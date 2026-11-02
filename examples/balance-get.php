<?php

declare(strict_types=1);

// Reads the units left on one bundle. Never billed.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\BundleType;
use Unicom360\Client;

$client = new Client();

$sms = $client->balance->get();
echo "SMS: {$sms->balance} units ({$sms->clientName})\n";

$whatsapp = $client->balance->get(BundleType::WHATSAPP);
echo "WhatsApp: {$whatsapp->balance} units\n";
