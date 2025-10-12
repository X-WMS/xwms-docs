---
title: Laravel Installation
description: ""
layout: docs
---

# Laravel Installation — XWMS Authentication

This page shows how to install and use the **XWMS Client OAuth** system inside a Laravel project.
We’ll start with a **quick setup**, then move to a **detailed, easy-to-understand walkthrough** — explained as if you’re 10 years old.

---

## 🚀 Quick Installation (Laravel)

### Step 1: Install the Package

Run this in your terminal:

```bash
composer require xwms/package
```

### Step 2: Publish the Config File

```bash
php artisan vendor:publish --tag=xwms-config
```

This command creates a file in `config/xwms.php` so you can edit your settings.

### Step 3: Add Your Secrets to `.env`

Put your credentials in the `.env` file so they stay safe:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://your-app.test/xwms/validateToken
```

> ⚠️ Make sure `.env` is **not** committed to Git — it contains secrets.

### Step 4: Add the XWMS Routes

Edit `routes/web.php` and paste this:

```php
use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

// XWMS Login API routes
Route::get('/xwms/info', [XwmsApiHelper::class, 'info'])->name('xwms.api.info');
Route::get('/xwms/auth', [XwmsApiHelper::class, 'auth'])->name('xwms.api.auth');
Route::get('/xwms/validateToken', [XwmsApiHelper::class, 'authValidate'])->name('xwms.api.validateToken');
```

That’s it for the short version! ✅

---

## 📖 Full Installation — Step by Step (Explained Like You’re 10)

Let’s walk through this like we’re teaching a beginner. No worries if this is your first time — we’ll take it slow!

### 🧠 Step 1: Think of XWMS Like a Friendly Gatekeeper

Imagine your app has a door. XWMS is a **friendly gatekeeper** that checks who’s allowed in.
When users click **“Login with XWMS”**, they’re sent to the gatekeeper, who checks their ID. If everything is fine, they’re sent back to your app with a “golden ticket” (a token).

Your Laravel app will use this golden ticket to verify who the user is.

---

### ⚙️ Step 2: Install and Publish

Open your terminal and type:

```bash
composer require xwms/package
php artisan vendor:publish --tag=xwms-config
```

This installs the XWMS library and creates a config file you can edit later in `config/xwms.php`.

---

### 📂 Step 3: Add Secrets in `.env`

Laravel keeps private information in a file called `.env`. Think of it as a **locked treasure chest** 🗝️.

Open `.env` and add your credentials:

```env
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_DOMAIN_ID=your_domain_id_here
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://your-app.test/xwms/validateToken
```

You get these values from your XWMS account. Never share them!

---

### 🧩 Step 4: Make a Controller for Login

Run this command:

```bash
php artisan make:controller AuthController
```

Now open `app/Http/Controllers/AuthController.php` and replace everything with:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

class AuthController extends Controller
{
    // Step 1: Redirect to XWMS login page
    public function redirectToXWMS()
    {
        try {
            return (new XwmsApiHelper)->auth();
        } catch (\Throwable $th) {
            logger()->error('XWMS redirect failed', ['error' => $th->getMessage()]);
            return back()->with('error', 'Login is temporarily unavailable. Please try again later.');
        }
    }

    // Step 2: Handle callback and log the user in
    public function handleXWMSCallback(Request $request)
    {
        try {
            $response = XwmsApiHelper::getAuthenticateUser();

            if (!isset($response['status']) || $response['status'] !== 'success') {
                throw new Exception('Invalid response from XWMS');
            }

            $data = $response['data'];
            $email = $data['email'] ?? null;
            $name = $data['name'] ?? 'New User';

            if ($email && ($data['email_verified'] ?? false)) {
                $user = User::firstOrCreate(
                    ['email' => $email],
                    ['name' => $name, 'password' => bcrypt(Str::random(32))]
                );

                Auth::login($user, true);
                return redirect()->route('dashboard');
            }

            return redirect()->route('login')->with('error', 'Your email is not verified.');
        } catch (\Throwable $th) {
            logger()->error('XWMS login failed', ['error' => $th->getMessage()]);
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }
    }
}
```

This controller does two jobs:

1. Sends users to the XWMS login page.
2. Handles their return, verifies the token, and logs them in.

---

### 🧭 Step 5: Add Routes

Now open `routes/web.php` and add:

```php
use App\Http\Controllers\AuthController;

Route::get('/xwms/auth', [AuthController::class, 'redirectToXWMS'])->name('xwms.auth');
Route::get('/xwms/validateToken', [AuthController::class, 'handleXWMSCallback'])->name('xwms.callback');
```

* `/xwms/auth` sends users to XWMS.
* `/xwms/validateToken` brings them back after logging in.

---

### 💡 Step 6: Add a Login Button

In your Blade template (for example `resources/views/login.blade.php`):

```html
<a href="{{ route('xwms.auth') }}" class="btn btn-primary">Login with XWMS</a>
```

When clicked:

1. The user goes to XWMS.
2. XWMS verifies them.
3. XWMS redirects them back.
4. Laravel logs them in automatically. 🎉

---

### 🧰 Step 7: Troubleshooting (If Things Break 😅)

If it doesn’t work:

1. Open `storage/logs/laravel.log`.
2. Search for messages like `XWMS redirect failed` or `XWMS login failed`.
3. Double-check your `.env` values — especially `XWMS_REDIRECT_URI`.
4. Make sure your callback URL in `.env` matches the route exactly.

---

### 🔒 Security Tips

* Never share `.env` or commit it to Git.
* Use HTTPS in production.
* Handle all tokens securely.
* Log only safe information — not secrets.

---

### 🧩 Summary (For the Grown-ups 😎)

| Step | What It Does                  |
| ---- | ----------------------------- |
| 1    | Install XWMS package          |
| 2    | Publish config                |
| 3    | Add secrets to `.env`         |
| 4    | Create Auth controller        |
| 5    | Add login & callback routes   |
| 6    | Add a login button            |
| 7    | Test and check logs if needed |

With these steps, your Laravel app can safely log users in using **XWMS OAuth**. It’s secure, enterprise‑ready, and integrates smoothly with dashboards or admin panels like **Filament**.

---

**That’s it! 🎉** You’ve connected Laravel with XWMS — the safe and friendly way to let users log in.




