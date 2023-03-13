<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage\Tests\Fakes;

use Generator;
use Traversable;

final class FakeValueObjectWithUnionsError
{
    public Generator|Traversable $confusingUn;
}
