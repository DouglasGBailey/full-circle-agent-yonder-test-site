<?php

namespace App\Http\Controllers;

use App\Models\ChatState;
use App\Services\Chatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The website chat widget's single endpoint. */
class ChatController extends Controller
{
    public function message(Request $request, Chatbot $bot): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:200']]);

        $sessionId = $request->session()->getId();

        $result = $bot->handle($data['message'], $request->user(), ChatState::load($sessionId));
        ChatState::save($sessionId, $result['state']);

        return response()->json([
            'route' => $result['state']['flow'],
            'messages' => $result['messages'],
        ]);
    }
}
