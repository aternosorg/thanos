<?php

namespace Aternos\Thanos\World;

use Aternos\IO\Exception\IOException;
use Aternos\IO\Interfaces\Features\GetChildrenInterface;
use Aternos\IO\Interfaces\Features\GetPathInterface;
use Aternos\IO\Interfaces\IOElementInterface;
use Aternos\IO\Interfaces\Types\DirectoryInterface;
use Aternos\IO\Interfaces\Types\FileInterface;
use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Pattern\Factory\DimensionPatternFactoryInterface;
use Aternos\Thanos\Task\CopyFileTask;
use Aternos\Thanos\Task\CreateDirectoryTask;
use Aternos\Thanos\Task\ProcessRegionTask;
use Aternos\Thanos\Util\PathPair;
use Generator;

class DimensionTaskGenerator
{
    /**
     * @param (ChunkPatternInterface|DimensionPatternFactoryInterface)[] $patterns
     */
    public function __construct(
        protected array $patterns = [],
    )
    {
    }

    /**
     * @param GetPathInterface&DirectoryInterface $source
     * @param GetPathInterface&DirectoryInterface $target
     * @return Generator
     * @throws IOException
     */
    public function generateTasks(
        DirectoryInterface&GetPathInterface $source,
        DirectoryInterface&GetPathInterface $target
    ): Generator
    {
        yield new CreateDirectoryTask($target->getPath());
        $region = null;
        $entities = null;
        $poi = null;

        /** @var GetPathInterface $child */
        foreach ($this->getChildrenOfType($source, GetPathInterface::class) as $child) {
            $name = $child->getName();
            if ($name === "region" && $child instanceof DirectoryInterface) {
                $region = $child;
                continue;
            } else if ($name === "entities" && $child instanceof DirectoryInterface) {
                $entities = $child;
                continue;
            } else if ($name === "poi" && $child instanceof DirectoryInterface) {
                $poi = $child;
                continue;
            }

            if ($child instanceof FileInterface) {
                yield new CopyFileTask(new PathPair(
                    $child->getPath(),
                    $this->getChildFile($target, $child->getName())->getPath()
                ));
                continue;
            }

            yield from $this->generateTasks(
                $child,
                $this->getChildDirectory($target, $child->getName())
            );
        }

        if ($region !== null || $entities !== null || $poi !== null) {
            yield from $this->generateRegionTasks(
                $source,
                $target,
                $region,
                $entities,
                $poi,
            );
        }
    }

    /**
     * @param GetPathInterface&DirectoryInterface $parent
     * @param string $name
     * @return GetPathInterface&DirectoryInterface
     * @throws IOException
     */
    protected function getChildDirectory(DirectoryInterface&GetPathInterface $parent, string $name): DirectoryInterface&GetPathInterface
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $parent->getChild($name, DirectoryInterface::class, GetPathInterface::class);
    }

    /**
     * @param GetPathInterface&DirectoryInterface $parent
     * @param string $name
     * @return FileInterface&GetPathInterface
     * @throws IOException
     */
    protected function getChildFile(DirectoryInterface&GetPathInterface $parent, string $name): FileInterface&GetPathInterface
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $parent->getChild($name, FileInterface::class, GetPathInterface::class);
    }

    /**
     * @param GetChildrenInterface $parent
     * @param string ...$type
     * @return Generator<IOElementInterface>
     * @throws IOException
     */
    protected function getChildrenOfType(GetChildrenInterface $parent, string ...$type): Generator
    {
        foreach ($parent->getChildren() as $child) {
            foreach ($type as $t) {
                if (!is_a($child, $t)) {
                    continue 2;
                }
            }
            yield $child;
        }
    }

    /**
     * @param GetPathInterface&DirectoryInterface $source
     * @param GetPathInterface&DirectoryInterface $target
     * @param array $excludedFiles
     * @return Generator
     * @throws IOException
     */
    protected function copyFilesInDirectory(
        DirectoryInterface&GetPathInterface $source,
        DirectoryInterface&GetPathInterface $target,
        array $excludedFiles = []
    ): Generator
    {
        /** @var GetPathInterface $child */
        foreach ($this->getChildrenOfType($source, GetPathInterface::class) as $child) {
            if (in_array($child->getName(), $excludedFiles, true)) {
                continue;
            }
            if ($child instanceof FileInterface) {
                yield new CopyFileTask(new PathPair(
                    $child->getPath(),
                    $this->getChildFile($target, $child->getName())->getPath()
                ));
                continue;
            }

            if ($child instanceof DirectoryInterface) {
                $newTarget = $this->getChildDirectory($target, $child->getName());
                yield new CreateDirectoryTask($newTarget->getPath());
                yield from $this->copyFilesInDirectory(
                    $child,
                    $newTarget
                );
            }
        }
    }

    /**
     * @param GetPathInterface&DirectoryInterface $source
     * @param GetPathInterface&DirectoryInterface $target
     * @param (GetPathInterface&DirectoryInterface)|null $region
     * @param (GetPathInterface&DirectoryInterface)|null $entities
     * @param (GetPathInterface&DirectoryInterface)|null $poi
     * @return Generator
     * @throws IOException
     */
    protected function generateRegionTasks(
        DirectoryInterface&GetPathInterface $source,
        DirectoryInterface&GetPathInterface $target,
        (DirectoryInterface&GetPathInterface)|null $region,
        (DirectoryInterface&GetPathInterface)|null $entities,
        (DirectoryInterface&GetPathInterface)|null $poi,
    ): Generator
    {
        $patterns = [];
        foreach ($this->patterns as $pattern) {
            if ($pattern instanceof DimensionPatternFactoryInterface) {
                $patterns[] = $pattern->makePattern($source);
            } else {
                $patterns[] = $pattern;
            }
        }

        $processedRegions = [];
        $processedEntities = [];
        $processedPoi = [];
        if ($region !== null) {
            foreach ($this->getChildrenOfType($region, GetPathInterface::class) as $regionFile) {
                if (!$regionFile instanceof FileInterface ||
                    !preg_match(McaReader::MCA_FILE_PATTERN, $regionFile->getName()) ||
                    $regionFile->getSize() === 0
                ) {
                    continue;
                }

                $processedRegions[] = $regionFile->getName();
                $entitiesFile = $this->getMcaFile($entities, $regionFile->getName());
                if ($entitiesFile !== null) {
                    $processedEntities[] = $entitiesFile->getName();
                }
                $poiFile = $this->getMcaFile($poi, $regionFile->getName());
                if ($poiFile !== null) {
                    $processedPoi[] = $poiFile->getName();
                }
                yield new ProcessRegionTask(
                    $this->getMcaFilePathPair($regionFile, $this->getChildDirectory($target, "region")),
                    $this->getMcaFilePathPair($entitiesFile, $this->getChildDirectory($target, "entities")),
                    $this->getMcaFilePathPair($poiFile, $this->getChildDirectory($target, "poi")),
                    $patterns
                );
            }
        }

        if ($region !== null) {
            yield new CreateDirectoryTask($this->getChildDirectory($target, "region")->getPath());
            yield from $this->copyFilesInDirectory($region, $this->getChildDirectory($target, "region"), $processedRegions);
        }
        if ($entities !== null) {
            yield new CreateDirectoryTask($this->getChildDirectory($target, "entities")->getPath());
            yield from $this->copyFilesInDirectory($entities, $this->getChildDirectory($target, "entities"), $processedEntities);
        }
        if ($poi !== null) {
            yield new CreateDirectoryTask($this->getChildDirectory($target, "poi")->getPath());
            yield from $this->copyFilesInDirectory($poi, $this->getChildDirectory($target, "poi"), $processedPoi);
        }
    }

    /**
     * @param GetPathInterface|null $source
     * @param GetPathInterface&DirectoryInterface $target
     * @return PathPair|null
     * @throws IOException
     */
    protected function getMcaFilePathPair(?GetPathInterface $source, DirectoryInterface&GetPathInterface $target): ?PathPair
    {
        if ($source === null) {
            return null;
        }

        return new PathPair(
            $source->getPath(),
            $this->getChildDirectory($target, $source->getName())->getPath(),
        );
    }

    /**
     * @param (GetPathInterface&DirectoryInterface)|null $directory
     * @param string $filename
     * @return (FileInterface&GetPathInterface)|null
     * @throws IOException
     */
    protected function getMcaFile((DirectoryInterface&GetPathInterface)|null $directory, string $filename): (FileInterface&GetPathInterface)|null
    {
        if ($directory === null) {
            return null;
        }

        $file = $this->getChildFile($directory, $filename);
        if (!$file->exists() || $file->getSize() === 0) {
            return null;
        }

        return $file;
    }
}
