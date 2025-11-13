<?php

namespace Aternos\Thanos\Mca\Entry;

use Aternos\Thanos\Exception\McaExceptionInterface;
use Generator;

interface McaEntryInterface
{
    /**
     * @return Generator<string>
     * @throws McaExceptionInterface
     */
    public function getData(): Generator;

    /**
     * @return Generator<string>
     * @throws McaExceptionInterface
     */
    public function getSerializedData(): Generator;

    /**
     * @return int
     */
    public function getModifiedTime(): int;

    /**
     * @return int
     */
    public function getRegionIndex(): int;

    /**
     * @return int
     */
    public function getXPos(): int;

    /**
     * @return int
     */
    public function getZPos(): int;
}
