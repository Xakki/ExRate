<?php

declare(strict_types=1);

namespace App\Util;

trait UrlTemplateTrait
{
    private function prepareUrl(string $template, \DateTimeImmutable $date, string $baseCurrency = '', string $currencies = '', string $apiKey = ''): string
    {
        $url = $template;

        // Replace {date:format}
        $url = preg_replace_callback('/\{date:([^\}]+)\}/', function ($matches) use ($date) {
            return $date->format($matches[1]);
        }, $url);

        // Replace {baseCurrency}
        $url = str_replace('{baseCurrency}', $baseCurrency, $url);

        // Replace {currencies}
        $url = str_replace('{currencies}', $currencies, $url);

        // Replace {apiKey}
        $url = str_replace('{apiKey}', $apiKey, $url);

        return $url;
    }
}
