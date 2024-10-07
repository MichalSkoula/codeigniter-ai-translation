# codeigniter-ai-translation

Translate your CodeIgniter 3/4 language files into any language using the Anthropic Claude REST API.

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

$translator = new MichalSkoula\CodeIgniterAITranslation\Translator(
    'your-api-key',           // Anthropic Claude API key
    'cs',                     // source language (need to match you directory name)
    'en',                     // target language (need to match you directory name; will be created automatically)
    'application/language',   // path to your language files
    3                         // CodeIgniter version (3 - default, 4)
);

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

## Links

Homepage: https://skoula.cz

<a href="https://www.buymeacoffee.com/mskoula"><img src="https://www.buymeacoffee.com/assets/img/guidelines/download-assets-sm-1.svg" height="40"></a>
<a href="https://paypal.me/truehipstercz?country.x=CZ&locale.x=en_US"><img src="https://raw.githubusercontent.com/andreostrovsky/donate-with-paypal/master/blue.svg" height="40"></a>
