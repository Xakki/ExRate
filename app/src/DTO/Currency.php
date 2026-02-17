<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\FrequencyEnum;
use App\Util\Currencies;
use App\Util\Date;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * TODO: Use this as Currencies response for providers.
 */
class Currency
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 12)]
    #[OA\Property(description: 'Currency code', maxLength: 12, example: Currencies::BRL)]
    public string $code;

    #[Assert\NotBlank]
    #[OA\Property(description: 'Name', example: 'Brazilian Reals')]
    public string $name;

    /**
     * @var string[]
     */
    #[OA\Property(description: 'Countries', example: 'Brazil')]
    public array $countries = [];

    #[OA\Property(description: 'Symbol', example: 'R$')]
    public string $symbol = '';

    #[OA\Property(description: 'Description', example: 'Official currency of Brazil')]
    public string $info = '';

    #[OA\Property(description: 'Update frequency', type: 'string', enum: FrequencyEnum::class, default: FrequencyEnum::Daily)]
    public FrequencyEnum $frequency = FrequencyEnum::Daily;

    #[Assert\Date]
    #[OA\Property(description: 'Observation start date', format: 'date', example: '1999-01-01', nullable: true)]
    public ?string $observationStart = null;

    #[Assert\Date]
    #[OA\Property(description: 'Observation end date', format: 'date', example: '2026-02-06', nullable: true)]
    public ?string $observationEnd = null;

    /**
     * @param string[] $countries
     */
    public function __construct(
        string $code,
        string $name,
        array $countries = [],
        string $symbol = '',
        string $info = '',
        FrequencyEnum $frequency = FrequencyEnum::Daily,
        ?string $observationStart = null,
        ?string $observationEnd = null,
    ) {
        $this->code = $code;
        $this->name = $name;
        $this->countries = $countries;
        $this->symbol = $symbol;
        $this->info = $info;
        $this->frequency = $frequency;
        $this->observationStart = $observationStart;
        $this->observationEnd = $observationEnd;
    }

    public function getObservationStart(): ?\DateTimeImmutable
    {
        return $this->observationStart ? Date::createFromFormat(Date::FORMAT, $this->observationStart) : null;
    }
}
