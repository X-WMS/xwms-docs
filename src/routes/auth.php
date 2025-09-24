<?php

use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\LangController;

// ------------------------------------------------------
// --------- LANG
// ------------------------------------------------------

Route::middleware(['visitor', 'locale'])->group(function () {
    Route::get('/set/locale/{locale}', [LangController::class, 'setLocale'])->name('set.locale');
});