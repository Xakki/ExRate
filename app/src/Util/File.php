<?php

namespace App\Util;

class File
{
    public static function renameWithPrefix(string $filePath, string $prefix): string
    {
        $directory = dirname($filePath);
        $filename = basename($filePath);
        $newFilename = $prefix.$filename;

        // Если файл находится в текущей директории (нет слэшей), dirname вернет '.'
        if ('.' !== $directory) {
            $newFilename = $directory.DIRECTORY_SEPARATOR.$newFilename;
        }
        if (!rename($filePath, $newFilename)) {
            throw new \Exception('Failed to rename file: '.$filePath.' -> '.$newFilename);
        }

        return $newFilename;
    }
}
