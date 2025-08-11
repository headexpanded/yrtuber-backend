<?php

use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;


// Fortify routes are automatically registered by the service provider
// Sanctum middleware will be applied to API routes
Route::get("/sanctum/csrf-cookie", [CsrfCookieController::class, "show"]);
