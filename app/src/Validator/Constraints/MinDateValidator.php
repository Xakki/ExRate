<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Contract\RateRepositoryInterface;
use App\Enum\ProviderEnum;
use App\Service\ProviderRegistry;
use App\Util\Date;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MinDateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RateRepositoryInterface $exchangeRateRepository,
        private readonly ProviderRegistry $providerRegistry,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MinDate) {
            throw new UnexpectedTypeException($constraint, MinDate::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        try {
            $date = Date::createFromFormat(Date::FORMAT, (string) $value);
        } catch (\App\Exception\BadDateException) {
            // TODO: log notice
            return;
        }

        $provider = null;
        if ($constraint->provider instanceof ProviderEnum) {
            $provider = $this->providerRegistry->get($constraint->provider);
        }

        $minDateEntity = $this->exchangeRateRepository->getMinDate($provider);
        $min = $minDateEntity ?? new \DateTimeImmutable('2009-04-28');

        if ($date < $min->setTime(0, 0)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ date }}', $min->format(Date::FORMAT))
                ->addViolation();
        }
    }
}
