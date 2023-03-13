<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\Phpolar\Storage\AbstractStorage;
use Phpolar\Phpolar\Storage\Item;
use Phpolar\Phpolar\Storage\ItemKey;
use Countable;
use DateTime;
use DateTimeImmutable;
use DomainException;
use ReflectionNamedType;
use ReflectionObject;
use ReflectionUnionType;

/**
 * Allows for saving data to a CSV file.
 */
final class CsvFileStorage extends AbstractStorage implements Countable
{
    /**
     * Where the data was be persisted to.
     *
     * @var resource $readStream
     */
    private $readStream;

    /**
     * Where the data will be persisted to.
     *
     * @var resource $writeStream
     */
    private $writeStream;

    private int|false $fileSize = 0;

    /**
     * The first line of the CSV file.
     *
     * @var list<?string>
     */
    private array $firstLine;

    private int $lineNo = -1;

    private bool $closeWriteStream = true;

    private const MEMORY_STREAM = "php://memory";

    public function __construct(string $filename, private ?string $typeClassName = null)
    {
        $readMode = $filename === self::MEMORY_STREAM ? "r" : "r+";
        $writeMode = $filename === self::MEMORY_STREAM ? "a+" : "r+";
        $readStream = fopen($filename, $readMode);
        $writeStream = fopen($filename, $writeMode);
        $this->closeWriteStream = $filename !== self::MEMORY_STREAM;
        if ($writeStream === false) {
            throw new FileNotExistsException($filename);
        }
        $fileInfo = fstat($writeStream);
        if ($fileInfo !== false) {
            $this->fileSize = $fileInfo["size"];
        }
        $this->writeStream = $writeStream;
        if ($readStream !== false) {
            $this->readStream = $readStream;
        }
        parent::__construct();
        rewind($this->readStream);
    }

    public function __destruct()
    {
        if (is_resource($this->readStream) === true) {
            fclose($this->readStream);
        }
        if ($this->closeWriteStream === true) {
            fsync($this->writeStream);
            fclose($this->writeStream);
        }
    }

    public function commit(): void
    {
        foreach ($this->getAll() as $record) {
            $this->typeClassName ??= is_object($record) === true ? get_class($record) : null;
            switch (true) {
                case is_object($record):
                    fputcsv($this->writeStream, $this->convertObjVars($record));
                    break;
                case is_scalar($record):
                    fputcsv($this->writeStream, [$record]);
                    break;
                case is_array($record):
                    fputcsv($this->writeStream, $record);
                    break;
                default:
                    throw new DomainException("Invalid value. Only objects, scalars, and arrays are allowed.");
            }
        }
        rewind($this->writeStream);
    }

    /**
     * @return array<int|string, string>
     */
    private function convertObjVars(object $record): array
    {
        return array_map(
            static fn (mixed $item) => match (true) {
                $item instanceof DateTimeImmutable => $item->format(DATE_RSS),
                is_scalar($item) => (string) $item,
                default => "",
            },
            get_object_vars($record),
        );
    }

    /**
     * Get the count of items in storage
     */
    public function count(): int
    {
        return $this->getCount();
    }

    protected function load(): void
    {
        if ($this->fileSize === 0) {
            return;
        }
        $this->setFirstLine();
        if ($this->hasEmptyHeader() === true) {
            throw new DomainException("Malformed CSV file");
        }

        if ($this->hasObjects() === false) {
            $this->storeLine($this->firstLine);
        }
        while (($line = fgetcsv($this->readStream)) !== false) {
            $this->storeLine($line);
        }
        if (count($this) === 0) {
            throw new DomainException("Malformed CSV file");
        }
    }

    private function hasEmptyHeader(): bool
    {
        return count(array_filter($this->firstLine)) === 0;
    }

    private function hasObjects(): bool
    {
        return $this->typeClassName !== null;
    }

    private function setFirstLine(): void
    {
        $line = fgetcsv($this->readStream);
        if ($line !== false) {
            $this->firstLine ??= $line;
        }
    }

    /**
     * @param array<int,non-empty-string> $headers
     * @param list<?string> $line
     */
    private function storeObjLine(array $headers, array $line): void
    {
        // @codeCoverageIgnoreStart
        if ($this->typeClassName === null) {
            return;
        }
        // @codeCoverageIgnoreEnd
        $className = $this->typeClassName;
        $obj = new $className();
        $reflectionObj = new ReflectionObject($obj);
        foreach (array_combine($headers, $line) as $propName => $propValue) {
            $reflectionProp = $reflectionObj->getProperty($propName);
            $propType = $reflectionProp->getType();
            $obj->$propName = match (true) {
                $propType instanceof ReflectionNamedType => match (strtolower($propType->getName())) {
                    "bool" => (bool) $propValue,
                    "int" => (int) $propValue,
                    "float" => (float) $propValue,
                    default => $propValue,
                },
                $propType instanceof ReflectionUnionType => match (true) {
                    $this->containsType("string", $propType->getTypes()) => (string) $propValue,
                    $this->containsType("int", $propType->getTypes()) => (int) $propValue,
                    $this->containsType("float", $propType->getTypes()) => (float) $propValue,
                    $this->containsType("bool", $propType->getTypes()) => (bool) $propValue,
                    $this->containsType(DateTimeImmutable::class, $propType->getTypes()) =>
                        new DateTimeImmutable($propValue ?? "19700101 000000"),
                    $this->containsType(DateTime::class, $propType->getTypes()) =>
                        new DateTime($propValue ?? "19700101 000000"),
                    default => throw new AmbiguousUnionTypeException(),
                },
                default => $propValue,
            };
        }
        $item = new Item($obj);
        $key = new ItemKey(++$this->lineNo);
        $this->storeByKey($key, $item);
    }

    /**
     * @param string $needle
     * @param ReflectionNamedType[] $namedTypes
     */
    private function containsType(string $needle, array $namedTypes): bool
    {
        $haystack = array_map(static fn (ReflectionNamedType $type) => $type->getName(), $namedTypes);
        return in_array($needle, $haystack);
    }

    /**
     * @param list<?string> $line
     */
    private function storeLine(array $line): void
    {
        if ($this->hasObjects() === true) {
            $this->storeObjLine(array_filter($this->firstLine), $line);
            return;
        }
        $item = new Item(array_combine(range(0, count($this->firstLine) - 1), $line));
        $key = new ItemKey(++$this->lineNo);
        $this->storeByKey($key, $item);
    }
}
