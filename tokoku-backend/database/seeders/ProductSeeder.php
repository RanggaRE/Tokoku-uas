<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\StockMovement;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $makanan = Category::where('name', 'Makanan')->first();
        $minuman = Category::where('name', 'Minuman')->first();
        $sembako = Category::where('name', 'Sembako')->first();
        $atk = Category::where('name', 'Alat Tulis')->first();
        $rumtang = Category::where('name', 'Kebutuhan Rumah Tangga')->first();

        $products = [
            [
                'category_id' => $minuman?->id,
                'code' => 'P001',
                'name' => 'Aqua Botol 600ml',
                'unit' => 'pcs',
                'purchase_price' => 2500,
                'selling_price' => 3500,
                'stock' => 50,
                'min_stock' => 10,
                'barcode' => '8999999123451',
                'is_active' => true,
                'description' => 'Air mineral kemasan botol 600ml',
            ],
            [
                'category_id' => $minuman?->id,
                'code' => 'P002',
                'name' => 'Coca Cola 250ml',
                'unit' => 'pcs',
                'purchase_price' => 4000,
                'selling_price' => 5500,
                'stock' => 30,
                'min_stock' => 5,
                'barcode' => '8999999123452',
                'is_active' => true,
                'description' => 'Minuman berkarbonasi rasa kola botol 250ml',
            ],
            [
                'category_id' => $makanan?->id,
                'code' => 'P003',
                'name' => 'Indomie Goreng',
                'unit' => 'pcs',
                'purchase_price' => 2700,
                'selling_price' => 3500,
                'stock' => 100,
                'min_stock' => 15,
                'barcode' => '8999999123453',
                'is_active' => true,
                'description' => 'Mie instan goreng rasa original',
            ],
            [
                'category_id' => $makanan?->id,
                'code' => 'P004',
                'name' => 'Chitato Sapi Panggang 68g',
                'unit' => 'pcs',
                'purchase_price' => 8500,
                'selling_price' => 10500,
                'stock' => 20,
                'min_stock' => 5,
                'barcode' => '8999999123454',
                'is_active' => true,
                'description' => 'Keripik kentang rasa sapi panggang',
            ],
            [
                'category_id' => $sembako?->id,
                'code' => 'P005',
                'name' => 'Beras Pandan Wangi 5kg',
                'unit' => 'bag',
                'purchase_price' => 65000,
                'selling_price' => 75000,
                'stock' => 10,
                'min_stock' => 3,
                'barcode' => '8999999123455',
                'is_active' => true,
                'description' => 'Beras kualitas super pandan wangi kemasan 5kg',
            ],
            [
                'category_id' => $sembako?->id,
                'code' => 'P006',
                'name' => 'Minyak Goreng Bimoli 2L',
                'unit' => 'pouch',
                'purchase_price' => 32000,
                'selling_price' => 36000,
                'stock' => 15,
                'min_stock' => 5,
                'barcode' => '8999999123456',
                'is_active' => true,
                'description' => 'Minyak goreng kelapa sawit Bimoli pouch 2 liter',
            ],
            [
                'category_id' => $atk?->id,
                'code' => 'P007',
                'name' => 'Buku Tulis Sinar Dunia 38 Lbr',
                'unit' => 'pack',
                'purchase_price' => 28000,
                'selling_price' => 33000,
                'stock' => 8,
                'min_stock' => 2,
                'barcode' => '8999999123457',
                'is_active' => true,
                'description' => 'Buku tulis isi 10 buku per pack',
            ],
            [
                'category_id' => $rumtang?->id,
                'code' => 'P008',
                'name' => 'Rinso Liquid Detergen 750ml',
                'unit' => 'pouch',
                'purchase_price' => 18000,
                'selling_price' => 22000,
                'stock' => 2,
                'min_stock' => 5,
                'barcode' => '8999999123458',
                'is_active' => true,
                'description' => 'Deterjen cair Rinso aroma rose fresh 750ml',
            ],
        ];

        foreach ($products as $prodData) {
            $product = Product::updateOrCreate(['code' => $prodData['code']], $prodData);

            // Log initial stock movement if it doesn't already exist for this product
            if ($product->wasRecentlyCreated && $product->stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $product->stock,
                    'stock_before' => 0,
                    'stock_after' => $product->stock,
                    'reference' => 'Stok Awal',
                    'notes' => 'Input stok awal dari ProductSeeder',
                ]);
            }
        }
    }
}
