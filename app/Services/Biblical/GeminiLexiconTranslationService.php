<?php

declare(strict_types=1);

namespace App\Services\Biblical;

use App\Models\Lexicon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiLexiconTranslationService
{
    private string $apiKey;
    private string $model;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = (string) config('services.gemini.api_key');
        $this->model = (string) config('services.gemini.model', 'gemini-2.5-flash');
        $this->baseUrl = (string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models');

        if ($this->apiKey === '' || $this->apiKey === 'your-gemini-api-key-here') {
            throw new RuntimeException('GEMINI_API_KEY is not configured in services.gemini.api_key');
        }
    }

    /**
     * ترجمة تفاصيل الكلمة المعجمية بالاعتماد على سياقها العبري/اليوناني
     */
    public function translateLexicon(Lexicon $lexicon): array
    {
        $prompt = $this->buildPrompt($lexicon);

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
                'temperature' => 0.1, // دقة متناهية ومنع الاختلاق
            ],
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->timeout(45)
            ->post($url, $payload);

        if ($response->failed()) {
            Log::error("Gemini API Error for {$lexicon->strong_id}: " . $response->body());
            throw new RuntimeException("Gemini API request failed: " . $response->status());
        }

        $json = $response->json();
        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
        
        $decoded = json_decode($rawText, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON response received from Gemini for {$lexicon->strong_id}");
        }

        return $decoded;
    }

    /**
     * بناء البرومبت اللاهوتي الاحترافي
     */
    private function buildPrompt(Lexicon $lexicon): string
    {
        $outlineItems = is_array($lexicon->outline_json) 
            ? json_encode($lexicon->outline_json, JSON_UNESCAPED_UNICODE) 
            : ($lexicon->outline_json ?? '[]');

        return <<<PROMPT
You are a World-Class Biblical Scholar, Semitic Linguist, and Classical Arabic Lexicographer (specializing in biblical Hebrew, Aramaic, Koine Greek, and Smith-Van Dyck theological vocabulary).

Your task is to translate an English Strong's Lexicon entry into authoritative, natural, scholarly Arabic for an Arabic Biblical Computing Platform.

### SOURCE ENTRY:
- Strong ID: {$lexicon->strong_id}
- Original Language: {$lexicon->language}
- Original Word (Lemma): {$lexicon->lemma}
- Transliteration: {$lexicon->transliteration}
- Part of Speech: {$lexicon->part_of_speech}
- English Strong's Definition: {$lexicon->strongs_definition}
- Common English Translations: {$lexicon->kjv_translations}
- Outline of Biblical Usage: {$outlineItems}

### STRICT RULES:
1. **Theological Precision**: Use authentic Arabic biblical terminology (Smith-Van Dyck register). E.g., render theological concepts like "atonement" as كفارة, "firstfruits" as باكورة / أوائل, "strength" as قوة / بأس, "ram" as كبش, etc.
2. **Fallback Rule**: If an English cultural idiom or obscure grammatical term has no precise theological equivalent in Arabic, KEEP the English term or transliteration alongside the explanation. DO NOT invent false meanings.
3. **Clean Output**: Remove any internal artifact like :--, --, or broken semicolons.
4. **Structure Matching**: Maintain the exact structure of the outline items, matching each entry with its Arabic equivalent.
5. **Output Format**: Return strictly a JSON object with the following schema:

```json
{
  "short_gloss_ar": "1-3 primary Arabic meanings separated by commas (e.g., بداية، باكورة، أفضل)",
  "strongs_definition_ar": "Full scholarly translation of Strong's exhaustive explanation in clean Arabic",
  "kjv_translations_ar": "Comma-separated Arabic equivalents corresponding to the common translation tags",
  "outline_items_ar": [
    "Arabic translation of outline item 1",
    "Arabic translation of outline item 2"
  ]
}
PROMPT;
    }
}
