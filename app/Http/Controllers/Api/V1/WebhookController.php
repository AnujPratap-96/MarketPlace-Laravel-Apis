<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookService $webhookService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $rawPayload = $request->getContent();
        $signature = $request->header('X-Webhook-Signature', '');
        $secret = config('services.payment.webhook_secret', 'test_webhook_secret_key');

        try {
            $result = $this->webhookService->handlePaymentWebhook(
                $rawPayload,
                $signature,
                $secret,
                $request->all()
            );

            return response()->json($result, Response::HTTP_OK);

        } catch (BadRequestHttpException $e) {
            return response()->json([
                'error' => 'INVALID_SIGNATURE',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
