<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObject;
use Phpolar\Phpolar\Storage\Item;
use Phpolar\Phpolar\Storage\ItemKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;

#[CoversClass(CsvFileStorage::class)]
final class CsvFileStorageTest extends TestCase
{
    protected $stream = "php://memory";

    #[TestDox("Shall save objects to file")]
    public function test1()
    {
        $sut = new CsvFileStorage($this->stream);
        $givenObject = new FakeValueObject();
        $expected = $givenObject;
        $item0 = new Item($givenObject);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->commit();
        $sut->load();
        $stored = $sut->getByKey($key0);
        $this->assertObjectEquals($expected, $stored->bind());
    }

    #[TestDox("Shall save arrays to file")]
    public function test2()
    {
        $sut = new CsvFileStorage($this->stream);
        $expected = ["name" => "eric"];
        $givenData = ["name" => "eric"];
        $item0 = new Item($givenData);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->commit();
        $sut->load();
        $stored = $sut->getByKey($key0)->bind();
        $this->assertSame($expected, $stored);
    }

    #[TestDox("Shall save scalar values to file")]
    public function test3()
    {
        $sut = new CsvFileStorage($this->stream);
        $givenValue = 2 ** 44;
        $expected = $givenValue;
        $item0 = new Item($givenValue);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->commit();
        $sut->load();
        $stored = $sut->getByKey($key0)->bind();
        $this->assertSame($expected, $stored);
    }

    #[TestDox("Shall clear the internal data if no data is on file and load is called")]
    public function test4()
    {
        $sut = new CsvFileStorage($this->stream);
        $givenValue = 2 ** 44;
        $item0 = new Item($givenValue);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->load();
        $shouldBeEmpty = $sut->getAll();
        $this->assertEmpty($shouldBeEmpty);
    }

    #[TestDox("Shall not clear the internal data if data is on file, no headers exist, and load is called")]
    public function test5()
    {
        $sut = new CsvFileStorage("tests/__fakes__/without-headers.csv");
        $sut->load();
        $fromFile = $sut->getAll();
        $this->assertNotEmpty($fromFile);
    }

    #[TestDox("Shall load data if header line has empty values")]
    public function test6()
    {
        $expected = [["some", "empty", "headers"]];
        $sut = new CsvFileStorage("tests/__fakes__/empty-headers.csv");
        $sut->load();
        $fromFile = $sut->getAll();
        $this->assertSame($expected, $fromFile);
    }

    #[TestDox("Shall throw an exception if storage contains object types but file has no headers")]
    public function test7()
    {
        $this->expectException(RuntimeException::class);
        $sut = new CsvFileStorage("tests/__fakes__/empty-headers.csv");
        $givenObject = new FakeValueObject();
        $privateProp = new ReflectionProperty($sut, "containsObjects");
        $privateProp->setAccessible(true);
        $privateProp->setValue($sut, true);
        $item0 = new Item($givenObject);
        $key0 = new ItemKey(0);
        $sut->storeByKey($key0, $item0);
        $sut->load();
    }

    #[TestDox("Shall throw an exception if stream does not exist")]
    public function test8()
    {
        $this->expectException(RuntimeException::class);
        new CsvFileStorage("php://non-existing-stream-handle");
    }
}
