<?php

namespace Aternos\Thanos\Tests\Pattern;

use Aternos\Thanos\Mca\McaReader;
use Aternos\Thanos\Tests\ThanosTestCase;

class PatternTestCase extends ThanosTestCase
{
    protected McaReader $chunkReader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunkReader = McaReader::open(static::TEST_REGION);
    }
}
