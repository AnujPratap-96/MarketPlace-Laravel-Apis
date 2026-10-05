<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Cache::remember('categories_tree', 3600, function () {
            return Category::with('children')->whereNull('parent_id')->get();
        });

        return response()->json([
            'data' => CategoryResource::collection($categories),
        ], Response::HTTP_OK);
    }
}
