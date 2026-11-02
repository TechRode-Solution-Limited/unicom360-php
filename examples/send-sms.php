<?php

declare(strict_types=1);

// Sends an SMS and handles the result and the errors a send can raise.
//
//   UNICOM360_API_KEY=... php examples/send-sms.php
//
// This sends a real, billed message: there is no sandbox.

require __DIR__ . '/../vendor/autoload.php';

use Unicom360\Client;
use Unicom360\Exception\ApiConnectionException;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\AuthenticationException;
use Unicom360\Exception\DuplicateReferenceException;
use Unicom360\Exception\InsufficientBundleException;
use Unicom360\Exception\InvalidRequestException;
use Unicom360\Exception\RateLimitException;
use Unicom360\Exception\Unicom360Exception;

$client = new Client(); // reads UNICOM360_API_KEY

try {
    $result = $client->sms->send(
        from: 'Unicom360',
        to: ['260971234567', '260961234567'],
        message: 'Your verification code is 482193. It expires in 5 minutes.',
        userReferenceId: 'otp-10421', // de-duplicated by the gateway, so retries are safe
        callbackUrl: 'https://example.com/webhooks/unicom360/sms',
    );

    // Results are not in request order past the tenth recipient: match by number.
    foreach ($result->accepted() as $accepted) {
        echo "Sent to {$accepted->mobileNumber}: {$accepted->messageId}\n";
    }
    foreach ($result->invalid() as $invalid) {
        echo "Not sent to {$invalid->mobileNumber}: {$invalid->status}\n";
    }
    $first = $result->byNumber('+260 97 123 4567');
    echo 'First recipient: ' . ($first->messageId ?? 'not found') . "\n";
    echo "api_reference: {$result->apiReference}\n";
} catch (InsufficientBundleException $e) {
    echo "Top up your SMS bundle ({$e->getApiReference()})\n";
} catch (DuplicateReferenceException $e) {
    // After a retry (attempts > 1) this means the first attempt went through.
    echo $e->getAttempts() > 1 ? "Already sent by an earlier attempt\n" : "Reference already used\n";
} catch (ApiException $e) {
    echo "Refused: {$e->getErrorCode()} ({$e->getMessage()})\n";
} catch (InvalidRequestException $e) {
    echo "Bad request: {$e->getMessage()}\n";
} catch (AuthenticationException $e) {
    echo "Check the API key: {$e->getErrorCode()}\n";
} catch (RateLimitException $e) {
    echo "Rate limited; try again in {$e->getRetryAfter()} s\n";
} catch (ApiConnectionException $e) {
    echo "Network problem after {$e->getAttempts()} attempts: {$e->getMessage()}\n";
} catch (Unicom360Exception $e) {
    echo "Unicom360 error ({$e->getType()}): {$e->getMessage()}\n";
}
