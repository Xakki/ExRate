<?php

declare(strict_types=1);

namespace App\Enum;

enum FetchStatusEnum: string
{
    case ALREADY_EXIST = 'exist';
    case NO_MORE = 'no_more';
    case SUCCESS = 'success';
}
