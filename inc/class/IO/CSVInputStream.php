<?php

namespace IO;

use Exception;
use Generator;

class CSVInputStream extends FileInputStream
{

    private $delimiter = ',';
    private $enclosure = '"';
    private $escape = '\\';

    public function __construct(
        string $file,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\'
    ) {
        parent::__construct($file);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
    }

    public function setMaxFieldLength(int $maxFieldLength): void
    {
        if ($maxFieldLength >= 0) {
            $this->maxFieldLength = $maxFieldLength;
        }
    }

    public function readLine(): Generator
    {
        $r = fgetcsv($this->IO, $this->maxFieldLength, $this->delimiter, $this->enclosure, $this->escape);
        if ($r === null) {
            throw new Exception('Fail to parse CSV file: ' . $this->file);
        }
        yield $r;

        while (true) {
            $r = fgetcsv($this->IO, $this->maxFieldLength, $this->delimiter, $this->enclosure, $this->escape);

            if ($r === false) {
                return;
            }

            yield $r;
        }
    }

    public function readLines(): array
    {
        $list = [];
        foreach ($this->readLine() as $r) {
            $list[] = $r;
        }

        return $list;
    }
}
