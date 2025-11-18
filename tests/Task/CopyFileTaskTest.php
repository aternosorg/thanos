<?php

namespace Aternos\Thanos\Tests\Task;

use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Task\CopyFileTask;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\Util\PathPair;
use Exception;
use ReflectionClass;

class CopyFileTaskTest extends ThanosTestCase
{
    public function testCopyFile(): void
    {
        $sourcePath = static::TEST_DATA . "/source.txt";
        $destinationPath = static::TEST_DATA . "/destination.txt";
        file_put_contents($sourcePath, "Test content");
        $task = new CopyFileTask(new PathPair($sourcePath, $destinationPath));
        $task->run();

        $this->assertFileExists($destinationPath);
        $this->assertFileExists($sourcePath);
        $this->assertFileEquals($sourcePath, $destinationPath);
    }

    public function testCopyError(): void
    {
        $sourcePath = static::TEST_DATA . "/source.txt";
        $destinationPath = static::TEST_DATA . "/something/";
        file_put_contents($sourcePath, "Test content");
        mkdir($destinationPath);
        $task = new CopyFileTask(new PathPair($sourcePath, $destinationPath));
        $this->expectException(FileSystemException::class);
        $task->run();
    }

    public function testCreateBaseDirError(): void
    {
        $sourcePath = static::TEST_DATA . "/source.txt";
        $destinationPath = static::TEST_DATA . "/dest/file.txt";
        file_put_contents($sourcePath, "Test content");
        file_put_contents(static::TEST_DATA . "/dest", "This is a file, not a directory");
        $task = new CopyFileTask(new PathPair($sourcePath, $destinationPath));
        $this->expectException(FileSystemException::class);
        $task->run();
    }

    public function testHandleError(): void
    {
        $sourcePath = static::TEST_DATA . "/nonexistent.txt";
        $destinationPath = static::TEST_DATA . "/destination.txt";
        $task = new CopyFileTask(new PathPair($sourcePath, $destinationPath));
        $reflection = new ReflectionClass($task);
        $exception = new Exception("Test exception");
        $reflection->getMethod("handleError")->invoke($task, $exception);
        $this->assertSame($exception, $task->getError());
    }
}
