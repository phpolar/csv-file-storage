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

    private const DEFAULT_DATE_FORMAT = DATE_RFC3339;

    private const FILEINFO_SIZE_KEY = "size";

    private const INVALID_VALUE_MSG = "Invalid value. Only objects, scalars, and arrays are allowed.";

    private const MALFORMED_FILE_MSG = "Malformed CSV File.";

    private const MEMORY_STREAM = "php://memory";

    private const READ_MODE = "r";

    private const WRITE_APPEND_MODE = "a";

    private const WRITE_ONLY_MODE = "w";

    private const UNIX_EPOCH = "19700101 000000";

    public function __construct(private string $filename, private ?string $typeClassName = null)
    {
        // set up the write stream first
        $this->setUpWriteStream($filename);
        $this->setUpReadStream($filename);
        parent::__construct();
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
        if ($this->fileSize > 0) {
            fclose($this->writeStream);
            $file = fopen($this->filename, self::WRITE_ONLY_MODE);
            // @codeCoverageIgnoreStart
            if ($file !== false) {
                $this->writeStream = $file;
            }
            // @codeCoverageIgnoreEnd
        }
        foreach ($this->getAll() as $index => $record) {
            switch (true) {
                case is_object($record):
                    $objVars = get_object_vars($record);
                    $this->setUpObject($index, $record, $objVars);
                    $convertedVars = $this->convertObjVars($objVars);
                    fputcsv($this->writeStream, $convertedVars);
                    break;
                case is_scalar($record):
                    fputcsv($this->writeStream, [$record]);
                    break;
                case is_array($record):
                    fputcsv($this->writeStream, $record);
                    break;
                default:
                    throw new DomainException(self::INVALID_VALUE_MSG);
            }
        }
        rewind($this->writeStream);
    }

    /**
     * @param array<string,mixed> $objVars
     * @return array<string,string>
     */
    private function convertObjVars(array $objVars): array
    {
        return array_map(
            static fn (mixed $item) => match (true) {
                $item instanceof DateTimeImmutable => $item->format(self::DEFAULT_DATE_FORMAT),
                is_scalar($item) => (string) $item,
                default => "",
            },
            $objVars,
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
            throw new DomainException(self::MALFORMED_FILE_MSG);
        }
        if ($this->hasObjects() === false) {
            $this->storeLine($this->firstLine);
        }
        while (($line = fgetcsv($this->readStream)) !== false) {
            $this->storeLine($line);
        }
        if (count($this) === 0) {
            throw new DomainException(self::MALFORMED_FILE_MSG);
        }
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
     * @param array<string,mixed> $objVars
     */
    private function setUpObject(int|string $index, object $record, array $objVars): void
    {
        if ($index === 0) {
            $this->typeClassName = get_class($record);
            fputcsv($this->writeStream, array_keys($objVars));
        }
    }

    private function setUpReadStream(string $filename): void
    {
        $readStream = fopen($filename, self::READ_MODE);
        // @codeCoverageIgnoreStart
        if ($readStream !== false) {
            $this->readStream = $readStream;
            rewind($this->readStream);
        }
        // @codeCoverageIgnoreEnd
    }

    private function setUpWriteStream(string $filename): void
    {
        $writeStream = fopen($filename, self::WRITE_APPEND_MODE);
        $this->closeWriteStream = $filename !== self::MEMORY_STREAM;
        if ($writeStream === false) {
            throw new FileNotExistsException($filename);
        }
        $fileInfo = fstat($writeStream);
        // @codeCoverageIgnoreStart
        if ($fileInfo !== false) {
            $this->fileSize = $fileInfo[self::FILEINFO_SIZE_KEY];
        }
        // @codeCoverageIgnoreEnd
        $this->writeStream = $writeStream;
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
                $propType instanceof ReflectionNamedType => match ($propType->getName()) {
                    "bool" => (bool) $propValue,
                    "int" => (int) $propValue,
                    "float" => (float) $propValue,
                    DateTimeImmutable::class => new DateTimeImmutable($propValue ?? "now"), // @codeCoverageIgnore
                    DateTime::class => new DateTime($propValue ?? "now"), // @codeCoverageIgnore
                    default => $propValue,
                },
                $propType instanceof ReflectionUnionType => match (true) {
                    $this->containsType("string", $propType->getTypes()) => (string) $propValue,
                    $this->containsType("int", $propType->getTypes()) => (int) $propValue,
                    $this->containsType("float", $propType->getTypes()) => (float) $propValue,
                    $this->containsType("bool", $propType->getTypes()) => (bool) $propValue,
                    $this->containsType(DateTimeImmutable::class, $propType->getTypes()) =>
                        new DateTimeImmutable($propValue ?? self::UNIX_EPOCH),
                    $this->containsType(DateTime::class, $propType->getTypes()) =>
                        new DateTime($propValue ?? self::UNIX_EPOCH),
                    default => throw new AmbiguousUnionTypeException(),
                },
                default => $propValue,
            };
        }
        $item = new Item($obj);
        $keyVal = method_exists($obj, "getPrimaryKey") === true ? $obj->getPrimaryKey() : ++$this->lineNo;
        $key = new ItemKey($keyVal);
        $this->storeByKey($key, $item);
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
