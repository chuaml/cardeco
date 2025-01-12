<?php

namespace Product;

require_once(__DIR__ . '/../HTML/TableDisplayer.php');

use Generator;
use HTML\HtmlObject;
use HTML\HtmlTable;
use HTML\HtmlTableRow;
use HTML\HtmlTableCell as Cell;
// use \HTML\TableDisplayer;

class ItemEditor
{
    private $numFloorPage;
    private $htmlTable;

    public function __construct(array $Item_list, int $numPage)
    {
        $this->htmlTable = new HtmlTable();
        $this->htmlTable->setAttribute('cd-editable-sheet', '');

        $this->htmlTable->setHeader(0, new Cell('Item Code'));
        $this->htmlTable->setHeader(1, new Cell('Description'));
        $this->htmlTable->setHeader(2, new Cell('UOM'));
        $this->htmlTable->setHeader(3, new Cell('Group'));

        foreach ($Item_list as $Item) {
            $x = $Item->getAll();
            $r = new HtmlTableRow();

            $r->addCell(new Cell($x['code']));
            $r->addCell(new Cell())
                ->addChild(new HtmlObject('input'))
                ->setAttribute('name', 'r[' . $x['itemId'] . ']')
                ->setAttribute('value', $x['description'])
                ->setAttribute('maxlength', '255');
            $r->addCell(new Cell($x['uom']));
            $r->addCell(new Cell($x['group']));

            $this->htmlTable->addRow($r);
        }
        $this->numFloorPage = $numPage;
    }

    public function getTable(): Generator
    {
        return $this->htmlTable->streamHtmlText();
    }
}
