<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

enum Provider: string
{
    case CLAUDE = 'claude';
    case OPENAI = 'openai';

    public static function fromString(string $provider): self
    {
        return match (strtolower(trim($provider))) {
            'anthropic', 'claude' => self::CLAUDE,
            'openai', 'gpt' => self::OPENAI,
            default => throw new \InvalidArgumentException('Unsupported provider: ' . $provider),
        };
    }
}
