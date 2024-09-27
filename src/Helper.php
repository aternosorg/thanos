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
    static function copyDirectory(string $src, string $dst): void
    {
        if (is_link($src) || is_link($dst)) {
            return;
        }

        $dir = opendir($src);
        mkdir($dst, recursive: true);
        while (($file = readdir($dir)) !== false) {
            if ($file === static::CURRENT_DIRECTORY || $file === static::PARENT_DIRECTORY) {
                continue;
            }

            $newSrc = $src . DIRECTORY_SEPARATOR . $file;
            $newDst = $dst . DIRECTORY_SEPARATOR . $file;

            if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                static::copyDirectory($newSrc, $newDst);
            } else {
                if (is_link($newSrc) || is_link($newDst)) {
                    continue;
                }
                copy($newSrc, $newDst);
            }
        }
        closedir($dir);
    }

    /**
     * Remove directory recursive
     *
     * @param string $path
     */
    static function removeDirectory(string $path): void
    {
        if (substr($path, -1) !== DIRECTORY_SEPARATOR) {
            $path .= DIRECTORY_SEPARATOR;
        }

        if (!is_dir($path) || is_link($path)) {
            return;
        }

        $directory = dir($path);
        while ($file = $directory->read()) {
            if (in_array($file, [static::CURRENT_DIRECTORY, static::PARENT_DIRECTORY])) {
                continue;
            }

            $filePath = $path . $file;
            if (is_dir($filePath) && !is_link($filePath)) {
                static::removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        $directory->close();
        rmdir($path);
    }
}
