<?php

namespace HTML;

class HtmlTable extends HtmlObject
{
    private $header = [];
    private $body = [];
    private $footer = [];

    public function setHeader(int $index, HtmlTableCell $Cell): void
    {
        $this->header[$index] = $Cell;
    }

    public function setBody(int $index, array $RowList): void
    {
        $this->body = $RowList;
    }

    public function setRow(int $index, HtmlTableRow $Row): void
    {
        $this->body[$index] = $Row;
    }

    public function addRow(HtmlTableRow $Row): void
    {
        $this->body[] = $Row;
    }

    public function getRow(int $index): HtmlTableRow
    {
        return $this->body[$index];
    }

    public function setFooter(int $index, HtmlTableCell $Cell): void
    {
        $this->footer[$index] = $Cell;
    }

    public function toHtmlText(): string
    {
        $output = '<table';

        $attr = parent::getAllAttributesString();
        if ($attr !== '') {
            $output .= ' ' . $attr;
        }
        $output .= '>';
        unset($attr);

        if (empty($this->header) === false) {
            $output .= '<thead><tr>';

            $output .= \array_reduce($this->header, function (string $cells, HtmlTableCell $Cell) {
                return $cells . '<th>' . $Cell->getFormattedValue() . '</th>';
            }, '');

            $output .= '</tr></thead>';
        }

        if (empty($this->body) === false) {
            $output .= '<tbody>';

            $output .= \array_reduce($this->body, function (string $rows, HtmlTableRow $Row) {
                return $rows . $Row->toHtmlText();
            }, '');

            $output .= '</tbody>';
        }

        if (empty($this->footer) === false) {
            $output .= '<tfoot><tr>'

                . \array_reduce($this->body, function (string $cells, HtmlTableCell $Cell) {
                    return $cells . '<td>' . $Cell->getFormattedValue() . '</td>';
                }, '')

                . '</tr></tfoot>';
        }

        $output .= '</table>';

        return $output;
    }
}
