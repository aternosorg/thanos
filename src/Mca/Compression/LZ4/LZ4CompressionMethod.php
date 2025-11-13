<?php

namespace Aternos\Thanos\Mca\Compression\LZ4;

enum LZ4CompressionMethod: int
{
    case RAW = 0x10;
    case LZ4 = 0x20;
}
