<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransactionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $search = $request->input('search');
        $type = $request->input('type');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        // We build separate queries for Sales and Purchases to support union
        $sales = DB::table('sales')
            ->select([
                DB::raw("CONCAT('sale_', sales.id) as id"),
                'sales.invoice_number',
                DB::raw("'sale' as type"),
                'sales.created_at',
                'sales.total',
                'sales.payment_method',
                'sales.status',
                'sales.customer_name as reference_info',
                DB::raw("(SELECT COUNT(*) FROM sale_items WHERE sale_items.sale_id = sales.id) as items_count")
            ]);

        $purchases = DB::table('purchases')
            ->select([
                DB::raw("CONCAT('purchase_', purchases.id) as id"),
                'purchases.invoice_number',
                DB::raw("'purchase' as type"),
                'purchases.created_at',
                'purchases.total',
                'purchases.payment_method',
                'purchases.status',
                'purchases.notes as reference_info',
                DB::raw("(SELECT COUNT(*) FROM purchase_items WHERE purchase_items.purchase_id = purchases.id) as items_count")
            ]);

        // Apply filters
        if ($search) {
            $sales->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
            $purchases->where(function($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        if ($dateFrom) {
            $sales->whereDate('created_at', '>=', $dateFrom);
            $purchases->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $sales->whereDate('created_at', '<=', $dateTo);
            $purchases->whereDate('created_at', '<=', $dateTo);
        }

        // Determine final query
        if ($type === 'sale') {
            $query = $sales->orderBy('created_at', 'desc');
        } elseif ($type === 'purchase') {
            $query = $purchases->orderBy('created_at', 'desc');
        } else {
            // Union query
            $query = $sales->union($purchases);
        }

        $perPage = $request->input('per_page', 15);
        
        // Execute union pagination wrapping it in outer query
        $paginator = DB::table(DB::raw("({$query->toSql()}) as merged"))
            ->mergeBindings($query)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Convert items_count to a dummy items array so t.items.length works on frontend
        $paginator->getCollection()->transform(function ($item) {
            $item->items = array_fill(0, $item->items_count, null);
            return $item;
        });

        return response()->json($paginator);
    }

    public function show($compositeId): JsonResponse
    {
        if (str_starts_with($compositeId, 'sale_')) {
            $id = (int) str_replace('sale_', '', $compositeId);
            $sale = Sale::with(['items.product', 'user'])->findOrFail($id);

            return response()->json($this->formatSaleResponse($sale));
        } elseif (str_starts_with($compositeId, 'purchase_')) {
            $id = (int) str_replace('purchase_', '', $compositeId);
            $purchase = Purchase::with(['items.product', 'user'])->findOrFail($id);

            return response()->json($this->formatPurchaseResponse($purchase));
        }

        return response()->json(['message' => 'Transaction not found'], 404);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:sale,purchase',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $type = $request->input('type');

        // Get authenticated user or default first user
        $userId = auth()->id();
        if (!$userId) {
            $defaultRole = $type === 'sale' ? 'kasir' : 'admin_gudang';
            $userId = User::where('role', $defaultRole)->first()?->id ?? User::first()?->id;
        }

        $result = DB::transaction(function () use ($request, $type, $userId) {
            if ($type === 'sale') {
                return $this->handleSaleCreation($request, $userId);
            } else {
                return $this->handlePurchaseCreation($request, $userId);
            }
        });

        return response()->json($result, 201);
    }

    public function cancel($compositeId): JsonResponse
    {
        DB::transaction(function () use ($compositeId) {
            if (str_starts_with($compositeId, 'sale_')) {
                $id = (int) str_replace('sale_', '', $compositeId);
                $sale = Sale::lockForUpdate()->findOrFail($id);

                if ($sale->status === 'cancelled') {
                    throw ValidationException::withMessages(['message' => 'Transaksi sudah dibatalkan sebelumnya.']);
                }

                $sale->update(['status' => 'cancelled']);

                // Restore stock
                foreach ($sale->items as $item) {
                    $product = Product::lockForUpdate()->findOrFail($item->product_id);
                    $stockBefore = $product->stock;
                    $stockAfter = $stockBefore + $item->quantity;

                    $product->update(['stock' => $stockAfter]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'in',
                        'quantity' => $item->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'reference' => "Pembatalan Invoice {$sale->invoice_number}",
                        'notes' => 'Pengembalian stok karena transaksi dibatalkan',
                    ]);
                }
            } elseif (str_starts_with($compositeId, 'purchase_')) {
                $id = (int) str_replace('purchase_', '', $compositeId);
                $purchase = Purchase::lockForUpdate()->findOrFail($id);

                if ($purchase->status === 'cancelled') {
                    throw ValidationException::withMessages(['message' => 'Transaksi sudah dibatalkan sebelumnya.']);
                }

                $purchase->update(['status' => 'cancelled']);

                // Reduce stock
                foreach ($purchase->items as $item) {
                    $product = Product::lockForUpdate()->findOrFail($item->product_id);
                    $stockBefore = $product->stock;
                    $stockAfter = max(0, $stockBefore - $item->quantity);

                    $product->update(['stock' => $stockAfter]);

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'out',
                        'quantity' => $item->quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'reference' => "Pembatalan PO {$purchase->invoice_number}",
                        'notes' => 'Pengurangan stok karena pembelian dibatalkan',
                    ]);
                }
            } else {
                throw new \Exception('Invalid transaction type for cancellation');
            }
        });

        return response()->json(['message' => 'Transaksi berhasil dibatalkan']);
    }

    private function handleSaleCreation(Request $request, $userId): array
    {
        $discount = (double) $request->input('discount', 0);
        $tax = (double) $request->input('tax', 0);
        $paidAmount = (double) $request->input('paid_amount', 0);
        $paymentMethod = $request->input('payment_method', 'cash');
        $customerName = $request->input('customer_name');

        // Format: TR-YYYYMMDD-XXXX
        $dateStr = date('Ymd');
        $lastSale = Sale::where('invoice_number', 'like', "TR-{$dateStr}-%")->orderBy('id', 'desc')->first();
        $nextNum = $lastSale ? ((int) substr($lastSale->invoice_number, -4)) + 1 : 1;
        $invoiceNumber = 'TR-' . $dateStr . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        // Process items & compute total
        $subtotal = 0;
        $itemsData = [];

        foreach ($request->input('items') as $item) {
            $product = Product::lockForUpdate()->findOrFail($item['product_id']);

            if ($product->stock < $item['quantity']) {
                throw ValidationException::withMessages([
                    'items' => "Stok produk {$product->name} tidak mencukupi (Tersedia: {$product->stock}, Diminta: {$item['quantity']})"
                ]);
            }

            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $subtotal += $itemSubtotal;

            $itemsData[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $itemSubtotal,
            ];
        }

        $total = $subtotal - $discount + $tax;
        $changeAmount = $paymentMethod === 'cash' ? max(0, $paidAmount - $total) : 0;

        // Create Sale
        $sale = Sale::create([
            'invoice_number' => $invoiceNumber,
            'status' => 'completed',
            'customer_name' => $customerName,
            'user_id' => $userId,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => $total,
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'payment_method' => $paymentMethod,
        ]);

        // Process items & Stock movements
        foreach ($itemsData as $data) {
            $product = $data['product'];
            $qty = $data['quantity'];
            $stockBefore = $product->stock;
            $stockAfter = $stockBefore - $qty;

            // Update stock
            $product->update(['stock' => $stockAfter]);

            // Create Sale Item
            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $data['unit_price'],
                'discount' => 0,
                'subtotal' => $data['subtotal'],
            ]);

            // Log Stock Movement
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'out',
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $invoiceNumber,
                'notes' => 'Pengurangan stok dari transaksi penjualan POS',
            ]);
        }

        $sale->load('items.product');

        return $this->formatSaleResponse($sale);
    }

    private function handlePurchaseCreation(Request $request, $userId): array
    {
        $paymentMethod = $request->input('payment_method', 'transfer');
        $notes = $request->input('notes');

        // Format: PO-YYYYMMDD-XXXX
        $dateStr = date('Ymd');
        $lastPurchase = Purchase::where('invoice_number', 'like', "PO-{$dateStr}-%")->orderBy('id', 'desc')->first();
        $nextNum = $lastPurchase ? ((int) substr($lastPurchase->invoice_number, -4)) + 1 : 1;
        $invoiceNumber = 'PO-' . $dateStr . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $total = 0;
        $itemsData = [];

        foreach ($request->input('items') as $item) {
            $product = Product::lockForUpdate()->findOrFail($item['product_id']);
            $itemSubtotal = $item['quantity'] * $item['unit_price'];
            $total += $itemSubtotal;

            $itemsData[] = [
                'product' => $product,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $itemSubtotal,
            ];
        }

        // Create Purchase
        $purchase = Purchase::create([
            'invoice_number' => $invoiceNumber,
            'status' => 'completed',
            'notes' => $notes,
            'user_id' => $userId,
            'total' => $total,
            'payment_method' => $paymentMethod,
        ]);

        // Process items & Stock movements
        foreach ($itemsData as $data) {
            $product = $data['product'];
            $qty = $data['quantity'];
            $stockBefore = $product->stock;
            $stockAfter = $stockBefore + $qty;

            // Update stock
            $product->update(['stock' => $stockAfter]);

            // Create Purchase Item
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_price' => $data['unit_price'],
                'subtotal' => $data['subtotal'],
            ]);

            // Log Stock Movement
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $invoiceNumber,
                'notes' => 'Penambahan stok dari transaksi pembelian supplier',
            ]);
        }

        $purchase->load('items.product');

        return $this->formatPurchaseResponse($purchase);
    }

    private function formatSaleResponse(Sale $sale): array
    {
        return [
            'id' => "sale_{$sale->id}",
            'invoice_number' => $sale->invoice_number,
            'created_at' => $sale->created_at,
            'type' => 'sale',
            'status' => $sale->status,
            'customer_name' => $sale->customer_name,
            'payment_method' => $sale->payment_method,
            'subtotal' => $sale->subtotal,
            'discount' => $sale->discount,
            'tax' => $sale->tax,
            'total' => $sale->total,
            'paid_amount' => $sale->paid_amount,
            'change_amount' => $sale->change_amount,
            'cashier' => $sale->user?->name ?? 'Kasir',
            'items' => $sale->items->map(function ($item) {
                return [
                    'product_name' => $item->product?->name ?? 'Produk Terhapus',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => $item->discount,
                    'subtotal' => $item->subtotal,
                ];
            })->toArray()
        ];
    }

    private function formatPurchaseResponse(Purchase $purchase): array
    {
        return [
            'id' => "purchase_{$purchase->id}",
            'invoice_number' => $purchase->invoice_number,
            'created_at' => $purchase->created_at,
            'type' => 'purchase',
            'status' => $purchase->status,
            'notes' => $purchase->notes,
            'payment_method' => $purchase->payment_method,
            'subtotal' => $purchase->total,
            'discount' => 0,
            'tax' => 0,
            'total' => $purchase->total,
            'paid_amount' => $purchase->total,
            'change_amount' => 0,
            'cashier' => $purchase->user?->name ?? 'Admin',
            'items' => $purchase->items->map(function ($item) {
                return [
                    'product_name' => $item->product?->name ?? 'Produk Terhapus',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'discount' => 0,
                    'subtotal' => $item->subtotal,
                ];
            })->toArray()
        ];
    }
}
