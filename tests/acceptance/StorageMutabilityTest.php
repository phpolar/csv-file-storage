<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObject;
use Phpolar\Phpolar\Storage\Item;
use Phpolar\Phpolar\Storage\ItemKey;
use Phpolar\Phpolar\Storage\ItemNotFound;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class StorageMutabilityTest extends TestCase
{
    protected string $filename;

    protected function setUp(): void
    {
        $this->filename = tempnam(sys_get_temp_dir(), uniqid());
    }

    protected function tearDown(): void
    {
        file_exists($this->filename) && unlink($this->filename);
    }

    #[Test]
    #[TestDox("Shall create persistent storage")]
    public function criterionA()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key = new ItemKey(0);
        $item = new Item(PHP_INT_MAX);
        $sutA->save($key, $item);
        $sutA->commit();
        $this->assertFileExists($this->filename);
        $sutB = new CsvFileStorage($this->filename);
        $stored = $sutB->getOne($key);
        $this->assertContains((string) $item->bind(), $stored->bind());
    }

    #[Test]
    #[TestDox("Shall add items to storage without removing existing values")]
    public function criterionB()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = new ItemKey(0);
        $item0 = new Item(PHP_INT_MAX);
        $sutA->save($key0, $item0);
        $sutA->commit();
        $sutB = new CsvFileStorage($this->filename);
        $key1 = new ItemKey(1);
        $item1 = new Item(PHP_INT_MIN);
        $sutB->save($key1, $item1);
        $sutB->commit();
        $sutC = new CsvFileStorage($this->filename);
        $stored0 = $sutC->getOne($key0);
        $stored1 = $sutC->getOne($key1);
        $this->assertContains((string) $item0->bind(), $stored0->bind());
        $this->assertContains((string) $item1->bind(), $stored1->bind());
    }

    #[Test]
    #[TestDox("Shall remove items from storage without removing other values")]
    public function criterionC()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = new ItemKey(0);
        $item0 = new Item(PHP_INT_MAX);
        $sutA->save($key0, $item0);
        $sutA->commit();
        unset($sutA);
        $sutB = new CsvFileStorage($this->filename);
        $key1 = new ItemKey(1);
        $item1 = new Item(PHP_INT_MIN);
        $key2 = new ItemKey(2);
        $item2 = new Item(PHP_OS);
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->commit();
        unset($sutB);
        $sutC = new CsvFileStorage($this->filename);
        $sutC->remove($key2);
        $sutC->commit();
        unset($sutC);
        $sutD = new CsvFileStorage($this->filename);
        $stored0 = $sutD->getOne($key0);
        $stored1 = $sutD->getOne($key1);
        $stored2 = $sutD->getOne($key2);
        $this->assertContains((string) $item0->bind(), $stored0->bind());
        $this->assertContains((string) $item1->bind(), $stored1->bind());
        $this->assertInstanceOf(ItemNotFound::class, $stored2);
    }

    #[Test]
    #[TestDox("Shall update items in storage without removing other values")]
    public function criterionD()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = new ItemKey(0);
        $item0 = new Item(PHP_INT_MAX);
        $sutA->save($key0, $item0);
        $sutA->commit();
        $sutB = new CsvFileStorage($this->filename);
        $key1 = new ItemKey(1);
        $item1 = new Item(PHP_INT_MIN);
        $item2 = new Item(PHP_OS);
        $key2 = new ItemKey(2);
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->commit();
        $sutC = new CsvFileStorage($this->filename);
        $item2Updated = new Item(PHP_SAPI);
        $sutC->replace($key2, $item2Updated);
        $sutC->commit();
        $sutD = new CsvFileStorage($this->filename);
        $stored0 = $sutD->getOne($key0);
        $stored1 = $sutD->getOne($key1);
        $stored2 = $sutD->getOne($key2);
        $this->assertContains((string) $item0->bind(), $stored0->bind());
        $this->assertContains((string) $item1->bind(), $stored1->bind());
        $this->assertContains((string) $item2Updated->bind(), $stored2->bind());
    }

    #[Test]
    #[TestDox("Shall remove objects from storage without removing other objects")]
    public function criterionE()
    {
        $sutA = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key0 = new ItemKey(0);
        $item0 = new Item(new FakeValueObject("fake1"));
        $sutA->save($key0, $item0);
        $sutA->commit();
        unset($sutA);
        $sutB = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key1 = new ItemKey(1);
        $item1 = new Item(new FakeValueObject("fake2"));
        $key2 = new ItemKey(2);
        $item2 = new Item(new FakeValueObject("fake3"));
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->commit();
        unset($sutB);
        $sutC = new CsvFileStorage($this->filename, FakeValueObject::class);
        $sutC->remove($key2);
        $sutC->commit();
        unset($sutC);
        $sutD = new CsvFileStorage($this->filename, FakeValueObject::class);
        $stored0 = $sutD->getOne($key0);
        $stored1 = $sutD->getOne($key1);
        $stored2 = $sutD->getOne($key2);
        $this->assertSame($item0->bind()->title, $stored0->bind()->title);
        $this->assertSame($item1->bind()->title, $stored1->bind()->title);
        $this->assertInstanceOf(ItemNotFound::class, $stored2);
    }

    #[Test]
    #[TestDox("Shall update objects in storage without removing other objects")]
    public function criterionF()
    {
        $sutA = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key0 = new ItemKey(0);
        $item0 = new Item(new FakeValueObject("fake1"));
        $sutA->save($key0, $item0);
        $sutA->commit();
        $sutB = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key1 = new ItemKey(1);
        $item1 = new Item(new FakeValueObject("fake2"));
        $item2 = new Item(new FakeValueObject("fake3"));
        $key2 = new ItemKey(2);
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->commit();
        $sutC = new CsvFileStorage($this->filename, FakeValueObject::class);
        $item2Updated = new Item(new FakeValueObject("fake3 UPDATED"));
        $sutC->replace($key2, $item2Updated);
        $sutC->commit();
        $sutD = new CsvFileStorage($this->filename, FakeValueObject::class);
        $stored0 = $sutD->getOne($key0);
        $stored1 = $sutD->getOne($key1);
        $stored2 = $sutD->getOne($key2);
        $this->assertSame($item0->bind()->title, $stored0->bind()->title);
        $this->assertSame($item1->bind()->title, $stored1->bind()->title);
        $this->assertSame($item2Updated->bind()->title, $stored2->bind()->title);
    }
}
