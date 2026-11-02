# Security policy

## Reporting a vulnerability

Please report security problems privately, never in a public issue:

- through GitHub's private vulnerability reporting on this repository
  ("Security" tab, "Report a vulnerability"), or
- by email to info@techrode.co.

Include the SDK version (`Unicom360\Client::VERSION`), your PHP version, and the steps to
reproduce. Never include a real API key or webhook secret: rotate any key that was exposed.

We acknowledge reports within three working days and keep you informed until a fix is
released.

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | Yes |

## What this SDK protects

- The API key is sent only in the `X-API-Key` header, only to the configured base URL, and only
  over HTTPS (plain HTTP is refused except for localhost). Redirects are never followed.
- The API key and webhook secrets are redacted from `var_dump()`, `print_r()`, `var_export()`,
  `json_encode()` and exception messages, and a client cannot be serialized.
- Webhook signatures are compared in constant time (`hash_equals`), with a timestamp tolerance
  of 300 seconds by default.
- Nothing is logged except the method, path, status, `api_reference`, attempt and duration.
