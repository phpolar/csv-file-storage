<?php

namespace Phpolar\CsvFileStorage;

use Phpolar\Storage\Closable;
use Phpolar\Storage\Loadable;
use Phpolar\Storage\Persistable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CsvFileStorageLifeCycleHooks::class)]
final class CsvFileStorageLifeCycleHooksTest extends TestCase
{
    #[TestDox("Shall persist data during the destruction lifecycle")]
    public function testa()
    {
        /**
         * @var MockObject&Persistable&Closable&Loadable
         */
        $persistSpy = $this->createMockForIntersectionOfInterfaces([Persistable::class, Closable::class, Loadable::class]);
        $persistSpy->expects($this->once())
            ->method("persist");

        $sut = new CsvFileStorageLifeCycleHooks($persistSpy);
        $sut->onDestroy();
    }

    #[TestDox("Shall close resources during the destruction lifecycle")]
    public function testb()
    {
        /**
         * @var MockObject&Persistable&Closable&Loadable
         */
        $closeSpy = $this->createMockForIntersectionOfInterfaces([Persistable::class, Closable::class, Loadable::class]);
        $closeSpy->expects($this->once())
            ->method("close");

        $sut = new CsvFileStorageLifeCycleHooks($closeSpy);
        $sut->onDestroy();
    }

    #[TestDox("Shall load data during the initialization lifecycle")]
    public function testc()
    {
        /**
         * @var MockObject&Persistable&Closable&Loadable
         */
        $closeSpy = $this->createMockForIntersectionOfInterfaces([Persistable::class, Closable::class, Loadable::class]);
        $closeSpy->expects($this->once())
            ->method("load");

        $sut = new CsvFileStorageLifeCycleHooks($closeSpy);
        $sut->onInit();
    }
}
