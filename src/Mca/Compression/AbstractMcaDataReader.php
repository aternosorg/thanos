<?php

namespace Aternos\Thanos\Mca\Compression;

use Aternos\IO\Interfaces\Features\IsEndOfFileInterface;
use Aternos\IO\Interfaces\Features\ReadInterface;
use Aternos\IO\Interfaces\Features\SetPositionInterface;

abstract class AbstractMcaDataReader implements McaDataReaderInterface
{
    /**
     * @param ReadInterface&IsEndOfFileInterface&SetPositionInterface $input
     * @param int $startOffset
     * @param int $length
     */
    public function __construct(
        protected ReadInterface&SetPositionInterface&IsEndOfFileInterface $input,
        protected int                                                     $startOffset,
        protected int                                                     $length,
    )
    {
    }
}
