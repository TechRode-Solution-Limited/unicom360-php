<?php

declare(strict_types=1);

// Reads the units left on every bundle. Never billed.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;

$client = new Client();

$all = $client->balance->all();
foreach ($all->balances as $bundleType => $units) {
    echo "{$bundleType}: {$units}\n";
}
