<?php

namespace IO;

use Exception;
use Generator;

class CSVInputStream extends FileInputStream
{

    private $delimiter = ',';
    private $enclosure = '"';
    private $escape = '\\';
    private $maxFieldLength = 0;

    public function __construct(
        string $file,
        string $delimiter = ',',
        string $enclosure = '"',
        string $escape = '\\',
        int $maxFieldLength = 0
    ) {
        parent::__construct($file);
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
        $this->maxFieldLength = $maxFieldLength;
    }

    public function setMaxFieldLength(int $maxFieldLength): void
    {
        $this->maxFieldLength = $maxFieldLength;
    }

    public function readLine(): Generator
    {
        $r = fgetcsv($this->IO, $this->maxFieldLength, $this->delimiter, $this->enclosure, $this->escape);
        if ($r === null) {
            throw new Exception('Fail to parse CSV file: ' . $this->file);
        }
        // yield $r;

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
