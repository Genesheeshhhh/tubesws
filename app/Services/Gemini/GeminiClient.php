<?php

namespace App\Services\Gemini;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin client for Gemini's generateContent endpoint.
 *
 * The model is asked for a JSON object only. Parsing and validation happen in
 * AiChatbot, so a malformed or hallucinated response can never reach the
 * catalogue filters unchecked.
 */
class GeminiClient
{
    /**
     * @return array{filters: array<string, mixed>, answer: string}
     */
    public function ask(string $system, string $message): array
    {
        $apiKey = (string) config('gemini.api_key');

        if ($apiKey === '') {
            throw new GeminiException('GEMINI_API_KEY belum diisi.');
        }

        $url = str_replace(
            '{model}',
            (string) config('gemini.model', 'gemini-flash-lite-latest'),
            (string) config('gemini.url'),
        );

        try {
            $response = Http::timeout((int) config('gemini.timeout', 12))
                ->retry(1, 250, throw: false)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $apiKey,
                ])
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [['text' => $system]],
                    ],
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [['text' => $message]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.2,
                        'responseMimeType' => 'application/json',
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new GeminiException('Gemini tidak dapat dihubungi: '.$exception->getMessage());
        }

        if ($response->failed()) {
            throw new GeminiException(
                'Gemini gagal: HTTP '.$response->status()
                .' — '.Str::limit(trim($response->body()), 250)
            );
        }

        $feedback = $response->json('promptFeedback.blockReason');

        if (filled($feedback)) {
            throw new GeminiException('Gemini menolak prompt: '.(string) $feedback);
        }

        $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
        $text = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/i', '', $text) ?? $text;

        $data = json_decode($text, true);

        if (! is_array($data) || ! isset($data['filters']) || ! is_array($data['filters'])) {
            throw new GeminiException('Balasan Gemini bukan JSON yang diharapkan.');
        }

        return [
            'filters' => $data['filters'],
            'answer' => trim((string) ($data['answer'] ?? '')),
        ];
    }
}
