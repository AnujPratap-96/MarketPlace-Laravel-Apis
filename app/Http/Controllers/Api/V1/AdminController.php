<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Vendor;
use App\Services\EscrowSettlementService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class AdminController extends Controller
{
    public function __construct(
        private readonly EscrowSettlementService $escrowSettlementService
    ) {}

    public function deliverOrder(int $id): JsonResponse
    {
        $order = Order::with('items')->findOrFail($id);

        $settledOrder = $this->escrowSettlementService->settleOrderEscrow($order);

        return response()->json([
            'message' => 'Order marked as delivered. Escrow released to vendor wallets.',
            'order' => new OrderResource($settledOrder),
        ], Response::HTTP_OK);
    }

    public function verifyVendor(int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_verified' => true]);

        return response()->json([
            'message' => "Vendor '{$vendor->store_name}' verified successfully.",
            'vendor' => $vendor,
        ], Response::HTTP_OK);
    }
}
