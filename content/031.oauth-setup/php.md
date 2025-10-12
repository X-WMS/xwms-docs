---
title: PHP Installation
description: ""
layout: docs
---

# Client OAuth — PHP Installation (XWMS)

**Description:** Secure, advanced login and authentication APIs for businesses (XWMS). This page focuses on installing and using the `XwmsApiHelperPHP` library in PHP. It's written so even a 10‑year‑old can follow the main ideas, but it also includes full, step‑by‑step technical instructions for developers.

---

## Quick Install ( Fast track )

Follow these three steps to get going quickly.

1. **Install with Composer**

```bash
composer require xwms/package guzzlehttp/guzzle vlucas/phpdotenv
```

2. **Create a `.env` file** (recommended)

Create a file called `.env` in your project root:

```
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_CLIENT_DOMAIN=your-domain.example
XWMS_REDIRECT_URI=http://localhost/xwms/validateToken
XWMS_API_URL=https://api.xwms.example/
```

3. **Use the helper and visit the authenticate route**

Use the `XwmsApiHelperPHP` class to redirect users to XWMS and validate tokens on return. See the full examples below.

---

## Full, Friendly, Step-by-Step Installation (Explained)

Imagine XWMS is a friendly gatekeeper for your website. When someone wants to get into your site, they go to XWMS, XWMS checks who they are, and then lets them back in with a special ticket (a token).

We will:

1. Install the software that talks to the gatekeeper.
2. Tell our app the secret keys that XWMS gives us (we keep them hidden in a `.env` file).
3. Make two doors (URLs): one to send the user to XWMS, and another to accept them when XWMS sends them back with the ticket.

### Why use `.env`?

Think of `.env` like a locked treasure chest in your project where you hide secret keys. We use `vlucas/phpdotenv` to open that chest when the app starts.

**Install dotenv:**

```bash
composer require vlucas/phpdotenv
```

Then add this at the start of your app (for example `public/index.php` or `bootstrap.php`):

```php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad(); // loads .env if present, but won't crash if missing
```

Now you can read your secrets with `getenv('XWMS_CLIENT_ID')` or `$_ENV['XWMS_CLIENT_ID']`.

---

## Project Layout Recommendation

A small, safe layout for a plain PHP app:

```
/project-root
  /public
    index.php
  /src
    XwmsApiHelperPHP.php
  /config
    xwms.php
  .env
  composer.json
```

Put `xwms.php` in `config/` with default fallback values that read from environment variables.

Example `config/xwms.php`:

```php
<?php
return [
    'client_id' => getenv('XWMS_CLIENT_ID') ?: null,
    'client_secret' => getenv('XWMS_CLIENT_SECRET') ?: null,
    'client_domain' => getenv('XWMS_CLIENT_DOMAIN') ?: null,
    'client_redirect' => getenv('XWMS_REDIRECT_URI') ?: 'http://localhost/xwms/validateToken',
    'xwms_api_url' => rtrim(getenv('XWMS_API_URL') ?: 'https://api.xwms.example/', '/') . '/',
];
```

---

## The `XwmsApiHelperPHP` Class — How it Works (Short)

This helper does three main things:

1. Builds requests with your client id/secret.
2. Asks XWMS for a redirect URL (`sign-token`) and redirects the user there.
3. After the user returns, it verifies the token with `sign-token-verify`.

The class uses Guzzle to make HTTP calls and expects JSON responses.

---

## Example A — Using `XwmsApiHelperPHP` (Recommended)

This example shows a minimal plain PHP flow. Place the helper in `/src/XwmsApiHelperPHP.php` (your class as provided).

**public/index.php** (very small example)

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

// load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use XWMS\Package\Controllers\Api\XwmsApiHelperPHP;

$path = $_SERVER['REQUEST_URI'];

if ($path === '/xwms/auth') {
    // redirect user to XWMS
    $helper = new XwmsApiHelperPHP();
    $helper->auth(); // this will send a Location header and exit
}

if ($path === '/xwms/validateToken') {
    try {
        $helper = new XwmsApiHelperPHP();
        $result = $helper->authValidate(); // will POST token to verify

        // Example result handling
        if (!empty($result['status']) && $result['status'] === 'success') {
            // safely extract user fields
            $user = $result['data'] ?? [];
            echo "Welcome, " . htmlspecialchars($user['name'] ?? 'User') . "!";
        } else {
            echo "Authentication failed: " . htmlspecialchars(json_encode($result));
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

// otherwise show a simple link

if ($path === '/') {
    echo '<a href="/xwms/auth">Login with XWMS</a>';
}
```

**Notes:**

* `auth()` calls the API, gets a redirect URI, and issues `header('Location: ...')`.
* `authValidate()` reads `$_GET['token']` and calls `sign-token-verify`.

---

## Example B — Not using the helper (Manual Guzzle flow)

If you want more control or don't want to use the helper class, here is how to perform the same flow manually.

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$clientId = getenv('XWMS_CLIENT_ID');
$clientSecret = getenv('XWMS_CLIENT_SECRET');
$clientDomain = getenv('XWMS_CLIENT_DOMAIN');
$apiBase = rtrim(getenv('XWMS_API_URL'), '/') . '/';

$guzzle = new Client(['base_uri' => $apiBase, 'timeout' => 10]);

// 1) Ask for a sign-token (get redirect url)
try {
    $resp = $guzzle->post('sign-token', [
        'headers' => [
            'X-Client-Id' => $clientId,
            'X-Client-Secret' => $clientSecret,
            'X-Client-Domain' => $clientDomain,
            'Accept' => 'application/json',
        ],
        'json' => [
            'redirect_url' => getenv('XWMS_REDIRECT_URI')
        ]
    ]);

    $body = json_decode((string) $resp->getBody(), true);
    $redirectUrl = $body['data']['url'] ?? $body['redirect_url'] ?? null;
    if ($redirectUrl) {
        header('Location: ' . $redirectUrl);
        exit;
    }
} catch (\Throwable $e) {
    echo 'Error contacting XWMS: ' . htmlspecialchars($e->getMessage());
    exit;
}

// 2) When user returns to /xwms/validateToken?token=abc -> verify:

// Example verification request
try {
    $token = $_GET['token'] ?? null;
    $resp = $guzzle->post('sign-token-verify', [
        'headers' => [
            'X-Client-Id' => $clientId,
            'X-Client-Secret' => $clientSecret,
            'X-Client-Domain' => $clientDomain,
            'Accept' => 'application/json',
        ],
        'json' => [
            'token' => $token
        ]
    ]);

    $data = json_decode((string) $resp->getBody(), true);
    // handle $data similar to helper example
} catch (\Throwable $e) {
    echo 'Verification failed: ' . htmlspecialchars($e->getMessage());
}
```

This manual approach does the exact same steps as the helper but gives you the freedom to change headers, timeouts, or logging.

---

## Security Best Practices (Very Important)

1. **Never commit `.env` to git.** Add `.env` to `.gitignore`.
2. **Limit secret access.** Only your server and deployment system should have the `.env` file.
3. **Use HTTPS** for your `XWMS_REDIRECT_URI` and your app in production.
4. **Validate inputs.** Never trust `$_GET` or `$_POST` without checking and sanitizing.
5. **Use short token lifetimes** and treat returned tokens as sensitive. Store them encrypted if you must store.
6. **Log safely.** Do not log client secrets, tokens, or sensitive user info. Log only useful debugging info.

---

## Troubleshooting Tips (Practical)

* **If login doesn't redirect:** check `.env` values and the `XWMS_API_URL` and `XWMS_REDIRECT_URI`.
* **If token verification fails:** inspect the HTTP response body in logs (`storage/logs` or your error log) for error messages.
* **If you see `Missing required client configuration.`** — that means `client_id`, `client_secret`, or `client_domain` were not found by the code. Check your `config/xwms.php` and `.env` values.

---

## Example Responses (What the API might return)

A successful verify may look like:

```json
{
  "status": "success",
  "message": "Successful authenticated",
  "data": {
    "email": "jane@example.com",
    "name": "Jane Doe",
    "email_verified": true
  }
}
```

An error may look like:

```json
{
  "status": "error",
  "message": "Invalid token",
  "data": {}
}
```

Always check `status` and do not assume `data` exists.

---

## Useful Extra Tips

* Use environment-specific `.env` files (for example `.env.local`, `.env.production`) and a secure deployment pipeline.
* If you deploy on a platform (Heroku, DigitalOcean, Vercel), use the platform's secret management to store these keys.
* For local development you can use `php -S localhost:8000 -t public` to quickly test.

---

## Summary (One-liner)

Install via Composer, keep secrets in `.env` with `vlucas/phpdotenv`, use `XwmsApiHelperPHP` to manage the redirect & verification flow (or do it manually with Guzzle), and always follow security best practices.

---

*End of document.*
