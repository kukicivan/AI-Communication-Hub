<?php

namespace App\Http\Controllers;

class AIService
{
    public function generateThreadSummary(array $thread): string
    {
        $messages = collect($thread['messages']);
        $context = $messages->pluck('content.text')->join(' ');

        // Pripremiti AI prompt
        $prompt = "Sumarizuj ovu email konverzaciju u jednu kratku rečenicu sa preporukom akcije: " . $context;

        // Pozvati AI API (OpenAI/Claude)
        $response = Http::post('AI_API_ENDPOINT', [
            'prompt' => $prompt,
            'max_tokens' => 100
        ]);

        return $response->json()['suggestion'] ?? 'Potrebna je revizija ove komunikacije.';
    }
}
