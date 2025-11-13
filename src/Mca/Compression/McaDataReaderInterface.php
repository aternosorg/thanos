<?php

namespace Aternos\Thanos\Mca\Compression;

use Aternos\Thanos\Exception\McaExceptionInterface;
use Generator;

interface McaDataReaderInterface
{
    /**
     * @return Generator<string>
     * @throws McaExceptionInterface
     */
    public function read(): Generator;
}
