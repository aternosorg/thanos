<?php

namespace Aternos\Thanos\Pattern;

use Aternos\Nbt\Tag\LongTag;
use Aternos\Thanos\Exception\McaExceptionInterface;
use Aternos\Thanos\Exception\McaFileException;
use Aternos\Thanos\World\Chunk;

class InhabitedTimePattern implements ChunkPatternInterface
{
    /**
     * @param int $inhabitedTimeThreshold
     * @param bool $removeUnknownChunks
     */
    public function __construct(
        protected int  $inhabitedTimeThreshold,
        protected bool $removeUnknownChunks,
    )
    {
    }

    /**
     * @inheritDoc
     * @throws McaExceptionInterface
     */
    public function matches(Chunk $chunk): bool
    {
        try {
            $tag = $chunk->findChunkTag(LongTag::class, "InhabitedTime", 8);
        } catch (McaExceptionInterface $e) {
            throw new McaFileException(
                "Failed to read InhabitedTime tag of chunk " .
                "[" . $chunk->getXPos() . ", " . $chunk->getZPos() . "] in region " .
                "[" . $chunk->getRegionXPos() . ", " . $chunk->getRegionZPos() . "]"
                , previous: $e
            );
        }
        if ($tag === null) {
            return !$this->removeUnknownChunks;
        }
        return $tag->getValue() > $this->inhabitedTimeThreshold;
    }
}
