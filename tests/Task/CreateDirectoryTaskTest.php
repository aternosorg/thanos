<?php

namespace Aternos\Thanos\Tests\Task;

use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Task\CreateDirectoryTask;
use Aternos\Thanos\Tests\ThanosTestCase;

class CreateDirectoryTaskTest extends ThanosTestCase
{
    public function testCreateDirectory(): void
    {
        $directoryPath = static::TEST_DATA . "/new_directory/sub_directory";
        $task = new CreateDirectoryTask($directoryPath);
        $task->run();
        $this->assertDirectoryExists($directoryPath);
    }

    public function testDirectoryCreateError(): void
    {
        $directoryPath = static::TEST_DATA . "/file_instead_of_directory";
        file_put_contents($directoryPath, "This is a file, not a directory");
        $task = new CreateDirectoryTask($directoryPath);
        $this->expectException(FileSystemException::class);
        $task->run();
    }
}
