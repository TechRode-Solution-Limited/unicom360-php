# Unicom360 PHP SDK

[![Packagist Version](https://img.shields.io/packagist/v/unicom360/unicom360-php)](https://packagist.org/packages/unicom360/unicom360-php)
[![CI](https://github.com/TechRode-Solution-Limited/unicom360-php/actions/workflows/ci.yml/badge.svg)](https://github.com/TechRode-Solution-Limited/unicom360-php/actions/workflows/ci.yml)

The official PHP client for the [Unicom360](https://unicom360.com) messaging API: SMS, email
and WhatsApp over one JSON API, with webhook verification and GSM-7 helpers.

This version targets the **Unicom360 API contract v0.1.0** (`unicom360-openapi` tag `v0.1.0`)
and passes every vector of its conformance suite. The API reference is at
[unicom360.com/docs](https://unicom360.com/docs).

- PHP 8.1 or later, with `ext-curl` and `ext-json`. No other runtime dependency.
- Optional: any PSR-18 HTTP client (`psr/http-client` and `psr/http-factory`) and any PSR-3
  logger (`psr/log`).

> There is no sandbox. Every accepted send is real and billed. `health()`, the balance calls,
> `templates()` and `status()` cost nothing.

## Contents

- [Install](#install)
- [Quick start](#quick-start)
- [Every operation](#every-operation)
- [Results](#results)
- [Errors](#errors)
- [Retries](#retries)
- [Automatic reference ids](#automatic-reference-ids)
- [Webhooks](#webhooks)
- [SMS and email delivery reports](#sms-and-email-delivery-reports)
- [GSM-7 helpers](#gsm-7-helpers)
- [Configuration](#configuration)
- [Logging](#logging)
- [Known differences from the published docs](#known-differences-from-the-published-docs)
- [Development](#development)

## Install

```sh
composer require unicom360/unicom360-php
```

## Quick start

```php
use Unicom360\Client;

$client = new Client(); // reads UNICOM360_API_KEY; or new Client(apiKey: '...')

$result = $client->sms->send(
    from: 'Unicom360',
    to: '260971234567',
    message: 'Your order ZM4821 is ready.',
);

echo $result->results[0]->messageId;
```

Create the API key in the portal (Developer > API keys) and keep it out of source control. A
missing or blank key throws `ConfigurationException` when the client is built.

Parameters are named arguments, or one array keyed by the API's own field names (handy when
the data already has that shape). Both send exactly the same request:

```php
$client->sms->send(from: 'Unicom360', to: ['260971234567'], message: 'Hello', userReferenceId: 'order-10421');

$client->sms->send([
    'from' => 'Unicom360',
    'to' => ['260971234567'],
    'message' => 'Hello',
    'user_reference_id' => 'order-10421',
]);
```

An optional field you do not set (or set to `null`) is left out of the request. Whatever you
do set is sent unchanged: a string `to` stays a string, a list stays a list, text is never
trimmed. The SDK does no business validation; the gateway does, and its answer becomes an
exception (see [Errors](#errors)).

## Every operation

Every operation also takes the per-call named arguments `timeout:` (seconds) and
`maxRetries:`.

### SMS

```php
$result = $client->sms->send(
    from: 'Unicom360',                       // your registered sender ID
    to: ['260971234567', '260961234567'],    // international form; up to 2,000
    message: 'Your verification code is 482193.',
    userReferenceId: 'otp-10421',            // optional; de-duplicated, makes retries safe
    callbackUrl: 'https://example.com/webhooks/unicom360/sms', // optional delivery reports
    source: 'crm',                           // optional label
);

foreach ($result->accepted() as $sms) {
    echo "{$sms->mobileNumber}: {$sms->messageId}\n";
}
foreach ($result->invalid() as $sms) {
    echo "{$sms->mobileNumber}: {$sms->status}\n";   // e.g. "Invalid mobile number"
}
$one = $result->byNumber('+260 97 123 4567');        // digits-only match
```

Only GSM-7 text is accepted, up to 1,200 characters; billing is
`max(1, ceil(length / 160))` units per recipient. See [GSM-7 helpers](#gsm-7-helpers).

### Email

```php
$result = $client->email->send(
    from: 'noreply@yourdomain.com',
    to: ['jane@example.com', 'john@example.com'],
    subject: 'Your order is confirmed',
    emailBody: '<p>Thanks for your order.</p>',
    replyTo: 'support@yourdomain.com',       // optional
    userReferenceId: 'inv-4821',             // optional
    callbackUrl: 'https://example.com/webhooks/unicom360/email', // optional
);

$jane = $result->byAddress('Jane@Example.com');      // trimmed, case-insensitive
```

### WhatsApp template

```php
use Unicom360\WhatsApp\TemplateBody;
use Unicom360\WhatsApp\TemplateButton;
use Unicom360\WhatsApp\TemplateHeader;
use Unicom360\WhatsApp\TemplateRecipient;

$result = $client->whatsapp->sendTemplate(
    template: 'order_ready',
    language: 'en_US',
    from: '260960123456',                    // optional when you have one WhatsApp number
    recipients: [
        new TemplateRecipient(
            to: '260971234567',
            userReferenceId: 'inv-42',
            header: TemplateHeader::image('https://example.com/receipts/ZM-4821.png'),
            body: TemplateBody::of(['Ada', 'ZM-4821']),
            buttons: [TemplateButton::url(0, 'ZM-4821')],
        ),
        new TemplateRecipient('260961234567', userReferenceId: 'inv-43', body: TemplateBody::of(['Joseph', 'ZM-4822'])),
    ],
);

echo "{$result->acceptedCount} accepted, {$result->rejectedCount} rejected\n";
foreach ($result->rejected() as $r) {
    echo "{$r->to}: {$r->reason}\n";   // e.g. INVALID_NUMBER
}
```

Headers: `TemplateHeader::text($value)`, `::image($url)`, `::video($url)`,
`::document($url, $filename)`. Bodies: `TemplateBody::of([...])` (positional) or
`TemplateBody::named(['name' => 'Ada'])`. Buttons: `TemplateButton::url($index, $value)`,
`TemplateButton::quickReply($index, $value)`. Authentication templates take `otp:`. Plain
values work too: a string header is a text header, a list body is positional, an associative
body is named, and a recipient can be an array in API shape.

### WhatsApp session message

Free-form messages, allowed within 24 hours of the customer's last message:

```php
use Unicom360\WhatsApp\ListRow;
use Unicom360\WhatsApp\ListSection;
use Unicom360\WhatsApp\ReplyButton;
use Unicom360\WhatsApp\Session;

$sent = $client->whatsapp->sendSession(Session::text('260971234567', 'Your order is ready.', userReferenceId: 's-1'));
echo $sent->messageId;

$client->whatsapp->sendSession(Session::image('260971234567', 'https://example.com/receipt.png', caption: 'Your receipt'));
$client->whatsapp->sendSession(Session::document('260971234567', 'https://example.com/invoice.pdf', filename: 'invoice-4821.pdf'));
$client->whatsapp->sendSession(Session::buttons('260971234567', 'Collect or deliver?', [
    new ReplyButton('collect', 'Collect'),
    new ReplyButton('deliver', 'Deliver'),
]));
$client->whatsapp->sendSession(Session::list('260971234567', 'Choose a slot', 'Pick a slot', [
    new ListSection([new ListRow('am', 'Morning', '08:00 to 12:00')], title: 'Today'),
]));
$client->whatsapp->sendSession(Session::location('260971234567', -15.4167, 28.2833, name: 'Cairo Road branch'));
$client->whatsapp->sendSession(Session::reaction('260971234567', $sent->messageId, "\u{1F44D}")); // "" removes it
```

Factories: `text`, `image`, `video`, `document`, `audio`, `sticker`, `buttons`, `list`,
`location`, `contacts` (Meta's contact objects, as arrays or `ContactCard`) and `reaction`.
Each takes the optional named arguments `from:`, `replyTo:` (not on `reaction`),
`userReferenceId:` and `callbackUrl:`; a built message also has `withFrom()`,
`withReplyTo()`, `withUserReferenceId()` and `withCallbackUrl()`. `replyTo` and a reaction's
target take either a `message_id` a send returned or the `wamid.` id of an inbound message
from a `message.received` webhook. `sendSession()` also accepts an array in API shape.

Outside the 24-hour window the call throws `SessionWindowClosedException`: send a template.

### WhatsApp status

```php
$status = $client->whatsapp->status(['7c1f3a2e-5b64-4d2f-9a8e-0f1b2c3d4e5f']); // 1 to 100 ids
$message = $status->byId('7c1f3a2e-5b64-4d2f-9a8e-0f1b2c3d4e5f');
echo $message?->status;          // ACCEPTED, SENT, DELIVERED, READ, FAILED, REJECTED, NOT_FOUND
echo $message?->error?->message; // when FAILED
```

### WhatsApp templates

```php
$list = $client->whatsapp->templates();
foreach ($list->templates as $template) {
    echo "{$template->name} ({$template->language}): " . implode(', ', $template->body->variables ?? []) . "\n";
}
$orderReady = $list->byName('order_ready', 'en_US');
```

### Balance

```php
use Unicom360\BundleType;

$sms = $client->balance->get();                         // SMS by default
$whatsapp = $client->balance->get(BundleType::WHATSAPP); // SMS, SMS_INTERNATIONAL, EMAIL, WHATSAPP
echo $whatsapp->balance;                                // float

$all = $client->balance->all();
echo $all->get(BundleType::EMAIL);                      // or $all->balances['EMAIL']
```

### Health

```php
$health = $client->health(); // no API key is sent
echo $health->status;        // "ok"
```

Runnable versions of all of these are in [`examples/`](examples/).

## Results

- Results are read-only objects with camelCase properties (`$result->apiReference`,
  `$sms->messageId`). Every model also has `raw`: the JSON object it was parsed from, so a
  field the gateway adds later is readable today (`$result->raw['new_field']`).
- `messageId` is always a string, even where the gateway sends a number.
- Statuses, categories, reasons and codes are open strings. Constants for today's values are
  in `Unicom360\Status`, `Unicom360\Category`, `Unicom360\BundleType` and
  `Unicom360\ErrorCode`; always keep a default branch.
- **SMS and email results are not in request order past the tenth recipient.** Use
  `byNumber()` and `byAddress()`, never positions.
- Partial success is not an error: invalid numbers in an SMS send, or rejected recipients in
  a template send, are results.
- Timestamps are the ISO 8601 strings the gateway sent.

## Errors

Everything the SDK throws extends `Unicom360\Exception\Unicom360Exception` (a
`RuntimeException`):

| Exception | When | Retried |
|---|---|---|
| `ConfigurationException` | no API key, a bad base URL or option, a blank webhook secret | |
| `ApiConnectionException` | DNS, refused connection, TLS, reset | before sending: always; after: if retry-safe |
| `ApiTimeoutException` (extends `ApiConnectionException`) | connect or response timeout | connect: always; response: if retry-safe |
| `ApiStatusException` | any other non-2xx (3xx, 403, 404, ...), or a 2xx without a usable envelope | never |
| `AuthenticationException` | 401 | never |
| `RateLimitException` | 429; `getRetryAfter()` is the seconds to the next UTC minute | always |
| `InvalidRequestException` | 400: the request's shape is wrong | never |
| `ApiServerException` | 5xx | if retry-safe |
| `ApiException` | HTTP 200 with `"success": false`: a business refusal | only `SEND_FAILED`, if retry-safe |
| `InsufficientBundleException`, `DuplicateReferenceException`, `NoValidRecipientsException`, `SessionWindowClosedException` | subclasses of `ApiException` for those codes | never |
| `SignatureVerificationException` | a webhook failed verification; `getReason()` says why | |

Errors from a response carry `getHttpStatus()`, `getApiReference()` (quote it to support),
`getRawBody()`, `getErrorCode()` (normalised, see below), `getData()`, `getHeaders()` and
`getAttempts()` (HTTP attempts made, retries included). `getType()` gives the contract's type
string (`api_error`, `rate_limit_error`, ...).

```php
use Unicom360\ErrorCode;
use Unicom360\Exception\ApiException;
use Unicom360\Exception\InsufficientBundleException;
use Unicom360\Exception\Unicom360Exception;

try {
    $client->sms->send(from: 'Unicom360', to: '260971234567', message: 'Hello');
} catch (InsufficientBundleException $e) {
    // top up
} catch (ApiException $e) {
    if ($e->getErrorCode() === ErrorCode::INVALID_SENDER_ID) {
        // register the sender ID
    }
} catch (Unicom360Exception $e) {
    error_log("{$e->getType()}: {$e->getMessage()} ({$e->getApiReference()})");
}
```

**Error codes.** The gateway answers some refusals with a sentence and others with a code.
`getErrorCode()` normalises both: `Invalid From Sender ID` and `Invalid Sender` become
`INVALID_SENDER_ID`, `duplicate user reference id` and `DUPLICATE_MSG_REF` become
`DUPLICATE_REFERENCE`, `INSUFFICIENT_BUNDLE: no international SMS bundle` becomes
`INSUFFICIENT_BUNDLE`, an upper-case code passes through, and anything else is `UNKNOWN`
(request-shape sentences included; read the message for those). `ErrorCode::normalize()` is
public.

**A `DuplicateReferenceException` after a retry means the message was sent.** When
`getAttempts()` is greater than 1, an earlier attempt of the same call very probably went
through (it timed out or failed after the request was written), the SDK retried, and the
retry was refused because the reference was already used. For session sends,
`getOriginalMessageId()` returns the first message's id when the gateway gives it.

## Retries

The client retries only what is safe without idempotency keys:

- **Always:** a 429 (rejected before any processing) and a connection that failed before
  anything was sent.
- **Only when the call is retry-safe:** a 5xx, a response timeout or reset after the request
  was written, and a `SEND_FAILED` refusal. Reads (`status`, `templates`, `balance`, `health`)
  are always retry-safe. An SMS or WhatsApp session send is retry-safe when it has a
  `userReferenceId`; a template send when every recipient has one, because the gateway
  de-duplicates references under a unique index.
- **Email is never retry-safe**, even with a reference: the gateway's email reference check is
  not atomic, so a retry that overlaps the first attempt could send twice.
- **Never:** 400, 401, other 4xx and other refusals.

Waits: a 429 waits until the next UTC minute (the rate limit is a fixed UTC-minute window and
no `Retry-After` header is sent) plus up to a second of jitter, and gives up at once if that
is longer than `maxRetryWait`. Other retries use full-jitter exponential backoff capped at 8
seconds. `maxRetries` (default 2) counts retries after the first attempt; a call can override
it:

```php
$client = new Client(maxRetries: 3, maxRetryWait: 30.0);
$client->balance->get(maxRetries: 0, timeout: 5.0);
```

When the retries run out, the last attempt's exception is thrown with `getAttempts()` set.

## Automatic reference ids

```php
$client = new Client(autoReferenceIds: true);
```

Off by default. When on, an SMS, email or session send without a `userReferenceId` gets one
(`u360-` followed by 32 hex characters), and so does every template recipient without one,
before the first attempt; every retry reuses it. That makes SMS and WhatsApp sends retry-safe.
Email gets a reference too, which lets the gateway refuse a later repeat of the same send, but
it stays not retry-safe. A reference you pass is never replaced. The generated values are in
the request, in the debug log line, and (for WhatsApp) echoed in the result's `raw`.

## Webhooks

WhatsApp events (`message.status`, `message.received`, `webhook.test`) are signed with your
endpoint's secret. Verify the exact bytes you received, before any JSON parsing:

```php
use Unicom360\Exception\SignatureVerificationException;
use Unicom360\Webhook;
use Unicom360\Webhook\MessageReceivedEvent;
use Unicom360\Webhook\MessageStatusEvent;
use Unicom360\Webhook\TestEvent;

$rawBody = (string) file_get_contents('php://input');

try {
    $event = Webhook::constructEvent($rawBody, $_SERVER, (string) getenv('UNICOM360_WEBHOOK_SECRET'));
} catch (SignatureVerificationException $e) {
    http_response_code(401); // $e->getReason(): missing_signature, signature_mismatch, ...
    exit;
}

if ($event instanceof MessageStatusEvent) {
    // $event->data->messageId, ->status (sent, delivered, read, failed), ->error
} elseif ($event instanceof MessageReceivedEvent) {
    // $event->data->from, ->type, ->text, ->media, ->payload, ->replyTo, ->windowExpiresAt
} elseif ($event instanceof TestEvent) {
    // the portal's "Send test"
}
http_response_code(204);
```

Laravel (`routes/api.php`, so no CSRF check):

```php
Route::post('/webhooks/unicom360', function (Illuminate\Http\Request $request) {
    try {
        $event = Unicom360\Webhook::constructEvent($request->getContent(), $request->headers, config('services.unicom360.webhook_secret'));
    } catch (Unicom360\Exception\SignatureVerificationException $e) {
        return response('', 401);
    }
    // handle $event, then:
    return response('', 204);
});
```

Symfony is the same with `$request->getContent()` and `$request->headers`.

- `Webhook::verify($rawBody, $headers, $secret, tolerance: 300)` only checks; `constructEvent()`
  checks and parses. `$headers` can be `$_SERVER`, an array of name to value (or list of
  values), a PSR-7 request or a Symfony/Laravel `HeaderBag`; names are case-insensitive.
- The secret is the 43-character string from the portal, used as is. The signature is
  `sha256=` and the lowercase hex HMAC-SHA256 of `"<X-Unicom360-Timestamp>.<raw body>"`,
  compared in constant time; deliveries more than 300 seconds from your clock are rejected.
- An empty or whitespace-only secret (an unset environment variable through `(string) getenv()`,
  say) throws `ConfigurationException` before any other check, because anyone can forge a
  signature with an empty key. That is a fault in your configuration, not a failed verification:
  let it answer 500, and do not catch it where you answer 401.
- An event this SDK does not know yet parses as `UnknownEvent` (with `data` as an array)
  instead of failing. Answer it 2xx.
- Deliveries are retried on any non-2xx answer (after 30 s, 2 min, 10 min, 30 min, 1 h, 3 h,
  6 h and 12 h): answer 2xx quickly and drop repeats by `$event->deliveryId`.
- In `message.received`: a caption arrives in `text`, not in `media`; `payload` is set only
  for location, contacts, reaction, sticker and unsupported messages; an interactive or
  button reply has the chosen title in `text` and `replyTo` set to your interactive message's
  `wamid.`, and the button or row id is not passed on.

## SMS and email delivery reports

The `callbackUrl` of an SMS or email send receives delivery reports:

```php
$report = Unicom360\Webhook::parseSmsCallback(file_get_contents('php://input'));
// $report->messageId, ->status (SENT, DELIVERED, FAILED, UNKNOWN_ERROR), ->mobileNumber

$report = Unicom360\Webhook::parseEmailCallback(file_get_contents('php://input'));
// $report->messageId (a string), ->status (SENT, DELIVERED, FAILED), ->emailAddress, ->message
```

**These callbacks are not signed and never retried.** Anyone who knows the URL can post one,
and a lost one is not resent. Treat them as hints, use a hard-to-guess URL, and confirm
anything important another way.

## GSM-7 helpers

Opt-in checks that match the gateway exactly:

```php
use Unicom360\Gsm;

Gsm::isGsm7("It\u{2019}s ready");    // false: the curly apostrophe is not GSM-7
Gsm::length("Hi\r\nthere");          // 8: CR LF counts as one character
Gsm::units(str_repeat('a', 161));    // 2 billing units: max(1, ceil(length / 160))
Gsm::nonGsmCharacters('Thanks 😀');  // [['char' => '😀', 'index' => 7]]
Gsm::validateSms($text);             // [] when the gateway would accept it, else
                                     // 'empty', 'too_long' (over 1,200), 'non_gsm'
```

`Gsm::CHARSET`, `Gsm::MAX_LENGTH` (1200) and `Gsm::UNIT_LENGTH` (160) are constants. Billing
units are not GSM segments.

## Configuration

```php
$client = new Client(
    apiKey: null,             // default: UNICOM360_API_KEY
    baseUrl: null,            // default: UNICOM360_BASE_URL, else https://api.unicom360.com
    timeout: 60.0,            // seconds per attempt
    connectTimeout: 10.0,     // seconds, TLS included
    maxRetries: 2,
    maxRetryWait: 60.0,       // seconds
    autoReferenceIds: false,
    appInfo: new Unicom360\AppInfo('shopsync', '2.3.0', 'https://shopsync.example'),
    transport: null,          // default: Unicom360\Http\CurlTransport
    logger: null,             // a PSR-3 logger
);

$patient = $client->withOptions(timeout: 120.0); // clients are immutable
```

- `baseUrl` must be `https`; plain `http` is accepted only for `localhost`, `127.0.0.0/8` and
  `::1` (for a local mock). A path prefix is kept. Redirects are never followed, so the API
  key is never replayed to another host.
- `appInfo` is appended to the User-Agent, `unicom360-php/0.1.0 (php 8.4.1; linux; x86_64)`.
- The API key is redacted from `var_dump()`, `print_r()`, `var_export()` and `json_encode()`
  of the client, never appears in exception messages, and a client cannot be serialized.
- `clock`, `sleeper` and `random` are test seams.

**Transports.** `CurlTransport` takes extra cURL options, for example a proxy or a CA bundle:
`new CurlTransport([CURLOPT_PROXY => 'http://proxy:3128'])`. To use your own HTTP stack,
`Psr18Transport` wraps any PSR-18 client (`composer require psr/http-client psr/http-factory`):

```php
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7\HttpFactory;
use Unicom360\Http\Psr18Transport;

$factory = new HttpFactory();
$client = new Unicom360\Client(transport: new Psr18Transport(
    new Guzzle(['allow_redirects' => false, 'timeout' => 60, 'connect_timeout' => 10]),
    $factory,
    $factory,
));
```

Configure the PSR-18 client's timeouts and turn its redirects off yourself. PSR-18 cannot say
whether a failed request reached the server, so its failures are retried only for retry-safe
calls. Any class implementing `Unicom360\Http\Transport` works too: it throws
`Unicom360\Http\TransportException` with `sent` and `timeout` when no response arrived.

## Logging

Pass any PSR-3 logger (`logger: $monolog`); without one nothing is logged. Each attempt logs
one debug line with `method`, `path`, `attempt`, `status` (or the connection failure),
`api_reference` and `duration_ms`; each scheduled retry logs one info line with `attempt`,
`wait_s` and `cause`. Request and response bodies, headers, parameters, phone numbers,
addresses and the API key are never logged.

## Known differences from the published docs

The SDK follows the gateway's actual behaviour, which differs from the API reference in a few
places:

1. **Request-shape errors (400) can carry a list of messages.** The SDK joins them with `"; "`
   into the exception message.
2. **SMS and email results are not in request order** past the tenth recipient: use
   `byNumber()` and `byAddress()`.
3. **WhatsApp balances are whole numbers today**, although the docs mention fractions. The SDK
   returns floats either way.
4. **Local number formats:** only `095`, `096`, `097` and `076` prefixes are normalised to
   `260...`; other local forms come back as `Invalid mobile number`. Send the international
   form.
5. **The SMS and email `callbackUrl` is not validated** by the gateway (the WhatsApp one is),
   and the WhatsApp `callbackUrl` limit of 300 is in bytes, not characters.
6. **Session `text` must not be blank**: whitespace-only text is refused with a 400.
7. **The health `timestamp` has microseconds** (`...30.412345Z`), not milliseconds.
8. **A session `DUPLICATE_MSG_REF` can carry a null original id** when two requests race for
   the same reference; `getOriginalMessageId()` is then `null`.
9. **Rejected template recipients echo the request's values**, which can be numbers; the SDK
   turns `to` and `userReferenceId` into strings.
10. **The balance endpoints can refuse** with `CLIENT_NOT_FOUND` (HTTP 200, no
    `api_reference`); the SDK throws `ApiException`.
11. **An email `userReferenceId` does not stop concurrent duplicates**, only sequential ones,
    which is why email is never retried after the request may have been sent.
12. **429 responses carry no `Retry-After`.** The limit is a fixed UTC-minute window, which is
    what `getRetryAfter()` and the retry wait use.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md): `composer check` runs `composer validate --strict`,
PHP-CS-Fixer (PER-CS 2.0), PHPStan at level max and PHPUnit, including the full conformance
suite in `tests/conformance` (vendored from `unicom360-openapi` v0.1.0). The contract test
against a Prism mock runs with `vendor/bin/phpunit --group contract`.

Security reports: see [SECURITY.md](SECURITY.md).

## License

MIT. Copyright (c) 2026 Techrode Solutions Limited. See [LICENSE](LICENSE).
