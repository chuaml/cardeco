<?php

namespace Product;

require_once(__DIR__ . '/../HTML/TableDisplayer.php');

use HTML\HtmlTable;
use HTML\HtmlTableRow;
use HTML\HtmlTableCell;
// use \HTML\TableDisplayer;

class ItemEditor
{
    private $numFloorPage;
    private $htmlTable;

    public function __construct(array $Item_list, int $numPage)
    {
        $this->htmlTable = new HtmlTable();

        $this->htmlTable->setHeader(0, new HtmlTableCell('Item Code'));
        $this->htmlTable->setHeader(1, new HtmlTableCell('Description'));
        $this->htmlTable->setHeader(2, new HtmlTableCell('UOM'));
        $this->htmlTable->setHeader(3, new HtmlTableCell('Group'));

        foreach ($Item_list as $Item) {
            $x = $Item->getAll();
            $r = new HtmlTableRow();

            $r->addCell(new HtmlTableCell($x['code']));
            $r->addCell(new HtmlTableCell($x['description']))
                ->setAttribute('contenteditable', 'plaintext-only')
                ->setAttribute('data-max-length', '255')
                ->setAttribute('data-name', 'r[' . $x['itemId'] . ']');
            $r->addCell(new HtmlTableCell($x['uom']));
            $r->addCell(new HtmlTableCell($x['group']));

            $this->htmlTable->addRow($r);
        }
        $this->numFloorPage = $numPage;
    }

    public function getTable(): string
    {
        return $this->htmlTable->toHtmlText();
    }
}
