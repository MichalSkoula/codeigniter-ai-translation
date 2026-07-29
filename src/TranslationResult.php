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
        private readonly string $errorMessage = '',
        private readonly array $errors = []
    ) {
    }

    public function isError(): bool
    {
        return $this->error;
    }

    /**
     * A single overall error message for the translation run.
     * This is used when the whole process fails, such as a request error.
     */
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

    /**
     * Detailed per-item errors encountered while translating missing items.
     * Useful when some keys fail but the overall run still completes.
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
