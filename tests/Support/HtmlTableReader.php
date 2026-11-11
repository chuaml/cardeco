<?php

declare(strict_types=1);

namespace test\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads the <table> HTML produced by HTML\TableDisplayer / HTML\HtmlTable into plain arrays,
 * so tests can assert on cell values instead of brittle markup.
 */
final class HtmlTableReader
{
    /** @var DOMXPath */
    private $xpath;

    public function __construct(string $html)
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->xpath = new DOMXPath($doc);
    }

    /** @return array<int, string> header cell texts in order */
    public function headers(): array
    {
        $cells = [];
        foreach ($this->xpath->query('//thead//th') as $th) {
            $cells[] = trim($th->textContent);
        }

        return $cells;
    }

    /** @return array<int, array<int, string>> body rows as lists of cell texts */
    public function rows(): array
    {
        $rows = [];
        foreach ($this->xpath->query('//tbody/tr') as $tr) {
            $cells = [];
            /** @var DOMElement $tr */
            foreach ($tr->getElementsByTagName('td') as $td) {
                $cells[] = trim($td->textContent);
            }
            $rows[] = $cells;
        }

        return $rows;
    }

    /**
     * Body rows keyed by header name, e.g. [['Item Code' => 'ZZTEST-A', 'Stock' => '5'], ...].
     *
     * @return array<int, array<string, string>>
     */
    public function assocRows(): array
    {
        $headers = $this->headers();
        $result = [];
        foreach ($this->rows() as $cells) {
            $row = [];
            foreach ($headers as $i => $name) {
                $row[$name] = array_key_exists($i, $cells) ? $cells[$i] : null;
            }
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return array<string, array<string, string>> assocRows() indexed by the value of $column
     */
    public function assocRowsBy(string $column): array
    {
        $indexed = [];
        foreach ($this->assocRows() as $row) {
            $indexed[$row[$column]] = $row;
        }

        return $indexed;
    }
}
