<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Http\Requests\ProductRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with('category');

        // Filter search (code, name, barcode)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Filter category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        // Filter low stock
        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        // Filter active status
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Check if all products are requested (dropdown/POS)
        if ($request->boolean('all')) {
            $products = $query->orderBy('name')->get();
            return response()->json($products);
        }

        // Otherwise paginate
        $perPage = $request->input('per_page', 15);
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($products);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $product = Product::create($data);

            // Log stock movement if initial stock is greater than 0
            if ($product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                    'reference' => 'Stok Awal',
                    'notes' => 'Input stok awal saat pembuatan produk baru',
                ]);
            }

            return $product;
        });

        $product->load('category');
        return response()->json($product, 201);
    }

    public function show($id): JsonResponse
    {
        $product = Product::with('category')->findOrFail($id);
        return response()->json($product);
    }

    public function update(ProductRequest $request, $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());
        $product->load('category');
        return response()->json($product);
    }

    public function destroy($id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();
        return response()->json(['message' => 'Product soft-deleted successfully']);
    }

    public function adjustStock(Request $request, $id): JsonResponse
    {
        $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $product = Product::findOrFail($id);

        DB::transaction(function () use ($request, $product) {
            $type = $request->input('type');
            $qty = $request->input('quantity');
            $stockBefore = $product->stock;
            $stockAfter = $stockBefore;

            if ($type === 'in') {
                $stockAfter = $stockBefore + $qty;
            } elseif ($type === 'out') {
                $stockAfter = max(0, $stockBefore - $qty);
            } elseif ($type === 'adjustment') {
                $stockAfter = $qty;
                // For stock_movements log: calculate absolute difference
                $qty = abs($stockAfter - $stockBefore);
            }

            $product->update(['stock' => $stockAfter]);

            StockMovement::create([
                'product_id' => $product->id,
                'type' => $type,
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference' => $request->input('reference'),
                'notes' => $request->input('notes'),
            ]);
        });

        $product->load('category');
        return response()->json($product);
    }
}
