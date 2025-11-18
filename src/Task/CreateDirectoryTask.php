<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Thanos\Exception\FileSystemException;

class CreateDirectoryTask extends ThanosTask
{
    /**
     * @param string $path
     */
    public function __construct(
        protected string $path
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
        error_clear_last();
        if (!@mkdir($this->path, recursive: true) && !is_dir($this->path)) {
            $error = error_get_last();
            $message = "Unknown error";
            if ($error !== null && isset($error["message"])) {
                $message = $error["message"];
            }
            throw new FileSystemException("Failed to create directory at " . $this->path . ": " . $message);
        }
    }
}
