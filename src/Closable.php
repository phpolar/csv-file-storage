<?php

namespace Phpolar\CsvFileStorage;

/**
 * Provides resource closing functionality.
 */
interface Closable
{
    /**
     * Close resources.
     */
    public function close(): void;
}
