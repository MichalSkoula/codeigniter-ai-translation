<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

use Anthropic;
use Anthropic\Client;
use Anthropic\Exceptions\ErrorException;

class Translator
{
    private readonly Client $client;

    private ?string $file = null;

    /**
     * Should contain three %s placeholders:
     * first argument = source language
     * second argument = target language
     * third argument = text to translate
     */
    private string $prompt = 'Translate the following %s language line to %s language. Keep placeholders, HTML tags, escaped characters and line breaks unchanged. Text to translate: %s';

    /**
     * Strict output contract appended to each prompt.
     */
    private string $strictOutputFormatPrompt = 'Return exactly one XML block in this format: <translation>...</translation>. No other text.';

    /**
     * Wait time in milliseconds between requests to comply with your API rate limit https://console.anthropic.com/settings/limits
     */
    private int $sleepMs = 300;

    /**
     * Sanitize model output before storing to language files.
     */
    private bool $sanitizeResponseOutput = true;

    /**
     * Append strict output format instruction to each prompt.
     */
    private bool $useStrictOutputFormatPrompt = true;

    /**
     * Optional model temperature, null keeps provider default.
     */
    private ?float $temperature = 0;

    public function __construct(
        string $apiKey,
        private readonly string $sourceLang,
        private readonly string $targetLang,
        string $dir,
        private readonly int $version = 3,
        private readonly string $model = 'claude-sonnet-4-6'
    ) {
        $this->client = Anthropic::client($apiKey);
        $this->sourceDir = $dir . '/' . $sourceLang;
        $this->targetDir = $dir . '/' . $targetLang;
    }

    public function translate(?string $file = null): TranslationResult
    {
        $this->file = $file;

        // Ensure the target directory exists
        if (! is_dir($this->targetDir)) {
            mkdir($this->targetDir, 0755, true);
        }

        // Initialize counters
        $processed = 0;
        $translated = 0;
        $failed = 0;

        // Process files
        try {
            if ($file !== null && $file !== '') {
                // Translate a specific file
                $result = $this->translateMissingItems();
                $processed = 1;
                $translated += $result['translated'];
                $failed += $result['failed'];
            } else {
                // Process all PHP files in the source directory
                foreach (glob(sprintf('%s/*.php', $this->sourceDir)) as $sourceFile) {
                    $this->file = pathinfo($sourceFile, PATHINFO_FILENAME);
                    $this->file = str_replace('_lang', '', $this->file);
                    $result = $this->translateMissingItems();
                    ++$processed;
                    $translated += $result['translated'];
                    $failed += $result['failed'];
                }
            }
        } catch (\Exception $exception) {
            return new TranslationResult(error: true, errorMessage: $exception->getMessage());
        }

        return new TranslationResult($processed, $translated, $failed);
    }

    public function setPrompt(string $prompt): void
    {
        // check if text contains three %s placeholders
        if (substr_count($prompt, '%s') !== 3) {
            throw new \InvalidArgumentException('Prompt must contain three %s placeholders (source lang, target lang, text)');
        }
        $this->prompt = $prompt;
    }

    public function setSleepMs(int $sleepMs): void
    {
        $this->sleepMs = $sleepMs;
    }

    public function setSanitizeResponseOutput(bool $sanitizeResponseOutput): void
    {
        $this->sanitizeResponseOutput = $sanitizeResponseOutput;
    }

    public function setTemperature(?float $temperature): void
    {
        if ($temperature !== null && ($temperature < 0 || $temperature > 1)) {
            throw new \InvalidArgumentException('Temperature must be between 0 and 1.');
        }

        $this->temperature = $temperature;
    }

    public function setUseStrictOutputFormatPrompt(bool $useStrictOutputFormatPrompt): void
    {
        $this->useStrictOutputFormatPrompt = $useStrictOutputFormatPrompt;
    }

    public function setStrictOutputFormatPrompt(string $strictOutputFormatPrompt): void
    {
        $this->strictOutputFormatPrompt = trim($strictOutputFormatPrompt);
    }

    private function getSourceFile(): string
    {
        $suffix = $this->version === 3 ? '_lang' : '';
        return sprintf('%s/%s%s.php', $this->sourceDir, $this->file, $suffix);
    }

    private function getTargetFile(): string
    {
        $suffix = $this->version === 3 ? '_lang' : '';
        return sprintf('%s/%s%s.php', $this->targetDir, $this->file, $suffix);
    }

    private function translateMissingItems(): array
    {
        $sourceFile = $this->getSourceFile();
        $targetFile = $this->getTargetFile();

        // Check if source file exists
        if (! file_exists($sourceFile)) {
            throw new \Exception('Source file does not exist: ' . $sourceFile);
        }

        // Load source language file
        $sourceLangArray = [];
        if ($this->version === 3) {
            $lang = []; // Reset $lang variable before including source file
            include $sourceFile;
            $sourceLangArray = $lang;
        } else {
            $sourceLangArray = include $sourceFile;
        }

        // Load target language file if it exists, otherwise create an empty array
        $targetLangArray = [];
        if (file_exists($targetFile)) {
            if ($this->version === 3) {
                $lang = []; // Reset $lang variable before including target file
                include $targetFile;
                $targetLangArray = $lang;
            } else {
                $targetLangArray = include $targetFile;
            }
        }

        // Flatten both arrays
        $flatSourceLang = ArrayTrans::flattenArray($sourceLangArray);
        $flatTargetLang = ArrayTrans::flattenArray($targetLangArray);

        $missingItems = [];

        // Find missing items
        foreach ($flatSourceLang as $key => $value) {
            if (! isset($flatTargetLang[$key])) {
                $missingItems[$key] = $value;
            }
        }

        $translated = 0;
        $failed = 0;

        // Translate missing items
        foreach ($missingItems as $key => $value) {
            // Sleep to comply with API rate limit
            usleep($this->sleepMs * 1000);

            // Try to translate the item
            try {
                $prompt = sprintf($this->prompt, $this->sourceLang, $this->targetLang, $value);
                if ($this->useStrictOutputFormatPrompt && $this->strictOutputFormatPrompt !== '') {
                    $prompt .= ' ' . $this->strictOutputFormatPrompt;
                }

                $requestData = [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ];

                if ($this->temperature !== null) {
                    $requestData['temperature'] = $this->temperature;
                }

                $response = $this->client->messages()->create($requestData);

                $translation = (string) ($response->content[0]->text ?? '');
                if ($this->sanitizeResponseOutput) {
                    $translation = $this->_sanitizeResponseText($translation);
                } else {
                    $translation = trim($translation);
                }

                if ($translation === '') {
                    ++$failed;
                    continue;
                }

                $flatTargetLang[$key] = $translation;
                ++$translated;
            } catch (ErrorException) {
                ++$failed;
                continue;
            }
        }

        // Unflatten the target language array
        $targetLangArray = ArrayTrans::unflattenArray($flatTargetLang);

        // Write updated target language file
        if ($this->version === 3) {
            $content = "<?php\n\n\$lang = " . ArrayTrans::arrayToString($targetLangArray) . ";\n";
        } else {
            $content = "<?php\n\nreturn " . ArrayTrans::arrayToString($targetLangArray) . ";\n";
        }
        file_put_contents($targetFile, $content);

        return [
            'translated' => $translated,
            'failed' => $failed,
        ];
    }

    private function _sanitizeResponseText(string $responseText): string
    {
        $responseText = trim($responseText);
        if ($responseText === '') {
            return '';
        }

        if (preg_match('/<translation>\\s*(.*?)\\s*<\\/translation>/is', $responseText, $match) === 1) {
            $responseText = trim($match[1]);
        }

        if (preg_match('/^```[a-zA-Z0-9_-]*\\s*(.*?)\\s*```$/s', $responseText, $match) === 1) {
            $responseText = trim($match[1]);
        }

        $responseText = preg_replace('/^(?:translation|translated text|result)\\s*:\\s*/i', '', $responseText) ?? $responseText;
        $responseText = preg_replace('/<\\/?translation>/i', '', $responseText) ?? $responseText;

        return trim($responseText);
    }
}
