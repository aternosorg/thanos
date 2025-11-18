<?php

namespace Aternos\Thanos\Tests\World;

use Aternos\IO\Exception\IOException;
use Aternos\Thanos\Exception\FileSystemException;
use Aternos\Thanos\Exception\TaskCreatingException;
use Aternos\Thanos\Task\CopyFileTask;
use Aternos\Thanos\Tests\ThanosTestCase;
use Aternos\Thanos\Util\PathPair;
use Aternos\Thanos\World\ThanosTaskFactory;
use Exception;

class ThanosTaskFactoryTest extends ThanosTestCase
{
    public function testTaskFactory(): void
    {
        $factory = new ThanosTaskFactory((function () {
            yield new CopyFileTask(new PathPair("source1.txt", "destination1.txt"));
            yield new CopyFileTask(new PathPair("source2.txt", "destination2.txt"));
        })());

        $task1 = $factory->createNextTask(null);
        $this->assertInstanceOf(CopyFileTask::class, $task1);
        $task2 = $factory->createNextTask(null);
        $this->assertInstanceOf(CopyFileTask::class, $task2);
        $task3 = $factory->createNextTask(null);
        $this->assertNull($task3);
    }

    public function testHandlesIOException(): void
    {
        $factory = new ThanosTaskFactory((function () {
            throw new IOException("Test IOException");
            yield;
        })());

        $this->expectException(FileSystemException::class);
        $factory->createNextTask(null);
    }

    public function testHandlesOtherException(): void
    {
        $factory = new ThanosTaskFactory((function () {
            throw new Exception("Test Exception");
            yield;
        })());

        $this->expectException(TaskCreatingException::class);
        $factory->createNextTask(null);
    }
}
