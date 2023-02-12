<?php

declare(strict_types=1);

namespace Phpolar\CsvFileStorage;

use Phpolar\Phpolar\Storage\AbstractStorage;
use Phpolar\Phpolar\Storage\Item;
use Phpolar\Phpolar\Storage\ItemKey;
use Phpolar\Phpolar\Storage\KeyNotFound;
use RuntimeException;

/**
 * Allows for saving data to a CSV file.
 */
final class CsvFileStorage extends AbstractStorage
{
    /**
     * Where the data will be persisted to.
     *
     * @var resource $stream
     */
    private $stream;

    private bool $containsObjects = false;

    private string $typeClassName;

    public function __construct(string $filename)
    {
        $stream = fopen($filename, "c+");
        if ($stream === false) {
            throw new RuntimeException("File or stream does not exist. Attempted to open $filename");
        }
        $this->stream = $stream;
        parent::__construct();
    }

    public function __destruct()
    {
        fclose($this->stream);
    }

    public function commit(): void
    {
        $data = $this->getAll();
        if (count($data) > 0) {
            $firstRecord = $data[0];
            if (is_object($firstRecord) === true) {
                $this->typeClassName = get_class($firstRecord);
                $firstRecord = get_object_vars($firstRecord);
                $this->containsObjects = true;
            }
            if (is_array($firstRecord) === true) {
                $headers = array_keys($firstRecord);
                fputcsv($this->stream, $headers);
            }
        }
        foreach ($data as $record) {
            if (is_object($record) === true) {
                /**
                 * @var array<string,bool|float|int|string|null> $objAsArray
                 */
                $objAsArray = array_filter(get_object_vars($record), is_scalar(...));
                fputcsv($this->stream, $objAsArray);
            }
            if (is_scalar($record) === true) {
                fputcsv($this->stream, [$record]);
            }
            if (is_array($record) === true) {
                fputcsv($this->stream, $record);
            }
        }
        rewind($this->stream);
    }

    public function load(): void
    {
        $firstLine = fgetcsv($this->stream);
        if ($firstLine === false) {
            $this->clear();
            return;
        }
        $headers = array_values(array_filter($firstLine));
        while (($line = fgetcsv($this->stream)) !== false) {
            $isScalar = count($line) === 1;
            $hasNoHeaders = count($headers) === 0 && count($line) > 0;
            $headers = $hasNoHeaders === true ? range(0, count($line) - 1) : $headers;
            match (true) {
                $isScalar => $this->storeScalarLine($line),
                $hasNoHeaders && $this->containsObjects => throw new RuntimeException("Malformed csv file"),
                $this->containsObjects => $this->storeObjLine($headers, $line),
                default => $this->storeLine($headers, $line),
            };
        }
        $hasOneLine = $this->getCount() === 0 && count($headers) !== 0;
        if ($hasOneLine === true) {
            $indexes = range(0, count($headers) - 1);
            $this->storeLine($indexes, $headers);
        }
        rewind($this->stream);
    }

    /**
     * @param list<?string> $line
     */
    private function storeScalarLine(array $line): void
    {
        $item = new Item($line[0]);
        $key = $this->getOrGenerateKey($item);
        $this->storeByKey($key, $item);
    }

    /**
     * @param array<int|string> $headers
     * @param list<?string> $line
     */
    private function storeObjLine(array $headers, array $line): void
    {
        $className = $this->typeClassName;
        $obj = new $className();
        foreach (array_combine($headers, $line) as $propName => $propValue) {
            $obj->$propName = $propValue;
        }
        $item = new Item($obj);
        $key = $this->getOrGenerateKey($item);
        $this->storeByKey($key, $item);
    }

    /**
     * @param array<int|string> $headers
     * @param list<?string> $line
     */
    private function storeLine(array $headers, array $line): void
    {
        $item = new Item(array_combine($headers, $line));
        $key = $this->getOrGenerateKey($item);
        $this->storeByKey($key, $item);
    }

    private function getOrGenerateKey(Item $item): ItemKey
    {
        $maybeKey = $this->findKey($item);
        return $maybeKey instanceof KeyNotFound ? new ItemKey(uniqid()) : $maybeKey;
    }
}
