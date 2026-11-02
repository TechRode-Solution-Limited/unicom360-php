<?php

declare(strict_types=1);

// Looks up where WhatsApp messages are, by the message_id a send returned.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;
use Unicom360\Status;

$client = new Client();

$result = $client->whatsapp->status(['7c1f3a2e-5b64-4d2f-9a8e-0f1b2c3d4e5f', '00000000-0000-4000-8000-000000000000']);
foreach ($result->results as $message) {
    $line = match ($message->status) {
        Status::READ => "read at {$message->readAt}",
        Status::DELIVERED => "delivered at {$message->deliveredAt}",
        Status::FAILED => 'failed: ' . ($message->error->message ?? 'unknown reason'),
        Status::NOT_FOUND => 'not found',
        default => (string) $message->status, // statuses are open
    };
    echo "{$message->messageId}: {$line}\n";
}
