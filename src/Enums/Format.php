<?php

declare(strict_types=1);

namespace Gamma\SDK\Enums;

enum Format: string
{
    case Presentation = 'presentation';
    case Document = 'document';
    case Social = 'social';
}
