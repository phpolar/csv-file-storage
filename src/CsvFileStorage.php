<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\Storage\AbstractStorage;
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
final class CsvFileStorage extends AbstractStorage implements Countable, Closable, Loadable, Persistable
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

    private int $fileSize = 0;

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

    public function __construct(
        private string $filename,
        private ?string $typeClassName = null,
    ) {
        // set up the write stream first
        $this->setUpWriteStream($filename);
        $this->setUpReadStream($filename);
        parent::__construct(
            new CsvFileStorageLifeCycleHooks($this)
        );
    }

    public function close(): void
    {
        if (is_resource($this->readStream) === true) {
            fclose($this->readStream);
        }
        if ($this->closeWriteStream === true) {
            fsync($this->writeStream);
            fclose($this->writeStream);
        }
    }

    public function persist(): void
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
        foreach ($this->findAll() as $index => $record) {
            switch (true) {
                case is_object($record):
                    $objVars = get_object_vars($record);
                    $this->setUpObject($index, $record, $objVars);
                    $convertedVars = $this->convertObjVars($objVars);
                    fputcsv($this->writeStream, $convertedVars, escape: "\\");
                    break;
                case is_scalar($record):
                    fputcsv($this->writeStream, [$record], escape: "\\");
                    break;
                case is_array($record):
                    /**
                     * @var array<int|string, bool|float|int|string|null>
                     */
                    $arr = $record;
                    fputcsv(
                        $this->writeStream,
                        $arr,
                        escape: "\\"
                    );
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

    public function load(): void
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
        while (($line = fgetcsv($this->readStream, escape: "\\")) !== false) {
            $this->storeLine($line);
        }
        if (count($this) === 0) {
            throw new DomainException(self::MALFORMED_FILE_MSG);
        }
    }

    /**
     * @param string $needle
     * @param ReflectionNamedType[]|\ReflectionIntersectionType[] $types
     */
    private function containsType(string $needle, array $types): bool
    {
        /**  @phan-suppress-next-line PhanPartialTypeMismatchArgument */
        $haystack = array_map(static fn (ReflectionNamedType $type) => $type->getName(), array_filter($types, static fn ($type) => $type instanceof ReflectionNamedType));
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
        $line = fgetcsv($this->readStream, escape: "\\");
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
            fputcsv($this->writeStream, array_keys($objVars), escape: "\\");
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
        $key = method_exists($obj, "getPrimaryKey") === true ? $obj->getPrimaryKey() : ++$this->lineNo;
        $this->save($key, $obj);
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
        $item = array_combine(range(0, count($this->firstLine) - 1), $line);
        $key = ++$this->lineNo;
        $this->save($key, $item);
    }
}
