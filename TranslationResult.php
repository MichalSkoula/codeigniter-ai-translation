<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

class TranslationResult
{
    private readonly int $processed;

    private readonly int $translated;

    private readonly int $failed;

    private readonly bool $error;

    private readonly string $errorMessage;

    public function __construct(int $processed = 0, int $translated = 0, int $failed = 0, bool $error = false, string $errorMessage = '')
    {
        $this->processed = $processed;
        $this->translated = $translated;
        $this->failed = $failed;
        $this->error = $error;
        $this->errorMessage = $errorMessage;
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
