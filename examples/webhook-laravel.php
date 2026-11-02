<?php

declare(strict_types=1);

// Laravel: a route for signed WhatsApp webhooks. Put it in routes/api.php (no CSRF), or exclude
// the path from VerifyCsrfToken in routes/web.php. Store the secret in config, not in code.

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Unicom360\Exception\ConfigurationException;
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Webhook;
use Unicom360\Webhook\MessageReceivedEvent;
use Unicom360\Webhook\MessageStatusEvent;

Route::post('/webhooks/unicom360', static function (Request $request): Response {
    $secret = (string) getenv('UNICOM360_WEBHOOK_SECRET');

    try {
        // getContent() is the raw body; $request->headers is a HeaderBag.
        $event = Webhook::constructEvent($request->getContent(), $request->headers, $secret);
    } catch (SignatureVerificationException $e) {
        Log::warning('Rejected Unicom360 webhook', ['reason' => $e->getReason()]);

        return new Response('', 401);
    } catch (ConfigurationException $e) {
        // The secret is unset or blank: a fault on this server, not a bad delivery. Answers 500.
        throw $e;
    } catch (Unicom360Exception) {
        return new Response('', 400);
    }

    if ($event instanceof MessageStatusEvent) {
        Log::info('WhatsApp status', ['delivery' => $event->deliveryId, 'status' => $event->data->status]);
    } elseif ($event instanceof MessageReceivedEvent) {
        Log::info('WhatsApp message received', ['delivery' => $event->deliveryId, 'type' => $event->data->type]);
    }

    return new Response('', 204);
});
