<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\AuthController as WebAuth;

// =====================
// PUBLIC WEB ROUTES
// =====================
Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [WebAuth::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuth::class, 'login']);

// =====================
// PROTECTED WEB ROUTES
// =====================
Route::middleware('auth')->group(function () {
    Route::post('/logout', [WebAuth::class, 'logout']);

});
