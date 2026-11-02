<?php

declare(strict_types=1);

// Lists the approved WhatsApp templates you can send, with the variables each one takes.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;

$client = new Client();

$list = $client->whatsapp->templates();
foreach ($list->templates as $template) {
    $variables = implode(', ', $template->body->variables ?? []);
    echo "{$template->name} ({$template->language}, {$template->category}): body variables [{$variables}]\n";
}
$orderReady = $list->byName('order_ready', 'en_US');
echo $orderReady === null ? "order_ready is not available\n" : "order_ready is sent from {$orderReady->from}\n";
