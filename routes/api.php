<?php

use App\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Native\Desktop\Http\Controllers\DispatchEventFromAppController;
use Native\Desktop\Http\Controllers\HealthCheckController;
use Native\Desktop\Http\Controllers\NativeAppBootedController;
use Native\Desktop\Http\Middleware\OptionalNightwatchNever;
use Native\Desktop\Http\Middleware\PreventRegularBrowserAccess;

Route::group(['middleware' => [OptionalNightwatchNever::class, PreventRegularBrowserAccess::class]], function () {
    Route::post('_native/api/booted', NativeAppBootedController::class);
    Route::post('_native/api/events', DispatchEventFromAppController::class);
    Route::get('_native/api/health', HealthCheckController::class);
})->withoutMiddleware(VerifyCsrfToken::class);
