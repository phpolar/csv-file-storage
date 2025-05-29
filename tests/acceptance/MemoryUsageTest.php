<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

use const Phpolar\Tests\PROJECT_MEMORY_USAGE_THRESHOLD;
use const Phpolar\Tests\PROJECT_MEMORY_USAGE_THRESHOLD_WITHOUT_PRELOADING;

#[CoversNothing]
final class MemoryUsageTest extends TestCase
{
    #[Test]
    #[TestDox("Memory usage for saving data shall be below " . PROJECT_MEMORY_USAGE_THRESHOLD . " bytes")]
    public function shallBeBelowThreshold1()
    {
        // if it were preloaded
        include_once "src/CsvFileStorage.php";
        include_once "src/CsvFileStorageLifeCycleHooks.php";
        include_once "vendor/phpolar/storage/src/Closable.php";
        include_once "vendor/phpolar/storage/src/Loadable.php";
        include_once "vendor/phpolar/storage/src/Persistable.php";
        include_once "vendor/phpolar/storage/src/InitHook.php";
        include_once "vendor/phpolar/storage/src/DestroyHook.php";
        include_once "vendor/phpolar/storage/src/AbstractStorage.php";
        include_once "vendor/phpolar/storage/src/Result.php";
        include_once "vendor/phpolar/storage/src/StorageContext.php";
        $totalUsed = -memory_get_usage();
        $sut = new CsvFileStorage("php://memory");
        $item0 = (object) ["name" => "eric"];
        $key0 = 0;
        $sut->save($key0, $item0);
        $sut->persist();
        $totalUsed += memory_get_usage();
        $this->assertGreaterThan(0, $totalUsed);
        $this->assertLessThanOrEqual((int) PROJECT_MEMORY_USAGE_THRESHOLD, $totalUsed);
    }

    #[Test]
    #[TestDox("Memory usage for saving data (with pre-loading disabled) shall be below " . PROJECT_MEMORY_USAGE_THRESHOLD_WITHOUT_PRELOADING . " bytes")]
    public function shallBeBelowThreshold2()
    {
        $totalUsed = -memory_get_usage();
        $sut = new CsvFileStorage("php://memory");
        $item0 = (object) ["name" => "eric"];
        $key0 = 0;
        $sut->save($key0, $item0);
        $sut->persist();
        $totalUsed += memory_get_usage();
        $this->assertGreaterThan(0, $totalUsed);
        $this->assertLessThanOrEqual((int) PROJECT_MEMORY_USAGE_THRESHOLD_WITHOUT_PRELOADING, $totalUsed);
    }
}
