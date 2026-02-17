<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Enum\ProviderEnum;
use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class MinDate extends Constraint
{
    public string $message = 'Date must be greater than or equal to {{ date }}.';

    /**
     * @param array<string, mixed>|null $options
     * @param string[]|null             $groups
     */
    public function __construct(
        public ?ProviderEnum $provider = null,
        ?array $groups = null,
        mixed $payload = null,
        ?array $options = null,
    ) {
        parent::__construct($options ?? [], $groups, $payload);
    }
}
