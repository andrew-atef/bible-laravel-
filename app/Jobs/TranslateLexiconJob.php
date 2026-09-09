<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Lexicon;
use App\Services\Biblical\GeminiLexiconTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslateLexiconJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        public string $strongId
    ) {}

    public function handle(GeminiLexiconTranslationService $translator): void
    {
        $lexicon = Lexicon::where('strong_id', $this->strongId)->first();

        if ($lexicon === null) {
            return;
        }

        try {
            $translated = $translator->translateLexicon($lexicon);

            $lexicon->update([
                'short_gloss_ar'        => $translated['short_gloss_ar'] ?? null,
                'strongs_definition_ar' => $translated['strongs_definition_ar'] ?? null,
                'kjv_translations_ar'   => $translated['kjv_translations_ar'] ?? null,
                'outline_json_ar'       => $translated['outline_items_ar'] ?? [],
                'translated_at'         => now(),
            ]);

            Log::info("Successfully translated lexicon entry: {$this->strongId}");
        } catch (Throwable $e) {
            Log::error("Failed to translate {$this->strongId}: " . $e->getMessage());
            throw $e;
        }
    }
}
