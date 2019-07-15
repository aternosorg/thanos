<?php

namespace Aternos\Thanos;

/**
 * Class Helper
 *
 * @package Aternos\Thanos
 */
class Helper
{

    /**
     * Copy directory recursive
     *
     * @param string $src
     * @param string $dst
     */
    static function copyDirectory(string $src, string $dst): void
    {
        $dir = opendir($src);
        mkdir($dst, 0777, true);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            if (is_dir($src . '/' . $file)) {
                self::copyDirectory($src . '/' . $file, $dst . '/' . $file);
            } else {
                copy($src . '/' . $file, $dst . '/' . $file);
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
        if (substr($path, -1) !== "/") {
            $path .= "/";
        }

        $directory = dir($path);
        while ($file = $directory->read()) {
            if (in_array($file, [".", ".."])) {
                continue;
            }

            $filePath = $path . $file;
            if (is_dir($filePath)) {
                self::removeDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }
        $directory->close();
        rmdir($path);
    }
}
