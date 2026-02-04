<?php

namespace Aternos\Thanos\World;

use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\IO\System\Directory\Directory;
use Aternos\Taskmaster\Task\TaskFactoryInterface;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\Factory\DimensionPatternFactoryInterface;
use Aternos\Thanos\Pattern\Factory\WorldPatternFactoryInterface;

class World
{
    /**
     * @param string $path
     * @return static
     */
    public static function open(string $path): static
    {
        return new static(new Directory($path));
    }

    /**
     * @param GetPathInterface&DirectoryInterface $source
     */
    public function __construct(
        protected DirectoryInterface&GetPathInterface $source,
    )
    {
    }

    /**
     * @param (ChunkPatternInterface|DimensionPatternFactoryInterface|WorldPatternFactoryInterface)[] $patterns
     * @param GetPathInterface&DirectoryInterface $destination
     * @return TaskFactoryInterface
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getTaskFactory(
        array $patterns,
        DirectoryInterface&GetPathInterface $destination,
    ): TaskFactoryInterface
    {
        foreach ($patterns as &$pattern) {
            if ($pattern instanceof WorldPatternFactoryInterface) {
                $pattern = $pattern->makePattern($this);
            }
        }

        $dimensionTaskGenerator = new DimensionTaskGenerator($patterns);
        return new ThanosTaskFactory($dimensionTaskGenerator->generateTasks($this->source, $destination));
    }

    /**
     * @return GetPathInterface&DirectoryInterface
     */
    public function getSource(): GetPathInterface&DirectoryInterface
    {
        return $this->source;
    }
}
