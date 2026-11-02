<?php

declare(strict_types=1);

// Checks SMS text before sending: only GSM-7 text of up to 1,200 characters is accepted.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Gsm;

$text = "It\u{2019}s ready! Collect order ZM-4821 at Cairo Road.";

$issues = Gsm::validateSms($text);
if ($issues === []) {
    echo 'OK, ' . Gsm::units($text) . " unit(s) per recipient\n";
} else {
    echo 'Would be refused: ' . implode(', ', $issues) . "\n";
    foreach (Gsm::nonGsmCharacters($text) as $bad) {
        echo "  character {$bad['index']}: {$bad['char']}\n";
    }
}
