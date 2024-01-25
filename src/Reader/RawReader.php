<?php

namespace Aternos\Thanos\Reader;

use Exception;

class RawReader extends BufferedReader
{

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getRawChunk(int $length): string
    {
        return $this->readRaw($length);
    }
}
