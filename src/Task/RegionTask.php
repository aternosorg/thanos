<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Taskmaster\Task\Task;
use Aternos\Thanos\Pattern\ChunkPatternInterface;
use Aternos\Thanos\Region\AnvilRegion;
use Exception;

class RegionTask extends Task
{
    public function __construct(
        #[OnChild] protected string $inputFile,
        #[OnChild] protected string $outputFile,

        /**
         * @var ChunkPatternInterface[]
         */
        #[OnChild] protected array $pattern = []
    )
    {
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    #[OnChild] public function run()
    {
        $removedChunks = 0;
        $region = new AnvilRegion($this->inputFile, $this->outputFile);
        foreach ($region->getChunks() as $chunk) {
            foreach ($this->pattern as $pattern) {
                if ($pattern->matches($chunk)) {
                    $chunk->save();
                    $chunk->close();
                    continue 2;
                }
            }
            $removedChunks++;
            $chunk->close();
        }
        $region->save();
        return $removedChunks;
    }
}
