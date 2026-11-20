<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payments\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Payments\Services\PaymentWebhookProcessor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayMongoWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PaymentWebhookProcessor $processor,
    ): JsonResponse {
        $signature = (string) $request->header('Paymongo-Signature', '');
        $rawPayload = (string) $request->getContent();

        try {
            $event = $processor->process($rawPayload, $signature);
        } catch (InvalidWebhookSignatureException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 400);
        }

        return response()->json([
            'status' => 'ok',
            'event_id' => $event->provider_event_id,
        ]);
    }
}
