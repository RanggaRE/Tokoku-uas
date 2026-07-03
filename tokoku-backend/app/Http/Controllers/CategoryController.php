<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\CategoryRequest;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Eager load products count
        $categories = Category::withCount('products')->get();
        return response()->json($categories);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        // Reload count for response
        $category->loadCount('products');
        return response()->json($category, 201);
    }

    public function show($id): JsonResponse
    {
        $category = Category::withCount('products')->findOrFail($id);
        return response()->json($category);
    }

    public function update(CategoryRequest $request, $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->update($request->validated());
        $category->loadCount('products');
        return response()->json($category);
    }

    public function destroy($id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return response()->json(['message' => 'Category deleted successfully']);
    }
}
