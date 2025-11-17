<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\PathPair;

class CopyFileTask extends ThanosTask
{
    /**
     * @param PathPair $paths
     */
    public function __construct(
        protected PathPair $paths
    )
    {
    }

    /**
     * @inheritDoc
     * @throws FileSystemException
     */
    #[OnChild]
    public function run(): void
    {
        $baseDir = dirname($this->paths->getDestination());
        error_clear_last();
        if (!is_dir($baseDir) && !@mkdir($baseDir, recursive: true)) {
            $error = error_get_last();
            $message = "Unknown error";
            if ($error !== null && isset($error["message"])) {
                $message = $error["message"];
            }
            throw new FileSystemException("Failed to create base directory " . $baseDir . ": " . $message);
        }

        error_clear_last();
        if (!@copy($this->paths->getSource(), $this->paths->getDestination())) {
            $error = error_get_last();
            $message = "Unknown error";
            if ($error !== null && isset($error["message"])) {
                $message = $error["message"];
            }
            throw new FileSystemException("Failed to copy file from " . $this->paths->getSource() . " to " . $this->paths->getDestination() . ": " . $message);
        }
    }
}
