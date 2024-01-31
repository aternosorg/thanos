<?php

namespace Aternos\Thanos\Reader;

use Exception;

class CustomCompressionReader extends BufferedReader
{
    protected string $compressionType;

    /**
     * @inheritDoc
     * @throws Exception
     */
    public function __construct($resource, int $offset, int $length)
    {
        parent::__construct($resource, $offset, $length);

        $this->compressionType = $this->readCompressionType();
        throw new Exception("Unsupported custom compression type: " . $this->compressionType);
    }

    /**
     * @return string
     * @throws Exception
     */
    protected function readCompressionType(): string
    {
        $result = "";
        while ($char = $this->readRaw(1)) {
            if (ord($char) !== 0) {
                break;
            }
            $result .= $char;
        }
        return $result;
    }

    /**
     * @inheritDoc
     * @throws Exception
     */
    protected function getRawChunk(int $length): string
    {
        return $this->readRaw($length);
    }
}
