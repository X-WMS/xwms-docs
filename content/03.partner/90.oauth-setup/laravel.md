---
title: Laravel Installation
description: ""
layout: docs
---

# Laravel Installation – XWMS Authentication

This page shows how to install and use the **XWMS Client OAuth** system inside a Laravel project.
We start with a **quick setup**, then a **slow, friendly explanation** (so that even a 10‑year‑old
could follow the idea).

The important change in this version:  
we no longer link users by **email address**, but by the **stable XWMS id** called `sub`.

---

## 🚀 Quick Installation (Laravel)

### Step 1: Install the Package

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

> ✅ Make sure `.env` is **not** committed to Git – it contains secrets.

### Step 4: Add the XWMS Routes

Edit `routes/web.php` and paste this:

```php
use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\Api\XwmsApiHelper;
use App\Http\Controllers\HomeController; // or your own controller name

// XWMS Login API routes
Route::get('/xwms/info', [XwmsApiHelper::class, 'info'])->name('xwms.api.info');
Route::get('/xwms/auth', [XwmsApiHelper::class, 'auth'])->name('xwms.api.auth');
Route::get('/xwms/validateToken', [HomeController::class, 'authValidate'])->name('xwms.api.validateToken');
```

`authValidate` is **your** method where you decide how to link
the XWMS user to a local account.  
Below you’ll see a complete example that uses the **stable `sub` id**.

That’s it for the short version.  
For a full explanation, keep reading.

---

## 🧠 Full Installation – Explained Like You’re 10

### 1. Think of XWMS Like a Friendly Gatekeeper

Imagine your app has a door. XWMS is a **friendly gatekeeper** that checks who’s allowed in.

When users click **“Login with XWMS”**, they:

1. go to the gatekeeper (XWMS)  
2. log in there  
3. come back to your app with a **golden ticket** (a token)

Your Laravel app uses that ticket to ask:  
“Who is this person? What is their id, name, email…?”

---

### 2. Why we don’t link by email anymore

In older examples you may see:

> “Find the user by email, or create a new user with this email.”

This works at first, but it is **not professional**:

- people can change their email address
- sometimes people share an email or lose access
- if email changes, your link between XWMS and your user breaks

XWMS therefore gives every account a **stable id** called `sub`:

- `sub` stands for “subject”  
- it never changes, even if the email or name changes  
- you can think of it as the **number on a library card**

When someone shows their library card, the library does **not**
care which email they have now – the card number is enough.

We will do the same:  
we store the `sub` in a small table and use **that** to link the person.

---

### 3. A tiny connection table (XwmsConnection)

Create a migration for a connection table:

```bash
php artisan make:model XwmsConnection -m
```

Edit the new migration to look something like:

```php
Schema::create('xwms_connections', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('sub')->unique(); // stable XWMS id
    $table->timestamps();
});
```

Then run:

```bash
php artisan migrate
```

Now edit `app/Models/XwmsConnection.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class XwmsConnection extends Model
{
    protected $fillable = ['user_id', 'sub'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function findBySub(string $sub): ?self
    {
        return static::where('sub', $sub)->first();
    }

    public static function connectUser(User $user, string $sub): self
    {
        return static::updateOrCreate(
            ['sub' => $sub],
            ['user_id' => $user->id],
        );
    }
}
```

This model simply says:

- “For this XWMS `sub`, which local user does it belong to?”

---

### 4. Make a Controller for Login

Run this command:

```bash
php artisan make:controller HomeController
```

Now open `app/Http/Controllers/HomeController.php`
and add the `authValidate` method (shortened to the important part here):

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\XwmsConnection;
use Exception;
use XWMS\Package\Controllers\Api\XwmsApiHelper;

class HomeController extends Controller
{
    public function authValidate()
    {
        $response = XwmsApiHelper::getAuthenticateUser();

        if (($response['status'] ?? null) !== 'success') {
            return redirect()->route('auth.login');
        }

        $data = $response['data'] ?? [];
        $sub  = $data['sub'] ?? null;              // stable XWMS id

        if (!$sub) {
            return redirect()->route('auth.login');
        }

        // 1) Try existing XWMS connection
        $connection = XwmsConnection::findBySub($sub);
        if ($connection && $connection->user) {
            // Optional: keep local user data in sync with XWMS
            $this->syncUserFromXwms($connection->user, $data);

            Auth::login($connection->user, true);
            return redirect()->route('home.index');
        }

        // 2) No account yet: create one and link it
        $name    = $data['name'] ?? trim(($data['given_name'] ?? '') . ' ' . ($data['family_name'] ?? ''));
        $email   = $data['email'] ?? null;
        $picture = $data['picture'] ?? null;

        $user = new User();
        $user->name     = $name ?: 'Gebruiker';
        $user->email    = $email;                       // may be null
        $user->img      = $picture;
        $user->password = bcrypt(Str::random(40));
        $user->save();

        XwmsConnection::connectUser($user, $sub);

        Auth::login($user, true);
        return redirect()->route('home.index');
    }

    protected function syncUserFromXwms(User $user, array $data): void
    {
        // Very simple example – adjust to your needs
        $user->name  = $data['name']  ?? $user->name;
        $user->email = $data['email'] ?? $user->email;
        $user->img   = $data['picture'] ?? $user->img;
        $user->save();
    }
}
```

In normal language:

- If we already know this `sub`, we log the user in again.  
- If we don’t know this `sub`, we create a user **once**, store the `sub`,
  and next time we recognise them automatically.

Even if they change email or name in XWMS later, the `sub` stays the same.

---

### 5. Add Routes for the Controller Version

If you prefer to use your own controller for both steps, you can also do:

```php
use App\Http\Controllers\AuthController;

Route::get('/xwms/auth', [AuthController::class, 'redirectToXWMS'])->name('xwms.auth');
Route::get('/xwms/validateToken', [AuthController::class, 'handleXWMSCallback'])->name('xwms.callback');
```

The idea is the same:

- `/xwms/auth` → send the user to XWMS  
- `/xwms/validateToken` → receive the `token`, use `getAuthenticateUser()`,
  read `sub`, and link the user via `XwmsConnection`

---

### 6. Add a Login Button

In your Blade template (for example `resources/views/login.blade.php`):

```html
<a href="{{ route('xwms.api.auth') }}" class="btn btn-primary">
  Login with XWMS
</a>
```

When clicked:

1. The user goes to XWMS.  
2. XWMS verifies them.  
3. XWMS redirects them back with a token.  
4. Your `authValidate` method reads the `sub`, finds or creates a user, and logs them in.

---

### 7. Troubleshooting (If Things Break)

If it doesn’t work:

1. Open `storage/logs/laravel.log`.  
2. Look for messages like `XWMS redirect failed` or `XWMS login failed`.  
3. Double‑check your `.env` values – especially `XWMS_REDIRECT_URI`.  
4. Make sure the callback URL in `.env` matches the route exactly.  

---

### 8. Security Tips

- Never share `.env` or commit it to Git.  
- Use HTTPS in production.  
- Treat tokens like passwords – never log them.  
- Log only safe information (for example status codes and messages).  

---

## Summary (for the grown‑ups)

- XWMS returns a stable user id called `sub`.  
- You create a small `xwms_connections` table that links `sub` to `users.id`.  
- On every login:
  - verify the token with `XwmsApiHelper::getAuthenticateUser()`
  - read `sub`
  - find or create a user, and keep the link in `xwms_connections`

This way, even if a user changes their email, your Laravel app still knows  
exactly which local account belongs to which XWMS account.

