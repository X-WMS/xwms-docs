---
title: Laravel Installation
layout: docs
---

## Short Installation (Laravel)

Step 1: Install the package via Composer

```bash
composer require xwms/package
```

Step 2: Publish the config file

```bash
php artisan vendor:publish --tag=xwms-config
```

Step 3: Configure your `.env` file
Add your XWMS credentials and settings to `.env`:

```env
XWMS_CLIENT_ID="your_client_id"
XWMS_CLIENT_SECRET="your_secret"
XWMS_REDIRECT_URI="http://example.com/xwms/validateToken"
```

Step 4: Add XWMS API routes
Add the following routes to `routes/web.php` or any other route file:

```php
<?php

use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

// ------------------------------------------------------
// --------- XWMS LOGIN API
// ------------------------------------------------------

Route::get('/xwms/info', [XwmsApiHelper::class, 'info'])->name('xwms.api.info');
Route::get('/xwms/auth', [XwmsApiHelper::class, 'auth'])->name('xwms.api.auth');
Route::get('/xwms/validateToken', [XwmsApiHelper::class, 'authValidate'])->name('xwms.api.validateToken');
```
---
---

## Example: Implementing Login Flow with XWMS in Laravel

This guide explains — step by step — how to integrate the XWMS authentication system into your Laravel app.

This is useful if you're building a login system using [Laravel](https://laravel.com), and you want users to log in via XWMS instead of (or in addition to) your local username/password system.

### 🧠 Step 1: Create the Auth Controller

First, create a controller where the login logic will live.

In your terminal, run:

```bash
php artisan make:controller AuthController
```

Then, open the new `app/Http/Controllers/AuthController.php` file and replace its contents with the following:

```php
<?php

use App\Http\Controllers\Controller
use Illuminate\Support\Facades\Auth
use Illuminate\Support\Str
use Illuminate\Http\Request
use App\Models\User
use Exception
use XWMS\Package\Controllers\Api\XwmsApiHelper

class AuthController extends Controller
{
    /**
     * This method redirects the user to the XWMS login page.
     * It uses the XWMS package to generate a secure login URL.
     */
    public function redirectToXWMS()
    {
        try {
            // Generate redirect URL from XWMS and redirect the user
            return (new XwmsApiHelper)->auth()
        } catch (\Throwable $th) {
            // ❗ Optional: log the error for debugging
            logger()->error('XWMS redirect failed', ['error' => $th->getMessage()])

            // Show a simple message to the user
            return back()->with('error', 'Login is temporarily unavailable. Please try again later.')
        }
    }

    /**
     * This method handles the callback after the user logs in via XWMS.
     * It verifies the token, finds or creates the user, and logs them in.
     */
    public function handleXWMSCallback(Request $request)
    {
        try {
            // Ask XWMS to verify the token and return the authenticated user data
            $response = XwmsApiHelper::getAuthenticateUser()

            // Check if the response is valid and successful
            if (!isset($response['status']) || $response['status'] !== 'success') {
                throw new Exception('Invalid response from XWMS')
            }

            $data = $response['data']
            $email = $data['email'] ?? null
            $name = $data['name'] ?? 'New User'

            // Check if email is present and verified
            if ($email && ($data['email_verified'] ?? false)) {
                // Check if user already exists
                $user = User::where('email', $email)->first()

                if (!$user) {
                    // Create a new user if not found
                    $user = User::create([
                        'email' => $email,
                        'name' => $name,
                        // Set a random password since login is handled by XWMS
                        'password' => bcrypt(Str::random(32)),
                    ])
                }

                // Log the user in
                Auth::login($user, true)

                // ✅ Redirect to your dashboard or homepage
                return redirect()->route('dashboard')
            } else {
                // If email is not verified
                return redirect()->route('login')->with('error', 'Your email address is not verified.')
            }

        } catch (\Throwable $th) {
            // ❗ Log the error to Laravel logs for debugging
            logger()->error('XWMS login failed', ['error' => $th->getMessage()])

            // Show a user-friendly error
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.')
        }
    }
}
```

---

### 🔧 Step 2: Add the Login Routes

Next, open your `routes/web.php` file. This is where we define the URLs your users will visit.

Add the following routes:

```php
Route::get('/xwms/auth', [AuthController::class, 'redirectToXWMS'])->name('xwms.auth')
Route::get('/xwms/validateToken', [AuthController::class, 'handleXWMSCallback'])->name('xwms.callback')
```

- The first route will **redirect the user to XWMS** to log in.
- The second route is the **callback**, where the user is sent **back** to your app after logging in.

---

---

### 📂 Step 3: Set Up Your `.env` File

Make sure your `.env` file has the correct XWMS credentials:

```
XWMS_CLIENT_ID=your_client_id_here  
XWMS_CLIENT_SECRET=your_secret_here  
XWMS_REDIRECT_URI=http://your-app.test/xwms/validateToken
```

> Replace `your-app.test` with your local development URL. And note that the `XWMS_REDIRECT_URI` has to match with your `handleXWMSCallback` route url.

This tells the XWMS package where to return users after they log in.

---

### ✅ Step 4: Add a Login Button to Your App

In your Blade template or Filament login page, you can add a login button like this:

```html
<a href="{{ route('xwms.auth') }}" class="btn btn-primary">Login with XWMS</a>
```

When users click this button:

1. They’ll be sent to XWMS to log in
2. XWMS will verify them and redirect them back
3. Your controller handles the response
4. The user is logged into your app

---

### 🐞 Debugging Tips

If something doesn’t work:

1. Open your Laravel log file: `storage/logs/laravel.log`
2. Look for any `XWMS login failed` or `XWMS redirect failed` messages
3. Make sure your `.env` file is set up correctly
4. Ensure your callback URL is **exactly the same** as in `XWMS_REDIRECT_URI`

---

### 📌 Summary

- You define 2 routes: one to start login, one to handle the response
- You create a controller to handle the full login flow
- You configure your `.env` file with XWMS credentials
- You add a login button to send users through the flow

With this setup, you can authenticate users securely via the XWMS system inside your Laravel application — even if they don’t have a local password.

> This flow is **secure**, **scalable**, and **easy to integrate** with dashboards, admin panels like Filament, or custom UIs.

---



