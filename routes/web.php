<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Public pages of the Saltmarsh Hotel test site.
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/rooms', [PageController::class, 'rooms'])->name('rooms');
Route::get('/restaurant', [PageController::class, 'restaurant'])->name('restaurant');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::get('/sitemap.xml', [PageController::class, 'sitemap'])->name('sitemap');

// Fixed-rules chatbot (no Artificial Intelligence calls).
Route::post('/chat', [ChatController::class, 'message'])->middleware('throttle:chat')->name('chat');

// Test-account sign-in.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// The change-request channel, board and agent API live in the Full Circle SDLC app
// (https://yonder-full-circle-sdlc.terminalvelocityai.tech), not on this site.
