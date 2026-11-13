<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum ProcessedStatusEnum: int
{
    case PENDING = 0;
    case SUCCESS = 1;
    case FAILED = 2;
    case DUPLICATE = 3;
}
