<?php

declare(strict_types=1);

// Receives SMS and email delivery reports, posted to the callback_url of a send.
//
// These callbacks are NOT signed and never retried: anyone who knows the URL can post one, and
// a lost one is not resent. Treat them as hints. Use a hard-to-guess callback URL.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Exception\Unicom360Exception;
use Unicom360\Webhook;

$body = (string) file_get_contents('php://input');
$kind = $_GET['kind'] ?? 'sms';

try {
    if ($kind === 'email') {
        $report = Webhook::parseEmailCallback($body);
        error_log("email {$report->messageId} to {$report->emailAddress}: {$report->status}");
    } else {
        $report = Webhook::parseSmsCallback($body);
        error_log("sms {$report->messageId} to {$report->mobileNumber}: {$report->status}");
    }
    http_response_code(204);
} catch (Unicom360Exception) {
    http_response_code(400);
}
