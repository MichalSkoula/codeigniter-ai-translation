# codeigniter-ai-translation

This package allows you to translate your CodeIgniter 3/4 language files to any language using Antrhopic Claude REST API.

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
    'your-api-key',
    'cs', // source language
    'en' // target language,
    'application/language' // path to your language files
);

$result = $translator->translate($file);

echo "Translation process completed." . PHP_EOL;
echo "Total files processed: " . $result->getProcessed() . PHP_EOL;
echo "Total items translated: " . $result->getTranslated() . PHP_EOL;
echo "Total items failed: " . $result->getFailed() . PHP_EOL;
```

## Links

Homepage: https://skoula.cz

<a href="https://www.buymeacoffee.com/mskoula"><img src="https://www.buymeacoffee.com/assets/img/guidelines/download-assets-sm-1.svg" height="40"></a>
<a href="https://paypal.me/truehipstercz?country.x=CZ&locale.x=en_US"><img src="https://raw.githubusercontent.com/andreostrovsky/donate-with-paypal/master/blue.svg" height="40"></a>
