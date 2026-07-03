<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');

        return [
            'code' => 'required|string|max:50|unique:products,code,' . $productId,
            'name' => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'unit' => 'required|string|max:50',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'stock' => $productId ? 'nullable|integer|min:0' : 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'barcode' => 'nullable|string|max:255',
            'is_active' => 'required|boolean',
            'description' => 'nullable|string',
        ];
    }
}
