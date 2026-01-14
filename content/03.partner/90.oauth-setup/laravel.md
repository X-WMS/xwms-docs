---
title: Laravel Installation
description: ""
layout: docs
---

# Laravel Installation - XWMS Authentication

This guide is the current Laravel setup for XWMS authentication.
It uses the **real XWMS flow**: `sign-token` -> redirect -> `sign-token-verify`.

Important rule: **link users by the stable `sub` id**, not by email.

---

## Quick Setup

### 1) Install the package

```bash
composer require xwms/package
```

### 2) Publish the config

```bash
php artisan vendor:publish --tag=xwms-config
```

### 3) Add env values

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_CLIENT_SECRET=your_client_secret_here
XWMS_DOMAIN=your-domain.example
XWMS_REDIRECT_URI=http://your-app.test/xwms/validateToken
XWMS_API_URI=https://xwms.nl/api/
```

### 4) Add routes

```php
use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\Api\XwmsApiHelper;
use App\Http\Controllers\HomeController;

Route::get('/xwms/info', [XwmsApiHelper::class, 'info']);
Route::get('/xwms/auth', [XwmsApiHelper::class, 'auth']);
Route::get('/xwms/validateToken', [HomeController::class, 'authValidate']);
```

---

## The correct flow (XWMS)

1) Your app calls `sign-token` and gets a **redirect URL**.
2) The user logs in on XWMS.
3) XWMS redirects back with a **token**.
4) Your server verifies the token using `sign-token-verify`.
5) XWMS returns user data including **sub** (stable id).
6) You link that `sub` to your local user (xwms_connections table).

This is **not** the Google OAuth code exchange. XWMS returns a token directly.

---

## Required DB table

Create a connection table:

```bash
php artisan make:model XwmsConnection -m
```

Migration example:

```php
Schema::create('xwms_connections', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('user_id')->index();
    $table->string('sub')->unique();
    $table->timestamps();
});
```

---

## Updated controller (uses new helper features)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\XwmsConnection;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

class HomeController extends Controller
{
    public function authValidate()
    {
        $result = XwmsApiHelper::authenticateAndSyncUser([
            'login' => true,
            'remember' => true,
            'create_user' => true,
            'update_existing' => true,
            'link_by_email' => true,
            'image_sync' => [
                'enabled' => true,
                'disk' => 'public',
                'directory' => 'users/xwms',
                'delete_old' => true,
            ],
        ]);

        if (($result['status'] ?? null) !== 'success') {
            return redirect()->route('auth.login');
        }

        return redirect()->route('home.index');
    }
}
```

This uses the built-in helper to:
- verify the token
- read `sub`
- find or create the user
- update user data from XWMS
- store the connection in `xwms_connections`

---

## More usage patterns (copy/paste)

### 1) Manual login (no auto Auth::login)

```php
$result = XwmsApiHelper::authenticateAndSyncUser([
    'login' => false,
    'remember' => false,
]);

if (($result['status'] ?? null) === 'success') {
    // Your own session logic here
}
```

### 2) Custom field mapping

```php
XwmsApiHelper::authenticateAndSyncUser([
    'field_map' => [
        'name' => 'name',
        'email' => 'email',
        'img' => 'picture',
    ],
    'field_transforms' => [
        'name' => function ($value) {
            return trim($value ?? '');
        },
    ],
]);
```

### 3) Extra payload for token verification

```php
XwmsApiHelper::authenticateAndSyncUser([
    'auth_payload' => [
        'extra' => 'value'
    ]
]);
```

### 4) Sync logged-in user later

```php
$result = XwmsApiHelper::syncAuthenticatedUserFromXwms([
    'image_sync' => [
        'enabled' => true,
        'disk' => 'public',
        'directory' => 'users/xwms',
        'delete_old' => true,
    ],
]);
```

---

## Sync a logged-in user

```php
use XWMS\Package\Controllers\Api\XwmsApiHelper;

public function syncAccount()
{
    $result = XwmsApiHelper::syncAuthenticatedUserFromXwms();

    if (($result['status'] ?? null) !== 'success') {
        return back()->withErrors('Sync failed');
    }

    return back()->with('success', 'Account synced');
}
```

---

## Why `sub` matters

Emails change. The `sub` never changes.
Always link XWMS users by `sub` using the connection table.

---

## Troubleshooting

- Check that `XWMS_REDIRECT_URI` matches your route exactly.
- Tokens are single use; try again after a failed attempt.
- Do not log client secrets or tokens.

---

## Summary

- Use `XwmsApiHelper::auth()` to redirect to XWMS.
- Use `XwmsApiHelper::authenticateAndSyncUser()` to validate and link users.
- Use `XwmsApiHelper::syncAuthenticatedUserFromXwms()` to refresh profile data.
- Always link by `sub`.
