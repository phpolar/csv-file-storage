<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

use const Phpolar\Tests\PROJECT_SIZE_THRESHOLD;

#[CoversNothing]
final class ProjectSizeTest extends TestCase
{
    #[Test]
    #[TestDox("Source code total size shall be below " . PROJECT_SIZE_THRESHOLD . " bytes")]
    public function shallBeBelowThreshold()
    {
        $totalSize = mb_strlen(
            implode(
                preg_replace(
                    [
                        // strip comments
                        "/\/\*\*(.*?)\//s",
                        "/^(.*?)\/\/(.*?)$/s",
                    ],
                    "",
                    array_map(
                        file_get_contents(...),
                        glob(getcwd() . SRC_GLOB, GLOB_BRACE),
                    ),
                ),
            )
        );
        $this->assertGreaterThan(0, $totalSize);
        $this->assertLessThanOrEqual((int) PROJECT_SIZE_THRESHOLD, $totalSize);
    }
}
