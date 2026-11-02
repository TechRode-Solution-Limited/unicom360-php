<?php

declare(strict_types=1);

// Sends an email. This sends a real, billed message: there is no sandbox.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;

$client = new Client();

$result = $client->email->send(
    from: 'noreply@yourdomain.com',
    to: ['jane@example.com', 'john@example.com'],
    subject: 'Your order is confirmed',
    emailBody: '<p>Thanks for your order. Order ZM4821 is confirmed.</p>',
    replyTo: 'support@yourdomain.com',
);

// Match results by address, never by position.
$jane = $result->byAddress('Jane@Example.com');
echo 'Jane: ' . ($jane->messageId ?? 'not sent') . "\n";
echo \count($result->accepted()) . ' accepted, ' . \count($result->invalid()) . " invalid\n";
