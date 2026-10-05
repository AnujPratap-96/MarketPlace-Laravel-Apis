<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkoutService
    ) {}

    public function checkout(CheckoutRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $order = $this->checkoutService->checkout(
                $request->user(),
                $validated['items'],
                $validated['shipping_address']
            );

            return response()->json([
                'message' => 'Order created successfully. Inventory locked.',
                'order' => new OrderResource($order),
            ], Response::HTTP_CREATED);

        } catch (InsufficientStockException $e) {
            return response()->json([
                'error' => 'INSUFFICIENT_STOCK',
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product', 'items.vendor'])
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => OrderResource::collection($orders),
            'meta' => [
                'total' => $orders->total(),
            ],
        ], Response::HTTP_OK);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $order = $request->user()
            ->orders()
            ->with(['items.product', 'items.vendor'])
            ->findOrFail($id);

        return response()->json([
            'data' => new OrderResource($order),
        ], Response::HTTP_OK);
    }
}
