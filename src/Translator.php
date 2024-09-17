<?php

declare(strict_types=1);

namespace MichalSkoula\CodeIgniterAITranslation;

use Anthropic;
use Anthropic\Client;
use Anthropic\Exceptions\ErrorException;

class Translator
{
    private readonly Client $client;

    private string $model = 'claude-3-5-sonnet-20240620';

    private ?string $file;

    public function __construct(
        string $apiKey,
        private readonly string $sourceLang,
        private readonly string $targetLang,
        string $dir,
        private readonly int $version = 3,
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

    private function getSourceFile(): string
    {
        $suffix = $this->version == 3 ? '_lang' : '';
        return sprintf('%s/%s%s.php', $this->sourceDir, $this->file, $suffix);
    }

    private function getTargetFile(): string
    {
        $suffix = $this->version == 3 ? '_lang' : '';
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
        if ($this->version == 3) {
            include $sourceFile;
            $sourceLangArray = $lang;
        } else {
            $sourceLangArray = include $sourceFile;
        }

        // Load target language file if it exists, otherwise create an empty array
        $targetLangArray = [];
        if (file_exists($targetFile)) {
            if ($this->version == 3) {
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
            try {
                // Some hardcore AI prompt engeeniring is happening here
                $prompt = 'Translate the following "' . $this->sourceLang . '" language line from e-shop system to "' . $this->targetLang . sprintf('" language. Return only translated text please: %s', $value);

                $response = $this->client->messages()->create([
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                ]);

                $flatTargetLang[$key] = trim($response->content[0]->text);
                ++$translated;
            } catch (ErrorException) {
                ++$failed;
                continue;
            }
        }

        // Unflatten the target language array
        $targetLangArray = ArrayTrans::unflattenArray($flatTargetLang);

        // Write updated target language file
        if ($this->version == 3) {
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
}
