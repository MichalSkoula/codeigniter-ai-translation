<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class Translator
{
    private readonly string $apiKey;

    private readonly HttpClientInterface $httpClient;

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
     * Wait time in milliseconds between requests to comply with your API rate limit
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

    private string $model = 'claude-haiku-4-5';

    private Provider $provider = Provider::CLAUDE;

    public function __construct(
        Provider|string $provider,
        string $model,
        string $apiKey,
        private readonly string $sourceLang,
        private readonly string $targetLang,
        string $dir,
        private readonly int $version = 3
    ) {
        $this->apiKey = $apiKey;
        $this->httpClient = HttpClient::create();
        $this->model = $model;
        $this->provider = $provider instanceof Provider ? $provider : Provider::fromString($provider);

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
        $errors = [];

        // Process files
        try {
            if ($file !== null && $file !== '') {
                // Translate a specific file
                $result = $this->translateMissingItems();
                $processed = 1;
                $translated += $result['translated'];
                $failed += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
            } else {
                // Process all PHP files in the source directory
                foreach (glob(sprintf('%s/*.php', $this->sourceDir)) as $sourceFile) {
                    $this->file = pathinfo($sourceFile, PATHINFO_FILENAME);
                    $this->file = str_replace('_lang', '', $this->file);
                    $result = $this->translateMissingItems();
                    ++$processed;
                    $translated += $result['translated'];
                    $failed += $result['failed'];
                    $errors = array_merge($errors, $result['errors']);
                }
            }
        } catch (\Exception $exception) {
            return new TranslationResult(error: true, errorMessage: $exception->getMessage(), errors: $errors);
        }

        return new TranslationResult($processed, $translated, $failed, false, '', $errors);
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

    public function setProvider(Provider|string $provider): void
    {
        $this->provider = $provider instanceof Provider ? $provider : Provider::fromString($provider);
    }

    public function setModel(string $model): void
    {
        $this->model = $model;
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

        // Numeric list keys are order-based. If a source list length changes,
        // previously translated entries can shift and produce duplicated tail lines.
        $targetLangArray = $this->_normalizeImplicitListBranches($sourceLangArray, $targetLangArray);

        // Flatten both arrays
        $flatSourceLang = ArrayTrans::flattenArray($sourceLangArray);
        $flatTargetLang = ArrayTrans::flattenArray($targetLangArray);

        $missingItems = [];

        // Find missing items
        foreach ($flatSourceLang as $key => $value) {
            if (! array_key_exists($key, $flatTargetLang)) {
                $missingItems[$key] = $value;
            }
        }

        $translated = 0;
        $failed = 0;
        $errors = [];

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

                $translation = $this->requestTranslation($prompt);

                if ($this->sanitizeResponseOutput) {
                    $translation = $this->_sanitizeResponseText($translation);
                } else {
                    $translation = trim($translation);
                }

                if ($translation === '') {
                    ++$failed;
                    $errors[] = sprintf('Empty translation for file %s, key %s', $this->file ?? 'unknown', $key);
                    continue;
                }

                $flatTargetLang[$key] = $translation;
                ++$translated;
            } catch (\Throwable $exception) {
                ++$failed;
                $errors[] = sprintf('Error in file %s, key %s: %s', $this->file ?? 'unknown', $key, $exception->getMessage());
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
            'errors' => $errors,
        ];
    }

    private function requestTranslation(string $prompt): string
    {
        if ($this->provider === Provider::OPENAI) {
            return $this->requestOpenAITranslation($prompt);
        }

        return $this->requestClaudeTranslation($prompt);
    }

    private function requestClaudeTranslation(string $prompt): string
    {
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

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ],
            'json' => $requestData,
        ]);

        $body = $response->getContent(false);
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException($this->buildApiErrorMessage($data, $response->getStatusCode()));
        }

        $textParts = [];
        foreach ($data['content'] ?? [] as $contentItem) {
            if (($contentItem['type'] ?? '') === 'text') {
                $textParts[] = (string) ($contentItem['text'] ?? '');
            }
        }

        return trim(implode($textParts));
    }

    private function requestOpenAITranslation(string $prompt): string
    {
        $requestData = [
            'model' => $this->model,
            'max_completion_tokens' => 1024,
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

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $requestData,
        ]);

        $body = $response->getContent(false);
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException($this->buildApiErrorMessage($data, $response->getStatusCode()));
        }

        $content = $data['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            return trim(implode(array_map(static fn (array $part): string => $part['text'] ?? '', $content)));
        }

        return trim((string) $content);
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

    private function buildApiErrorMessage(array $data, int $statusCode): string
    {
        $default = 'HTTP/' . $statusCode . ' returned for API request.';

        if (! isset($data['error']) || ! is_array($data['error'])) {
            if (isset($data['message']) && is_string($data['message']) && $data['message'] !== '') {
                return $default . ' ' . $data['message'];
            }
            return $default;
        }

        $error = $data['error'];
        $message = $error['message'] ?? $default;
        $details = [];

        foreach (['type', 'param', 'code'] as $field) {
            if (isset($error[$field]) && (string) $error[$field] !== '') {
                $details[] = $field . ': ' . $error[$field];
            }
        }

        if ($details !== []) {
            $message .= ' (' . implode(', ', $details) . ')';
        }

        return trim($message);
    }

    private function _normalizeImplicitListBranches(array $sourceArray, array $targetArray): array
    {
        foreach ($sourceArray as $key => $sourceValue) {
            if (! is_array($sourceValue) || ! array_key_exists($key, $targetArray) || ! is_array($targetArray[$key])) {
                continue;
            }

            $targetValue = $targetArray[$key];
            if (array_is_list($sourceValue) && (! array_is_list($targetValue) || count($sourceValue) !== count($targetValue))) {
                unset($targetArray[$key]);
                continue;
            }

            $targetArray[$key] = $this->_normalizeImplicitListBranches($sourceValue, $targetValue);
        }

        return $targetArray;
    }
}
