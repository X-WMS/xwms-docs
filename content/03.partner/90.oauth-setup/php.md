---
title: PHP Installation
description: ""
layout: docs
---

# PHP Installation - XWMS Authentication

This guide shows how to set up XWMS authentication in **plain PHP**.
It uses the real XWMS flow: `sign-token` -> redirect -> `sign-token-verify`.

Important rule: **link users by `sub`**, not by email.

---

## Quick Setup

### 1) Install dependencies

```bash
composer require xwms/package guzzlehttp/guzzle vlucas/phpdotenv
```

### 2) Add env values

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_CLIENT_SECRET=your_client_secret_here
XWMS_DOMAIN=your-domain.example
XWMS_REDIRECT_URI=http://localhost/xwms/validateToken
XWMS_API_URI=https://xwms.nl/api/
```

### 3) Minimal routing

```php
use XWMS\Package\Controllers\Api\XwmsApiHelperPHP;

if ($path === '/xwms/auth') {
    $helper = new XwmsApiHelperPHP();
    $helper->auth();
}

if ($path === '/xwms/validateToken') {
    $helper = new XwmsApiHelperPHP();
    $result = $helper->authenticateAndSyncUser([
        'find_connection_by_sub' => 'findConnectionBySub',
        'find_user_by_email' => 'findUserByEmail',
        'create_user' => 'createUser',
        'update_user' => 'updateUser',
        'connect_user' => 'connectUser',
        'on_login' => 'startUserSession',
        'image_sync' => [
            'enabled' => true,
            'directory' => __DIR__ . '/../public/xwms',
        ],
    ]);
}
```

---

## The XWMS flow (real)

1) `sign-token` returns a redirect URL  
2) user logs in on XWMS  
3) XWMS redirects back with `token`  
4) server calls `sign-token-verify`  
5) response includes stable `sub`  

Always link by `sub`.

---

## Response format (always JSON)

All XWMS responses use the same envelope:

```json
{
  "status": "success",
  "message": "Human readable message",
  "data": { "..." : "payload" }
}
```

- `status` is `success` or `error`
- `message` explains what happened
- `data` contains the payload for that endpoint

---

## sign-token response (start auth)

When you call `sign-token`, XWMS responds with:

- `client_id` (string)
- `token` (string)
- `email` (string, if available)
- `expires_at` (ISO 8601 string)
- `url` (string, the login/redirect URL)

The helper handles the redirect URL for you.

---

## sign-token-verify response (user data)

When you verify the token, XWMS returns user data in `data`:

- `sub` (string, stable user id)
- `name` (string)
- `given_name` (string)
- `family_name` (string or null)
- `email` (string)
- `email_verified` (boolean)
- `picture` (string URL)

Always link users by `sub`.

---

## Example helper usage

```php
$helper = new XwmsApiHelperPHP();
$result = $helper->authenticateAndSyncUser([
    'find_connection_by_sub' => function ($sub) {
        return findConnectionBySub($sub);
    },
    'create_user' => function ($attributes) {
        return createUser($attributes);
    },
    'update_user' => function ($user, $attributes) {
        return updateUser($user, $attributes);
    },
    'connect_user' => function ($user, $sub) {
        return connectUser($user, $sub);
    },
    'on_login' => function ($user) {
        startUserSession($user);
    },
]);
```

---

## Sync user later

```php
$helper = new XwmsApiHelperPHP();
$result = $helper->syncAuthenticatedUserFromXwms([
    'user' => $currentUser,
    'get_sub_for_user' => function ($user) {
        return getSubForUser($user);
    },
    'update_user' => function ($user, $attributes) {
        return updateUser($user, $attributes);
    },
]);
```

---

## More usage patterns

### 1) Manual session (no auto login)

```php
$helper = new XwmsApiHelperPHP();
$result = $helper->authenticateAndSyncUser([
    'token' => $_GET['token'] ?? null,
    'on_login' => null, // you handle session manually
]);
```

### 2) Custom field mapping

```php
$helper->authenticateAndSyncUser([
    'find_connection_by_sub' => 'findConnectionBySub',
    'create_user' => 'createUser',
    'connect_user' => 'connectUser',
    'field_map' => [
        'name' => 'name',
        'email' => 'email',
        'picture' => 'picture',
    ],
]);
```

### 3) Extra payload for token verify

```php
$helper->authenticateAndSyncUser([
    'auth_payload' => [
        'extra' => 'value',
    ],
]);
```

---

## Summary

- Use `auth()` to redirect to XWMS.
- Use `authenticateAndSyncUser()` to verify token and link user.
- Use `syncAuthenticatedUserFromXwms()` to refresh user data.
- Always link by `sub`.
