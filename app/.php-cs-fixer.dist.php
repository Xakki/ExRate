<?php

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/config',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->exclude('var')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PER-CS' => true,
        '@Symfony' => true,
    ])
    ->setCacheFile(__DIR__ .'/var/.php-cs-fixer.cache')
    ->setFinder($finder)
;
