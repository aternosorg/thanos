<?php

namespace Aternos\Thanos\Task;

use Aternos\Taskmaster\Task\OnChild;
use Aternos\Taskmaster\Task\Task;
use Aternos\Thanos\Region\AnvilRegion;
use Exception;

class RegionTask extends Task
{
    public function __construct(
        #[OnChild] protected string $inputFile,
        #[OnChild] protected string $outputFile,
        #[OnChild] protected int    $inhabitedTimeThreshold,
        #[OnChild] protected bool   $removeUnknownChunks,

        /**
         * @var int[][]
         */
        #[OnChild] protected array  $forceLoadedChunks,
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
            if (in_array([$chunk->getGlobalXPos(), $chunk->getGlobalYPos()], $this->forceLoadedChunks, true)) {
                $chunk->save();
                continue;
            }
            $time = $chunk->getInhabitedTime();
            if ($time > $this->inhabitedTimeThreshold || ($time === -1 && !$this->removeUnknownChunks)) {
                $chunk->save();
            } else {
                $removedChunks++;
            }
            $chunk->close();
        }
        $region->save();
        return $removedChunks;
    }
}
