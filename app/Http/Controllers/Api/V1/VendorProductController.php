<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VendorProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $products = $vendor->products()->with('category')->latest()->paginate(15);

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'total' => $products->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $validated = $request->validated();

        $product = $vendor->products()->create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(5),
            'description' => $validated['description'] ?? null,
            'price_in_cents' => $validated['price_in_cents'],
            'stock' => $validated['stock'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => new ProductResource($product->load(['vendor', 'category'])),
        ], Response::HTTP_CREATED);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $product = $vendor->products()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price_in_cents' => ['sometimes', 'integer', 'min:50'],
            'stock' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => new ProductResource($product->fresh(['vendor', 'category'])),
        ], Response::HTTP_OK);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $product = $vendor->products()->findOrFail($id);

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ], Response::HTTP_OK);
    }

    public function wallet(Request $request): JsonResponse
    {
        $vendor = $request->user()->vendor;
        $transactions = $vendor->transactions()->latest()->take(20)->get();

        return response()->json([
            'store_name' => $vendor->store_name,
            'balance_in_cents' => $vendor->balance_in_cents,
            'formatted_balance' => '$' . number_format($vendor->balance_in_cents / 100, 2),
            'commission_rate' => $vendor->commission_rate . '%',
            'recent_transactions' => $transactions,
        ], Response::HTTP_OK);
    }
}
