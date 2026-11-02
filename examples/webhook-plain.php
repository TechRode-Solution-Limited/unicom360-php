<?php

declare(strict_types=1);

// A plain-PHP endpoint for signed WhatsApp webhooks (message.status, message.received,
// webhook.test). Point the portal's webhook URL at it and set UNICOM360_WEBHOOK_SECRET.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Exception\ConfigurationException;
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Exception\Unicom360Exception;
use Unicom360\Webhook;
use Unicom360\Webhook\MessageReceivedEvent;
use Unicom360\Webhook\MessageStatusEvent;
use Unicom360\Webhook\TestEvent;

$secret = (string) getenv('UNICOM360_WEBHOOK_SECRET');

// The exact bytes received: verify before any JSON parsing, and never re-encode the body.
$rawBody = (string) file_get_contents('php://input');

try {
    // $_SERVER holds the headers as HTTP_X_UNICOM360_SIGNATURE and so on.
    $event = Webhook::constructEvent($rawBody, $_SERVER, $secret);
} catch (SignatureVerificationException $e) {
    http_response_code(401);
    error_log("Rejected webhook: {$e->getReason()}");

    exit;
} catch (ConfigurationException $e) {
    // The secret is unset or blank: a fault on this server, not a bad delivery.
    http_response_code(500);
    error_log($e->getMessage());

    exit;
} catch (Unicom360Exception) {
    http_response_code(400);

    exit;
}

// Deliveries are retried on any non-2xx answer: answer quickly, and drop repeats by deliveryId.
if ($event instanceof MessageStatusEvent) {
    error_log("{$event->deliveryId}: message {$event->data->messageId} is {$event->data->status}");
} elseif ($event instanceof MessageReceivedEvent) {
    // A caption arrives in text; a button reply has the chosen title in text.
    error_log("{$event->deliveryId}: {$event->data->from} wrote: {$event->data->text}");
} elseif ($event instanceof TestEvent) {
    error_log("Test event: {$event->data->message}");
}

http_response_code(204);
