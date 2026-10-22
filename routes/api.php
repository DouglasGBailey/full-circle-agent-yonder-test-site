<?php

use App\Http\Controllers\Api\AgentController;
use App\Http\Controllers\Api\SlackController;
use App\Http\Middleware\AgentToken;
use Illuminate\Support\Facades\Route;

// Token-protected API for Full Circle Agent (mock-Slack MCP server and watcher).
Route::middleware([AgentToken::class, 'throttle:api'])->group(function () {
    Route::get('/slack/conversations.history', [SlackController::class, 'history']);
    Route::get('/slack/conversations.replies', [SlackController::class, 'replies']);
    Route::post('/slack/chat.postMessage', [SlackController::class, 'postMessage']);
    Route::post('/slack/reactions.add', [SlackController::class, 'reactionsAdd']);

    Route::get('/agent/pending', [AgentController::class, 'pending']);
    Route::get('/agent/approval', [AgentController::class, 'approval']);
    Route::get('/board/cards', [AgentController::class, 'cards']);
    Route::post('/board/cards', [AgentController::class, 'createCard']);
    Route::patch('/board/cards/{card}', [AgentController::class, 'updateCard']);
    Route::post('/board/cards/{card}/tickets', [AgentController::class, 'proposeTickets']);
});
