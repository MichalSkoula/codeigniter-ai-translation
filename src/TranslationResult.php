<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

class TranslationResult
{
    public function __construct(
        private readonly int $processed = 0,
        private readonly int $translated = 0,
        private readonly int $failed = 0,
        private readonly bool $error = false,
        private readonly string $errorMessage = ''
    ) {
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getTranslated(): int
    {
        return $this->translated;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }
}
