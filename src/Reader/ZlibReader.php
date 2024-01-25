<?php

namespace Aternos\Thanos\Reader;

use Exception;

/**
 * Class ZlibReader
 * Read zlib compressed data from a resource
 *
 * @package Aternos\Thanos\Reader
 */
class ZlibReader extends BufferedReader
{
    /**
     * @var int
     */
    protected int $compression;

    /**
     * @var resource
     */
    protected $inflateContext = null;

    /**
     * ZlibReader constructor.
     *
     * @param $resource
     * @param int $compression
     * @param int $offset
     * @param int $length
     */
    public function __construct(
        $resource,
        int $compression,
        int $offset,
        int $length
    ) {
        parent::__construct($resource, $offset, $length);
        $this->compression = $compression;
        $this->inflateContext = inflate_init($this->compression);
    }

    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        parent::reset();
        $this->inflateContext = inflate_init($this->compression);
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getRawChunk(int $length): string
    {
        fseek($this->resource, $this->resourcePointer);
        $rawData = $this->readRaw($length);

        $uncompressedData = inflate_add(
            $this->inflateContext,
            $rawData
        );
        if($uncompressedData === false) {
            throw new Exception("Failed to inflate input data.");
        }
        return $uncompressedData;
    }
}
