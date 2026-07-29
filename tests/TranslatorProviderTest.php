<?php

require __DIR__ . '/../vendor/autoload.php';

use MichalSkoula\CodeIgniterAITranslation\Provider;
use MichalSkoula\CodeIgniterAITranslation\Translator;

$translator = new Translator(
    Provider::OPENAI,
    'gpt-4o',
    'test-key',
    'cs',
    'en',
    'application/language',
    3
);

if (! method_exists($translator, 'setProvider')) {
    throw new RuntimeException('Translator should support provider selection.');
}

if (! method_exists($translator, 'setModel')) {
    throw new RuntimeException('Translator should support model selection.');
}

$translator->setProvider('openai');
$translator->setModel('gpt-4o');

echo "provider-selection-ok\n";
