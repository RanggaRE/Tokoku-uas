<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // 1. Today's sales (only Sale transactions with status != 'cancelled')
        $todaySales = Sale::where('status', '!=', 'cancelled')
            ->whereDate('created_at', $today)
            ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as count')
            ->first();

        // 2. Month's sales (only Sale transactions with status != 'cancelled')
        $monthSales = Sale::where('status', '!=', 'cancelled')
            ->where('created_at', '>=', $startOfMonth)
            ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as count')
            ->first();

        // 3. Low stock count (Product stock <= min_stock)
        $lowStockCount = Product::where('is_active', true)
            ->whereColumn('stock', '<=', 'min_stock')
            ->count();

        // 4. Last 7 days sales (excluding cancelled)
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $salesOnDate = Sale::where('status', '!=', 'cancelled')
                ->whereDate('created_at', $date)
                ->selectRaw('COALESCE(SUM(total), 0) as total, COUNT(*) as count')
                ->first();

            $last7Days[] = [
                'date' => $date->format('Y-m-d'),
                'total' => (double) $salesOnDate->total,
                'count' => (int) $salesOnDate->count,
            ];
        }

        // 5. Recent transactions (union of Sales and Purchases, similar to index logic in TransactionController)
        $salesQuery = DB::table('sales')
            ->select('invoice_number', 'created_at', 'total')
            ->where('status', '!=', 'cancelled');

        $purchasesQuery = DB::table('purchases')
            ->select('invoice_number', 'created_at', 'total')
            ->where('status', '!=', 'cancelled');

        $recentTransactions = $salesQuery->union($purchasesQuery)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'today_sales' => [
                'total' => (double) $todaySales->total,
                'count' => (int) $todaySales->count,
            ],
            'month_sales' => [
                'total' => (double) $monthSales->total,
                'count' => (int) $monthSales->count,
            ],
            'low_stock_count' => $lowStockCount,
            'last_7_days' => $last7Days,
            'recent_transactions' => $recentTransactions,
        ]);
    }
}
