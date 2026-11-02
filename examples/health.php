<?php

declare(strict_types=1);

// Checks that the gateway is up. Needs no API key to be valid, but the client still needs one
// configured.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;

$client = new Client();

$health = $client->health();
echo "{$health->status} ({$health->environment}, {$health->version}) at {$health->timestamp}\n";
