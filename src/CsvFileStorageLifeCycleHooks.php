<?php

namespace Phpolar\CsvFileStorage;

use Phpolar\Storage\LifeCycleHooks;

final class CsvFileStorageLifeCycleHooks implements LifeCycleHooks
{
    public function __construct(private Persistable & Loadable & Closable $storage)
    {
    }

    public function onInit(): void
    {
        $this->storage->load();
    }

    public function onDestroy(): void
    {
        $this->storage->persist();
        $this->storage->close();
    }
}
