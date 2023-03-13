<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use RuntimeException;

/**
 * Represents when a file should exist but does not.
 */
final class FileNotExistsException extends RuntimeException
{
    public function __construct(string $filename)
    {
        parent::__construct("File or writeStream does not exist. Attempted to open $filename");
    }
}
