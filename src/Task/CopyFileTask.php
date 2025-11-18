<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Util\PathPair;

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
        if (!@mkdir($baseDir, recursive: true) && !is_dir($baseDir)) {
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
