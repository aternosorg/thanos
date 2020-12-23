<?php

namespace Aternos\Thanos;

/**
 * Class Helper
 *
 * @package Aternos\Thanos
 */
class Helper
{
    private const CURRENT_DIRECTORY = '.';

    private const PARENT_DIRECTORY = '..';

    /**
     * Copy directory recursive
     *
     * @param string $src
     * @param string $dst
     */
    static function copyDirectory(
        string $src,
        string $dst
    ): void {
        $dir = opendir($src);
        mkdir($dst, 0777, true);
        while (($file = readdir($dir)) !== false) {
            if (
                $file === static::CURRENT_DIRECTORY
                || $file === static::PARENT_DIRECTORY
            ) {
                continue;
            }

            if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                static::copyDirectory(
                    $src . DIRECTORY_SEPARATOR . $file,
                    $dst . DIRECTORY_SEPARATOR . $file
                );
            } else {
                copy(
                    $src . DIRECTORY_SEPARATOR . $file,
                    $dst . DIRECTORY_SEPARATOR . $file
                );
            }
        }
        closedir($dir);
    }

    /**
     * Remove directory recursive
     *
     * @param string $path
     */
    static function removeDirectory(string $path)
    {
        if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }

        $directory = dir($path);
        while ($file = $directory->read()) {
            if (in_array($file, [static::CURRENT_DIRECTORY, static::PARENT_DIRECTORY])) {
                continue;
            }

            $filePath = $path . $file;
            if (is_dir($filePath)) {
                static::removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        $directory->close();
        rmdir($path);
    }
}
