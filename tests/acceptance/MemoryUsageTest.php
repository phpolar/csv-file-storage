<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\Phpolar\Storage\Item;
use Phpolar\Phpolar\Storage\ItemKey;
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
        include_once "vendor/phpolar/phpolar-storage/src/AbstractStorage.php";
        include_once "vendor/phpolar/phpolar-storage/src/Item.php";
        include_once "vendor/phpolar/phpolar-storage/src/ItemKey.php";
        $totalUsed = -memory_get_usage();
        $sut = new CsvFileStorage("php://memory");
        $item0 = new Item((object) ["name" => "eric"]);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->commit();
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
        $item0 = new Item((object) ["name" => "eric"]);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->commit();
        $totalUsed += memory_get_usage();
        $this->assertGreaterThan(0, $totalUsed);
        $this->assertLessThanOrEqual((int) PROJECT_MEMORY_USAGE_THRESHOLD_WITHOUT_PRELOADING, $totalUsed);
    }
}
