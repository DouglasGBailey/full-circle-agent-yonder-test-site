<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ChannelController;
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

// Simulated Slack channel: anyone reads, signed-in test accounts post and react.
Route::get('/channel', [ChannelController::class, 'index'])->name('channel');
Route::get('/channel/feed', [ChannelController::class, 'feed'])->middleware('throttle:feed')->name('channel.feed');
Route::middleware(['auth', 'throttle:channel'])->group(function () {
    Route::post('/channel/messages', [ChannelController::class, 'post'])->name('channel.post');
    Route::post('/channel/reactions', [ChannelController::class, 'react'])->name('channel.react');
});

// Public request board, written only by Full Circle Agent through the API.
Route::get('/board', [BoardController::class, 'index'])->name('board');
