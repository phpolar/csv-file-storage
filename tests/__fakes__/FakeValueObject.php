<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage\Tests\Fakes;

final class FakeValueObject
{
    public function __construct(
        public string $title = "Add a fake model",
        public string $myInput = "what",
        public int $myInt = 0,
        public bool $myBool = false,
        public ?string $myNull = null,
        public float $myFloat = 1e1,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->title === $other->title &&
            $this->myInput === $other->myInput;
    }
}
