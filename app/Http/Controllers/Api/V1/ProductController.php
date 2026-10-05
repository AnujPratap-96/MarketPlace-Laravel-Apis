<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['vendor', 'category'])->active();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->query('vendor_id'));
        }

        if ($request->filled('search')) {
            $term = $request->query('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $perPage = min((int)$request->query('per_page', 15), 50);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(string $identifier): JsonResponse
    {
        $product = Product::with(['vendor', 'category'])
            ->where('id', $identifier)
            ->orWhere('slug', $identifier)
            ->firstOrFail();

        return response()->json([
            'data' => new ProductResource($product),
        ], Response::HTTP_OK);
    }
}
