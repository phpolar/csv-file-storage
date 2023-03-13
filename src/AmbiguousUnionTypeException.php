<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use RuntimeException;

/**
 * Represents when the type cannot be confidently selected from the specified union type
 */
final class AmbiguousUnionTypeException extends RuntimeException
{
}
