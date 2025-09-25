<?php

declare(strict_types=1);

namespace Gamma\SDK\Enums;

enum TextMode: string
{
    case Generate = 'generate';
    case Condense = 'condense';
    case Preserve = 'preserve';
}
