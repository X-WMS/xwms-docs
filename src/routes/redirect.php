<?php

use Illuminate\Support\Facades\Route;
use XWMS\Package\Controllers\RedirectController;

// ------------------------------------------------------
// --------- REDIRECTS
// ------------------------------------------------------

// AUTH
Route::get('/xwms/sign/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'sign')->name('sign.page');
Route::get('/xwms/login/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'login')->name('login.page');
Route::get('/xwms/signup/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'signup')->name('signup.page');
Route::get('/xwms/logout/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'logout')->name('logout.page');

Route::get('/xwms/oauth/login/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'oauth-login')->name('oauth.login.page');

// USER
Route::get('/xwms/account/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'account')->name('account.page');

// APPS
Route::get('/xwms/xwms/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'xwms')->name('xwms.page');
Route::get('/xwms/about/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'about')->name('about.page');
Route::get('/xwms/projects/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'projects')->name('projects.page');
Route::get('/xwms/contact/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'contact')->name('contact.page');

Route::get('/xwms/cyberverse/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'cyberverse')->name('cyberverse.page');
Route::get('/xwms/easytunez/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'easytunez')->name('easytunez.page');
Route::get('/xwms/mailfi/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'mailfi')->name('mailfi.page');

// APPS
Route::get('/xwms/twitter/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'twitter')->name('twitter.page');
Route::get('/xwms/tiktok/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'tiktok')->name('tiktok.page');
Route::get('/xwms/facebook/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'facebook')->name('facebook.page');
Route::get('/xwms/instagram/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'instagram')->name('instagram.page');
Route::get('/xwms/youtube/page', [RedirectController::class, 'redirectTo'])->defaults('target', 'youtube')->name('youtube.page');