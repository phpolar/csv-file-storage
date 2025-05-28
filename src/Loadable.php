<?php

namespace Phpolar\CsvFileStorage;

/**
 * Supports loading data from the storage context.
 */
interface Loadable
{
    /**
     * Load data from the storage context into memory.
     */
    public function load(): void;
}
