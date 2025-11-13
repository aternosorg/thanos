<?php

namespace Aternos\Thanos\Mca\Entry;

enum CompressionMethod: int
{
    case GZIP = 1;
    case ZLIB = 2;
    case RAW = 3;
    case LZ4 = 4;
    case CUSTOM = 127;
}
