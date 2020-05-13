<?php

namespace HTML;

class HtmlTableRow extends HtmlObject
{
    private $Cell = [];

    public function __construct(array $HtmlTableCells = [])
    {
        $this->Cell = $HtmlTableCells;
    }

    public function addCell(HtmlTableCell $Cell):void
    {
        $this->Cell[] = $Cell;
    }

    public function setCell(int $colIndex, HtmlTableCell $Cell)
    {
        $this->Cell[$colIndex] = $Cell;
    }

    public function getCell(int $colIndex):HtmlTableCell
    {
        if (\array_key_exists($colIndex, $this->Cell) === false || $this->Cell[$colIndex] === null) {
            throw new \InvalidArgumentException("Cell index {$colIndex} does not exist in this row.");
        }

        return $this->Cell[$colIndex];
    }

    public function toHtmlText():string
    {
        $begin = parent::getAllAttributesString();
        if ($begin === '') {
            $begin = '<tr>';
        } else {
            $begin = '<tr ' . $begin . '>';
        }

        return $begin . \implode('', \array_map(function (IHtml $Html) {
            return $Html->toHtmlText();
        }, $this->Cell)) . '</tr>';
    }
}
