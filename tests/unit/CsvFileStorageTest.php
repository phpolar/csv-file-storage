<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use DomainException;
use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObject;
use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObjectWithPrimaryKey;
use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObjectWithUnions;
use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObjectWithUnionsError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvFileStorage::class)]
#[CoversClass(FileNotExistsException::class)]
#[CoversClass(AmbiguousUnionTypeException::class)]
final class CsvFileStorageTest extends TestCase
{
    protected $stream = "php://memory";

    /**
     * @var string[]
     */
    protected static array $filenames;

    public static function setUpBeforeClass(): void
    {
        for ($i = 1; $i <= 2; ++$i) {
            self::$filenames[] = sys_get_temp_dir() . DIRECTORY_SEPARATOR .  uniqid();
        }
    }

    public static function tearDownAfterClass(): void
    {
        array_walk(
            self::$filenames,
            static function (string $filename): void {
                file_exists($filename) && unlink($filename);
            },
        );
        self::$filenames = [];
    }

    #[TestDox("Shall save objects to file")]
    public function test1a()
    {
        $sut = new CsvFileStorage($this->stream, FakeValueObject::class);
        $givenObject = new FakeValueObject();
        $expected = $givenObject;
        $item0 = $givenObject;
        $key0 = 0;
        $sut->save($key0, $item0);
        $stored = $sut->find($key0);
        $this->assertObjectEquals($expected, $stored->tryUnwrap());
    }

    #[TestDox("Shall save objects with primary key to file")]
    public function test1b()
    {
        $givenPrimaryKey = uniqid();
        $sut = new CsvFileStorage($this->stream, FakeValueObjectWithPrimaryKey::class);
        $givenObject = (new FakeValueObjectWithPrimaryKey())->withPrimaryKey($givenPrimaryKey);
        $expected = $givenObject;
        $item0 = $givenObject;
        $key0 = $givenPrimaryKey;
        $sut->save($key0, $item0);
        $stored = $sut->find($key0);
        $this->assertObjectEquals($expected, $stored->tryUnwrap());
    }

    #[TestDox("Shall save arrays to file")]
    public function test2()
    {
        $sut = new CsvFileStorage($this->stream);
        $expected = ["name" => "eric"];
        $givenData = ["name" => "eric"];
        $item0 = $givenData;
        $key0 = 0;
        $sut->save($key0, $item0);
        $stored = $sut->find($key0)->tryUnwrap();
        $this->assertSame($expected, $stored);
    }

    #[TestDox("Shall save scalar values to file")]
    public function test3()
    {
        $warningHandler = static fn () => true;
        set_error_handler($warningHandler, E_WARNING);
        $sut = new CsvFileStorage(self::$filenames[0]);
        $givenValue = 2 ** 44;
        $expected = $givenValue;
        $item0 = $givenValue;
        $key0 = 0;
        $sut->save($key0, $item0);
        unset($sut);
        gc_collect_cycles();
        $sut2 = new CsvFileStorage(self::$filenames[0]);
        $stored = $sut2->find($key0)->tryUnwrap();
        $this->assertCount(1, $stored);
        $this->assertEquals($expected, $stored[0]);
        restore_error_handler();
        unset($sut);
    }

    #[TestDox("Shall be empty if no data is on file")]
    public function test4()
    {

        $sut = new CsvFileStorage($this->stream);
        $shouldBeEmpty = $sut->findAll();
        $this->assertEmpty($shouldBeEmpty);
    }

    #[TestDox("Shall not clear the internal data if data is on file, no headers exist, and load is called")]
    public function test5()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/without-headers.csv");
        $fromFile = $sut->findAll();
        $this->assertNotEmpty($fromFile);
    }

    #[TestDox("Shall throw an exception if file has more than one line and header line has empty values")]
    public function test6()
    {
        $this->expectException(DomainException::class);
        new CsvFileStorage(__DIR__ . "/../__fakes__/empty-headers.csv");
    }

    #[TestDox("Shall throw an exception if storage contains object types but file has no headers")]
    public function test7()
    {
        $this->expectException(DomainException::class);
        new CsvFileStorage(__DIR__ . "/../__fakes__/empty-headers.csv", FakeValueObject::class);
    }

    #[TestDox("Shall throw an exception if stream does not exist")]
    #[WithoutErrorHandler]
    public function test8()
    {
        $this->expectException(FileNotExistsException::class);
        @new CsvFileStorage("php://non-existing-stream-handle");
    }

    #[TestDox("Shall load objects from file")]
    public function testaa()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/object.csv", FakeValueObject::class);
        $itemKey = 0;
        $result = $sut->find($itemKey);
        $this->assertInstanceOf(FakeValueObject::class, $result->tryUnwrap());
    }

    #[TestDox("Shall not return existing object when querying for non-existing object")]
    public function testaaa()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/object.csv", FakeValueObject::class);
        $itemKey = 3;
        $result = $sut->find($itemKey)->orElse(static fn() => "not found");
        $this->assertSame("not found", $result->tryUnwrap());
    }

    #[TestDox("Shall load objects with primary key from file")]
    public function testab()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/object-with-pkey.csv", FakeValueObjectWithPrimaryKey::class);
        $primaryKey = "123";
        $itemKey = $primaryKey;
        $item = $sut->find($itemKey)->tryUnwrap();
        $this->assertObjectHasProperty("id", $item);
    }

    #[TestDox("Shall load more than one object from file")]
    public function testb()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/object-2.csv", FakeValueObject::class);
        $this->assertCount(2, $sut);
    }

    #[TestDox("Shall throw an exception if attempting to load object from file with one line")]
    public function testc()
    {
        $this->expectException(DomainException::class);
        new CsvFileStorage(__DIR__ . "/../__fakes__/object-malformed.csv", FakeValueObject::class);
    }

    #[TestDox("Shall not set first line when file is empty")]
    public function testd()
    {
        $sut = new CsvFileStorage($this->stream);
        $this->assertCount(0, $sut);
    }

    #[TestDox("Shall parse into target object having union types")]
    public function teste()
    {
        $sut = new CsvFileStorage(__DIR__ . "/../__fakes__/object-unions.csv", FakeValueObjectWithUnions::class);
        $this->assertContainsOnlyInstancesOf(FakeValueObjectWithUnions::class, $sut->findAll());
    }

    #[TestDox("Shall throw an exception when the union type is ambiguous")]
    public function testf()
    {
        $this->expectException(AmbiguousUnionTypeException::class);
        new CsvFileStorage(__DIR__ . "/../__fakes__/object-unions-malformed.csv", FakeValueObjectWithUnionsError::class);
    }

    #[TestDox("Shall throw an exception when an invalid value is commited")]
    public function testg()
    {
        $this->expectException(DomainException::class);
        $sut = new CsvFileStorage($this->stream);
        $invalid = fopen("php://memory", "r");
        fclose($invalid);
        $key = 0;
        $item = $invalid;
        $sut->save($key, $item);
        unset($sut);
        gc_collect_cycles();
    }

    #[TestDox("Shall create file on init if it does not exist")]
    public function testh()
    {
        $filename = self::$filenames[1];
        $this->assertFileDoesNotExist($filename);
        new CsvFileStorage($filename);
        $this->assertFileExists($filename);
    }
}
