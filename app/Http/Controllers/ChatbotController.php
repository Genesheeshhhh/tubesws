<?php

namespace App\Http\Controllers;

use App\Services\Chatbot\AiChatbot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatbotController extends Controller
{
    public function __invoke(Request $request, AiChatbot $chatbot): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'context' => ['sometimes', 'array'],
            'context.page' => ['sometimes', 'nullable', 'string', 'max:255'],
            'context.car' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        return response()->json($chatbot->reply(
            $validated['message'],
            $validated['context'] ?? [],
        ));
    }
}
