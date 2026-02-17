<?php

declare(strict_types=1);

namespace App\Util;

trait UrlTemplateTrait
{
    private function prepareUrl(string $template, \DateTimeImmutable $date, string $baseCurrency = '', string $currencies = '', string $apiKey = '', ?\DateTimeImmutable $dateStart = null, string $quota = ''): string
    {
        $url = $template;

        // Replace {date:format}
        $url = preg_replace_callback('/\{date:([^\}]+)\}/', function ($matches) use ($date, $quota) {
            return $quota.$date->format($matches[1]).$quota;
        }, $url);

        if ($dateStart) {
            $url = preg_replace_callback('/\{dateStart:([^\}]+)\}/', function ($matches) use ($dateStart, $quota) {
                return $quota.$dateStart->format($matches[1]).$quota;
            }, $url);
        }

        // Replace {baseCurrency}
        $url = str_replace('{baseCurrency}', $baseCurrency, $url);

        // Replace {currencies}
        $url = str_replace('{currencies}', $currencies, $url);

        // Replace {apiKey}
        $url = str_replace('{apiKey}', $apiKey, $url);

        return $url;
    }
}
