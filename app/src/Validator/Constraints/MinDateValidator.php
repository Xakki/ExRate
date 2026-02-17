<?php

declare(strict_types=1);

namespace App\Validator\Constraints;

use App\Enum\ProviderEnum;
use App\Repository\ExchangeRateRepository;
use App\Service\ProviderRegistry;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MinDateValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ExchangeRateRepository $exchangeRateRepository,
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

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);
        if (false === $date) {
            return;
        }

        $providerId = null;
        if ($constraint->provider instanceof ProviderEnum) {
            $providerId = $this->providerRegistry->get($constraint->provider)->getId();
        }

        $minDateEntity = $this->exchangeRateRepository->getMinDate($providerId);
        $min = $minDateEntity ?? new \DateTimeImmutable('2009-04-28');

        if ($date < $min->setTime(0, 0)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ date }}', $min->format('Y-m-d'))
                ->addViolation();
        }
    }
}
