<?php

namespace Product;

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
        $this->htmlTable->setHeader(1, new Cell('Big Seller SKU'));
        $this->htmlTable->setHeader(2, new Cell('Description'));
        $this->htmlTable->setHeader(3, new Cell('UOM'));
        $this->htmlTable->setHeader(4, new Cell('Group'));

        foreach ($Item_list as $Item) {
            $x = $Item->getAll();
            $r = new HtmlTableRow();

            $r->addCell(new Cell($x['code']));

            // only take 1st mapping, might need more in future; an item without any mapping row gets an empty input
            $b = isset($x['bigseller_sku_map'][0]) ? $x['bigseller_sku_map'][0] : ['id' => null, 'bigseller_sku' => null];
            // name = bigseller_sku_map[<item id>][<mapping id> | new]: self-describing, so the controller never has to
            // pair mappings with items by position (that pairing used to re-point other items' mapping rows)
            $mappingKey = $b['id'] === null ? ItemManager::NEW_MAPPING_KEY : (string) $b['id'];
            $r->addCell(new Cell())
                ->addChild(new HtmlObject('input'))
                ->setAttribute('name', 'bigseller_sku_map[' . $x['itemId'] . '][' . $mappingKey . ']')
                ->setAttribute('value', $b['bigseller_sku'])
                ->setAttribute('maxlength', '255');
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
