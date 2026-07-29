# codeigniter-ai-translation

Translate your CodeIgniter 3/4 language files into any language using the Anthropic Claude REST API or OpenAI Chat Completions API.

It will automatically add missing translations (array elements), so you can run it periodically to update your language files. Multi-dimensional arrays are also supported.

## Installation

```bash
composer require michalskoula/codeigniter-ai-translation
```

Requires PHP 8.1+

## Usage:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use MichalSkoula\CodeIgniterAITranslation\Provider;

$translator = new MichalSkoula\CodeIgniterAITranslation\Translator(
    Provider::CLAUDE,         // provider: claude or openai
    'claude-sonnet-4-6',      // model name
    'your-api-key',           // API key for Claude or OpenAI
    'cs',                     // source language (need to match you directory name)
    'en',                     // target language (need to match you directory name; will be created automatically)
    'application/language',   // path to your language files
    3                         // CodeIgniter version (3 - default, 4)
);

// Or change it later
$translator->setProvider(Provider::OPENAI);
$translator->setModel('gpt-4o');

// if $file is null, if will translate all files in the directory
$result = $translator->translate($file);

echo "Translation process completed." . PHP_EOL;
echo "Total files processed: " . $result->getProcessed() . PHP_EOL;
echo "Total items translated: " . $result->getTranslated() . PHP_EOL;
echo "Total items failed: " . $result->getFailed() . PHP_EOL;
if ($result->isError()) {
    echo 'Error: ' . $result->getErrorMessage() . PHP_EOL;
}
```

## Development

```
./vendor/bin/rector
./vendor/bin/ecs --fix
```

## Links

* https://github.com/MichalSkoula/codeigniter-ai-translation
* https://skoula.cz/blog/2024/10/how-to-translate-codeigniter-3/4-language-files-with-ai/
