<?php

namespace Phpolar\CsvFileStorage;

use Phpolar\Storage\Closable;
use Phpolar\Storage\DestroyHook;
use Phpolar\Storage\InitHook;
use Phpolar\Storage\Loadable;
use Phpolar\Storage\Persistable;

final class CsvFileStorageLifeCycleHooks implements InitHook, DestroyHook
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
