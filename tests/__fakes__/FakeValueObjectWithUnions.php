<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage\Tests\Fakes;

use DateTime;
use DateTimeImmutable;

final class FakeValueObjectWithUnions
{
    public string|int $strUn;
    public int|float $intUn;
    public float|bool $floatUn;
    public bool|object $boolUn;
    public DateTime|array $dateTimeUn;
    public DateTimeImmutable|array $dateTimeImmUn;
    public $noType;
}
