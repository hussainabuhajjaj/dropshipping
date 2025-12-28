<?php

declare(strict_types=1);

namespace App\Services\AI;

interface TranslationProvider
{
    /**
     * Translate text from source to target language.
     *
     * @throws \Exception
     */
    public function translate(string $text, string $source, string $target): string;
}
