<?php

declare(strict_types=1);

namespace App\Enum;

enum FrequencyEnum: string
{
    case Daily = 'Daily';
    case Weekly = 'Weekly';
    case Monthly = 'Monthly';
}
