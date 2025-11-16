---
title: PHP Installation
description: ""
layout: docs
---

# Client OAuth – PHP Installation (XWMS)

**Description:** Secure, advanced login and authentication APIs for businesses (XWMS).  
This page focuses on installing and using the `XwmsApiHelperPHP` library in PHP.

The text is written so that even a non‑developer (or a 10‑year‑old) can
understand the idea, but it also includes full code for professional use.

**Important:** we link users using the stable XWMS id `sub`, **not** the email address.

---

## 🚀 Quick Install (Fast Track)

1. **Install with Composer**

```bash
composer require xwms/package guzzlehttp/guzzle vlucas/phpdotenv
```

2. **Create a `.env` file** (recommended)

Create `.env` in your project root:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here           # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_CLIENT_DOMAIN=your-domain.example
XWMS_REDIRECT_URI=http://localhost/xwms/validateToken
XWMS_API_URL=https://api.xwms.example/
```

3. **Use the helper and visit the authenticate route**

Use `XwmsApiHelperPHP` to redirect users to XWMS and verify
the token when they come back.  
Full examples are shown below.

---

## 🧠 What is happening? (Explained)

Imagine a cinema:

- XWMS is the **ticket office**.  
- Your site is the **entrance door**.  

When somebody wants to enter:

1. You send them to the ticket office (XWMS).  
2. XWMS checks who they are and gives them a **ticket** (token).  
3. They come back to your entrance with that ticket.  
4. Your PHP code shows the ticket to XWMS again (“Is this real?”).  
5. XWMS responds with **who this person is** – including a stable id `sub`.  

You then use that `sub` to find or create a local user.

---

## Why we do **not** link by email

A naive solution is:

> “Look up the user by email. If not found, create them.”

Problems:

- users can change their email  
- some people share an email address  
- email alone is not a strong identity key  

If the email changes, your local account and the XWMS account
can get out of sync.

XWMS therefore gives you a **stable id** called `sub`:

- it never changes for that account  
- it is unique per user  
- it is like the **number on an ID card**  

So your database should remember:

> “Which local user belongs to which XWMS `sub`?”

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

Example `config/xwms.php`:

```php
<?php
return [
    'client_id'      => getenv('XWMS_CLIENT_ID') ?: null,
    'client_secret'  => getenv('XWMS_CLIENT_SECRET') ?: null,
    'client_domain'  => getenv('XWMS_CLIENT_DOMAIN') ?: null,
    'client_redirect'=> getenv('XWMS_REDIRECT_URI') ?: 'http://localhost/xwms/validateToken',
    'xwms_api_url'   => rtrim(getenv('XWMS_API_URL') ?: 'https://api.xwms.example/', '/') . '/',
];
```

---

## Loading `.env` (Secrets)

Think of `.env` as a **locked box** with your secret keys.
We use `vlucas/phpdotenv` to open that box when the app starts:

```php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad(); // loads .env if present, but won't crash if missing
```

Now you can read your settings with `getenv('XWMS_CLIENT_ID')`.

---

## The `XwmsApiHelperPHP` Class – Short Overview

The helper does three things:

1. Builds requests with your client id / secret.  
2. Asks XWMS for a redirect URL (`sign-token`) and sends the user there.  
3. After the user returns, verifies the token with `sign-token-verify`.  

It uses Guzzle for HTTP and expects JSON responses.

---

## Example A – Minimal Flow With Stable `sub` Linking (Recommended)

This example uses:

- `/xwms/auth` – send user to XWMS  
- `/xwms/validateToken` – receive token, verify, and log the user in  

**public/index.php** (simplified example)

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use XWMS\Package\Controllers\Api\XwmsApiHelperPHP;

// load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// very simple routing
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === '/xwms/auth') {
    $helper = new XwmsApiHelperPHP();
    $helper->auth();            // redirects to XWMS and exits
}

if ($path === '/xwms/validateToken') {
    try {
        $helper = new XwmsApiHelperPHP();
        $result = $helper->authValidate();   // verifies token, returns data array

        if (!empty($result['status']) && $result['status'] === 'success') {
            $userData = $result['data'] ?? [];

            // --- Professional linking: use "sub", not email ---
            $sub = $userData['sub'] ?? null;
            if (!$sub) {
                throw new Exception('Missing XWMS sub in response.');
            }

            // Pseudo‑code – replace with your own DB logic:
            $user = findOrCreateUserBySub($sub, $userData);

            // Here you would create a session / cookie for $user.
            echo "Welcome, " . htmlspecialchars($user['name']) . "!";
        } else {
            echo "Authentication failed: " . htmlspecialchars(json_encode($result));
        }
    } catch (Exception $e) {
        http_response_code(400);
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}

if ($path === '/') {
    echo '<a href="/xwms/auth">Login with XWMS</a>';
}
```

The key part is the `findOrCreateUserBySub` function.  
Here is a very simple **example** implementation in plain PHP with PDO:

```php
function getPdo(): PDO
{
    return new PDO('mysql:host=localhost;dbname=app', 'user', 'pass', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

function findOrCreateUserBySub(string $sub, array $userData): array
{
    $pdo = getPdo();

    // 1) Look for an existing link by sub
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM xwms_connections xc
        JOIN users u ON u.id = xc.user_id
        WHERE xc.sub = :sub
        LIMIT 1
    ");
    $stmt->execute(['sub' => $sub]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Optional: sync some fields from XWMS
        return $user;
    }

    // 2) No user yet → create one
    $name  = $userData['name'] ?? trim(($userData['given_name'] ?? '') . ' ' . ($userData['family_name'] ?? ''));
    $email = $userData['email'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (:name, :email, :password)");
    $stmt->execute([
        'name'     => $name ?: 'User',
        'email'    => $email,
        'password' => password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT),
    ]);

    $userId = (int) $pdo->lastInsertId();

    // 3) Store the XWMS sub in a connection table
    $stmt = $pdo->prepare("INSERT INTO xwms_connections (user_id, sub) VALUES (:user_id, :sub)");
    $stmt->execute(['user_id' => $userId, 'sub' => $sub]);

    return [
        'id'    => $userId,
        'name'  => $name ?: 'User',
        'email' => $email,
    ];
}
```

In database terms this means:

- table `users` contains your normal users  
- table `xwms_connections` contains `user_id` + `sub`  
- you always look up by `sub` when someone logs in via XWMS  

Even if the user changes email in XWMS, the `sub` stays the same,
so your link keeps working.

---

## Example B – Manual Guzzle Flow (More Control)

If you prefer to skip the helper and call the API yourself:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use GuzzleHttp\Client;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$clientId     = getenv('XWMS_CLIENT_ID');
$clientSecret = getenv('XWMS_CLIENT_SECRET');
$clientDomain = getenv('XWMS_CLIENT_DOMAIN');
$apiBase      = rtrim(getenv('XWMS_API_URL'), '/') . '/';

$guzzle = new Client(['base_uri' => $apiBase, 'timeout' => 10]);

// 1) Ask for a sign-token (get redirect url)
$resp = $guzzle->post('sign-token', [
    'headers' => [
        'X-Client-Id'     => $clientId,
        'X-Client-Secret' => $clientSecret,
        'X-Client-Domain' => $clientDomain,
        'Accept'          => 'application/json',
    ],
    'json' => [
        'redirect_url' => getenv('XWMS_REDIRECT_URI'),
    ],
]);

$body        = json_decode((string) $resp->getBody(), true);
$redirectUrl = $body['data']['url'] ?? $body['redirect_url'] ?? null;

if ($redirectUrl) {
    header('Location: ' . $redirectUrl);
    exit;
}

// 2) When user returns to /xwms/validateToken?token=abc -> verify:

$token = $_GET['token'] ?? null;
$resp  = $guzzle->post('sign-token-verify', [
    'headers' => [
        'X-Client-Id'     => $clientId,
        'X-Client-Secret' => $clientSecret,
        'X-Client-Domain' => $clientDomain,
        'Accept'          => 'application/json',
    ],
    'json' => [
        'token' => $token,
    ],
]);

$data = json_decode((string) $resp->getBody(), true);

if (($data['status'] ?? null) === 'success') {
    $userData = $data['data'] ?? [];
    // again: handle via sub
    $user = findOrCreateUserBySub($userData['sub'] ?? '', $userData);
}
```

This does the same as the helper, but with more manual control.

---

## Security Best Practices

1. **Never commit `.env` to git.** Add `.env` to `.gitignore`.  
2. **Use HTTPS** in production for both your site and the redirect URL.  
3. **Validate all inputs.** Never trust raw `$_GET` or `$_POST`.  
4. **Treat tokens as secrets.** Do not log them or expose them to the browser.  
5. **Log safely.** Log status codes and messages, not client secrets or tokens.  

---

## Example of a Successful Response

XWMS may return a payload like:

```json
{
  "status": "success",
  "message": "Successful authenticated",
  "data": {
    "sub": "xwms-user-1234",
    "email": "jane@example.com",
    "name": "Jane Doe",
    "email_verified": true
  }
}
```

Note the `sub` field – this is the **stable id** you should store
in your own `xwms_connections` table.

An error might look like:

```json
{
  "status": "error",
  "message": "Invalid token",
  "data": {}
}
```

Always check `status` before trusting `data`.

---

## Summary (One‑liner)

Install via Composer, keep secrets in `.env`, use `XwmsApiHelperPHP`
or plain Guzzle to handle the redirect and token verification, and **always**
link accounts using the stable XWMS `sub` id instead of the email address.

