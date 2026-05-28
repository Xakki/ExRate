<?php

declare(strict_types=1);

namespace App\Entity;

use App\Util\BcMath;

trait RateValueTrait
{
    private const int INVERT_SCALE = 10;

    public function getRate(bool $invert = false): string
    {
        if ($invert) {
            return BcMath::div('1', $this->rate, self::INVERT_SCALE);
        }

        return $this->rate;
    }
}
