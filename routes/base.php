<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client\Rolexdev\Billing\BillingController;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/api/public/eggs', [\Pterodactyl\Http\Controllers\Api\PublicEggController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('api.public.eggs');

Route::get('/theme/hyperv1', [Base\HyperV1ThemePublicController::class, 'show'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);

Route::get('/language/available', [Base\LanguageController::class, 'available'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);
Route::patch('/language', [Base\LanguageController::class, 'set'])
    ->name('language.set');

Route::get('/referral/{code}', [Pterodactyl\Http\Controllers\Auth\ReferralController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class]);

Route::get('/status', [Base\PublicStatusPageController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('public.status');

Route::get('/public/stats', [Base\PublicStatsController::class, 'index'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->middleware('throttle:30,1')
    ->name('public.stats');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon)).+');

Route::post('/api/public/litepay/webhook', [BillingController::class, 'litepayWebhook'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('api.public.litepay.webhook');

Route::post('/api/public/smepay/webhook', [BillingController::class, 'smepayWebhook'])
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->name('api.public.smepay.webhook');
