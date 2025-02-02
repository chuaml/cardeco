<?php

namespace HTML;

use Generator;

class TableDisplayer
{
    private $attribute = '';
    private $theadKeys = null;
    private $isHeadWithId = true;

    private $headData;
    private $bodyData;
    private $footData;

    public function __construct(array $listOfRows = [], string $tableId = null)
    {
        if (array_key_exists(0, $listOfRows) === true) {
            // use default row keys as table head
            $head = array_map(function ($c) {
                return $c;
            }, $listOfRows[0]);
            foreach ($head as $k => $v) {
                $head[$k] = $k;
            }
            $this->setHead($head);
        } else {
            $this->setHead([]);
        }
        if ($tableId !== null) {
            $this->attribute .= 'id=' . $tableId;
        }
        $this->setBody($listOfRows);
    }

    public function getTable(): string
    {
        $table = '<table';
        if (strlen($this->attribute) > 0) {
            $table .= ' ' . $this->attribute;
        }
        $table .= '>';

        foreach ($this->getHead() as $html) {
            $table .= $html;
        }

        foreach ($this->getBody() as $html) {
            $table .= $html;
        }

        foreach ($this->getFoot() as $html) {
            $table .= $html;
        }

        $table .= '</table>';

        return $table;
    }

    public function streamHTML(): Generator
    {
        yield '<table';
        if (strlen($this->attribute) > 0) {
            yield ' ' . $this->attribute;
        }
        yield '>';

        foreach ($this->getHead() as $html) {
            yield $html;
        }

        foreach ($this->getBody() as $html) {
            yield $html;
        }

        foreach ($this->getFoot() as $html) {
            yield $html;
        }

        yield '</table>';
    }

    public function setBody(array $data): void
    {
        $this->bodyData = $data;
    }

    protected function formatCell($value, $ofColumnIndex): string
    {
        if (is_float($value) === true) {
            return '<td>' . number_format($value, 2, '.', ',') . '</td>';
        } else {
            return '<td>' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td>';
        }
    }

    public function getBody(): Generator
    {
        yield '<tbody>';
        if ($this->theadKeys === null) {
            foreach ($this->bodyData as $row) {
                yield '<tr><td>'
                    . implode('</td><td>', $row)
                    . '</td></tr>';
            }
        } else {
            foreach ($this->bodyData as $row) {
                yield '<tr>';
                foreach ($this->theadKeys as $index) {
                    yield $this->formatCell($row[$index], $index);
                }
                yield '</tr>';
            }
        }
        yield '</tbody>';
    }

    public function setHead(?array $data, bool $setId = true): void
    {
        $this->headData =  $data;
        $this->theadKeys = array_keys($this->headData);
        $this->isHeadWithId = $setId;
    }

    public function getHead(): Generator
    {
        if ($this->headData === null) {
            return '<thead></thead>';
        }

        yield '<thead><tr>';

        if ($this->isHeadWithId === true) {
            foreach ($this->headData as $k => $v) {
                yield "<th id=\"{$k}\">{$v}</th>";
            }
        } else {
            foreach ($this->headData as $v) {
                yield "<th>{$v}</th>";
            }
        }

        yield '</tr></thead>';
    }

    public function setFoot(?array $data): void
    {
        $this->footData = $data;
    }

    public function getFoot(): Generator
    {
        if ($this->footData === null) {
            return '';
        }

        yield '<tfoot><tr>';
        foreach ($this->footData as $v) {
            yield  "<td>{$v}</td>";
        }

        return  '</tr></tfoot>';
    }

    public function setAttributes(?string $htmlAttributes): void
    {
        $this->attribute = $htmlAttributes;
    }

    protected function getReNamedHeader(): array
    {
        //key original fieldName, value as new user friendly field name
        return [];
    }
}
