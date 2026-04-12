<?php

namespace HTML;

class HtmlTable extends HtmlObject
{
    private $header = [];
    private $body = [];
    private $footer = [];
    public function addHeader(string $displayName): HtmlTableCell
    {
        $Cell = new HtmlTableCell($displayName);
        $this->header[] = $Cell;
        return $Cell;
    }
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

    public function streamHtmlText(): \Generator
    {
        yield '<table ';
        yield parent::getAllAttributesString();
        yield '>';

        if (empty($this->header) === false) {
            yield '<thead><tr>';

            foreach ($this->header as $th) {
                yield '<th ';
                yield $th->getAllAttributesString();
                yield '>';
                yield $th->getFormattedValue();
                yield '</th>';
            }

            yield '</tr></thead>';
        }

        if (empty($this->body) === false) {
            yield '<tbody>';

            foreach ($this->body as $tr) {
                yield $tr->toHtmlText();
            }

            yield '</tbody>';
        }

        if (empty($this->footer) === false) {
            yield '<tfoot>';

            foreach ($this->footer as $tr) {
                yield $tr->toHtmlText();
            }

            yield '</tfoot>';
        }

        yield '</table>';
    }

    public function toHtmlText(): string
    {
        $outputText = '';
        foreach ($this->streamHtmlText() as $html) {
            $outputText .= $html;
        }
        return $outputText;
    }
}
