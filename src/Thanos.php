<?php

namespace Aternos\Thanos;

use Aternos\Thanos\World\WorldInterface;

/**
 * Class Thanos
 * Automatically delete chunks from Minecraft worlds
 *
 * @package Aternos\Thanos
 */
class Thanos{

    /**
     * @var int
     */
    protected $minInhabitedTime = 0;

    /**
     * @param int $minInhabitedTime
     */
    public function setMinInhabitedTime(int $minInhabitedTime): void
    {
        $this->minInhabitedTime = $minInhabitedTime;
    }

    /**
     * @return int $maxInhabitedTime
     */
    public function getMinInhabitedTime(): int
    {
        return $this->minInhabitedTime;
    }

    /**
     * Remove all unused chunks
     *
     * @param WorldInterface $world
     * @return int
     */
    public function snap(WorldInterface $world) : int
    {
        $world->copyFiles();
        $removedChunks = 0;
        foreach ($world as $chunk){
            if($chunk->getInhabitedTime() <= $this->minInhabitedTime){
                $chunk->remove();
                $removedChunks++;
            }
        }
        return $removedChunks;
    }

}
