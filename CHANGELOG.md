# Changelog

All notable changes to this package are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the package uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - Unreleased

First release. Targets the Unicom360 API contract v0.1.0 (`unicom360-openapi` tag `v0.1.0`).

### Added

- `Unicom360\Client` with the nine operations: `$client->sms->send()`,
  `$client->email->send()`, `$client->whatsapp->sendTemplate()`,
  `$client->whatsapp->sendSession()`, `$client->whatsapp->status()`,
  `$client->whatsapp->templates()`, `$client->balance->get()`, `$client->balance->all()` and
  `$client->health()`. Parameters are named arguments or one array keyed by the API's names.
- Typed, read-only result models that keep every field in `raw`, with the helpers
  `accepted()`, `invalid()`, `rejected()`, `byNumber()`, `byAddress()`, `byId()` and
  `byName()`.
- WhatsApp builders: `Session` factories (text, image, video, document, audio, sticker,
  buttons, list, location, contacts, reaction) and `TemplateRecipient`, `TemplateHeader`,
  `TemplateBody` and `TemplateButton` for template sends.
- The error hierarchy under `Unicom360\Exception`, with error codes normalised to
  `Unicom360\ErrorCode` constants.
- Automatic retries that are safe without idempotency keys: 429 waits for the next UTC
  minute, connection failures before sending are always retried, and anything that may have
  reached the gateway is retried only for reads and referenced SMS and WhatsApp sends.
- The `autoReferenceIds` option, which gives sends a generated `user_reference_id`.
- `Unicom360\Webhook::verify()`, `constructEvent()`, `parseSmsCallback()` and
  `parseEmailCallback()`. An empty or whitespace-only secret throws `ConfigurationException`
  before any other check, because anyone can forge a signature with an empty key.
- `Unicom360\Gsm` helpers: `isGsm7()`, `length()`, `units()`, `nonGsmCharacters()` and
  `validateSms()`.
- `CurlTransport` (default) and `Psr18Transport`, and optional PSR-3 logging.

[Unreleased]: https://github.com/TechRode-Solution-Limited/unicom360-php/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/TechRode-Solution-Limited/unicom360-php/releases/tag/v0.1.0
