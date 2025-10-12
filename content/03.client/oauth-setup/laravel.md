---
title: Laravel Installation
layout: docs
---

## Installation (Laravel)

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
✅ That’s it! You’re now ready to use the XWMS authentication APIs in your Laravel app.



