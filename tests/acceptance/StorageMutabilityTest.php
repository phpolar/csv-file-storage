<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\CsvFileStorage\Tests\Fakes\FakeValueObject;
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
        $key = 0;
        $item = PHP_INT_MAX;
        $sutA->save($key, $item);
        unset($sutA);
        gc_collect_cycles();
        $this->assertFileExists($this->filename);
        $sutB = new CsvFileStorage($this->filename);
        $stored = $sutB->find($key)->tryUnwrap();
        $this->assertContains((string) $item, $stored);
    }

    #[Test]
    #[TestDox("Shall add items to storage without removing existing values")]
    public function criterionB()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = 0;
        $item0 = PHP_INT_MAX;
        $sutA->save($key0, $item0);
        unset($sutA);
        gc_collect_cycles();
        $sutB = new CsvFileStorage($this->filename);
        $key1 = 1;
        $item1 = PHP_INT_MIN;
        $sutB->save($key1, $item1);
        unset($sutB);
        gc_collect_cycles();
        $sutC = new CsvFileStorage($this->filename);
        $stored0 = $sutC->find($key0)->tryUnwrap();
        $stored1 = $sutC->find($key1)->tryUnwrap();
        $this->assertContains((string) $item0, $stored0);
        $this->assertContains((string) $item1, $stored1);
    }

    #[Test]
    #[TestDox("Shall remove items from storage without removing other values")]
    public function criterionC()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = 0;
        $item0 = PHP_INT_MAX;
        $sutA->save($key0, $item0);
        $sutA->persist();
        unset($sutA);
        $sutB = new CsvFileStorage($this->filename);
        $key1 = 1;
        $item1 = PHP_INT_MIN;
        $key2 = 2;
        $item2 = PHP_OS;
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->persist();
        unset($sutB);
        $sutC = new CsvFileStorage($this->filename);
        $sutC->remove($key2);
        $sutC->persist();
        unset($sutC);
        $sutD = new CsvFileStorage($this->filename);
        $stored0 = $sutD->find($key0);
        $stored1 = $sutD->find($key1);
        $stored2 = $sutD->find($key2)->orElse(static fn() => "not found");
        $this->assertContains((string) $item0, $stored0->tryUnwrap());
        $this->assertContains((string) $item1, $stored1->tryUnwrap());
        $this->assertSame("not found", $stored2->tryUnwrap());
    }

    #[Test]
    #[TestDox("Shall update items in storage without removing other values")]
    public function criterionD()
    {
        $sutA = new CsvFileStorage($this->filename);
        $key0 = 0;
        $item0 = PHP_INT_MAX;
        $sutA->save($key0, $item0);
        $sutA->persist();
        $sutB = new CsvFileStorage($this->filename);
        $key1 = 1;
        $item1 = PHP_INT_MIN;
        $item2 = PHP_OS;
        $key2 = 2;
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->persist();
        $sutC = new CsvFileStorage($this->filename);
        $item2Updated = PHP_SAPI;
        $sutC->replace($key2, $item2Updated);
        $sutC->persist();
        $sutD = new CsvFileStorage($this->filename);
        $stored0 = $sutD->find($key0);
        $stored1 = $sutD->find($key1);
        $stored2 = $sutD->find($key2);
        $this->assertContains((string) $item0, $stored0->tryUnwrap());
        $this->assertContains((string) $item1, $stored1->tryUnwrap());
        $this->assertContains((string) $item2Updated, $stored2->tryUnwrap());
    }

    #[Test]
    #[TestDox("Shall remove objects from storage without removing other objects")]
    public function criterionE()
    {
        $sutA = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key0 = 0;
        $item0 = new FakeValueObject("fake1");
        $sutA->save($key0, $item0);
        $sutA->persist();
        unset($sutA);
        $sutB = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key1 = 1;
        $item1 = new FakeValueObject("fake2");
        $key2 = 2;
        $item2 = new FakeValueObject("fake3");
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->persist();
        unset($sutB);
        $sutC = new CsvFileStorage($this->filename, FakeValueObject::class);
        $sutC->remove($key2);
        $sutC->persist();
        unset($sutC);
        $sutD = new CsvFileStorage($this->filename, FakeValueObject::class);
        $stored0 = $sutD->find($key0);
        $stored1 = $sutD->find($key1);
        $stored2 = $sutD->find($key2)->orElse(static fn() => "not found");
        $this->assertSame($item0->title, $stored0->tryUnwrap()->title);
        $this->assertSame($item1->title, $stored1->tryUnwrap()->title);
        $this->assertSame("not found", $stored2->tryUnwrap());
    }

    #[Test]
    #[TestDox("Shall update objects in storage without removing other objects")]
    public function criterionF()
    {
        $sutA = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key0 = 0;
        $item0 = new FakeValueObject("fake1");
        $sutA->save($key0, $item0);
        $sutA->persist();
        $sutB = new CsvFileStorage($this->filename, FakeValueObject::class);
        $key1 = 1;
        $item1 = new FakeValueObject("fake2");
        $item2 = new FakeValueObject("fake3");
        $key2 = 2;
        $sutB->save($key1, $item1);
        $sutB->save($key2, $item2);
        $sutB->persist();
        $sutC = new CsvFileStorage($this->filename, FakeValueObject::class);
        $item2Updated = new FakeValueObject("fake3 UPDATED");
        $sutC->replace($key2, $item2Updated);
        $sutC->persist();
        $sutD = new CsvFileStorage($this->filename, FakeValueObject::class);
        $stored0 = $sutD->find($key0);
        $stored1 = $sutD->find($key1);
        $stored2 = $sutD->find($key2);
        $this->assertSame($item0->title, $stored0->tryUnwrap()->title);
        $this->assertSame($item1->title, $stored1->tryUnwrap()->title);
        $this->assertSame($item2Updated->title, $stored2->tryUnwrap()->title);
    }
}
