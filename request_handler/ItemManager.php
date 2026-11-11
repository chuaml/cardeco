<?php

namespace main;

use \Product\ItemEditor;
use \Product\ItemManager;
use \Product\Item;

# temporary code, to upgrade db structure
// $sql = file_get_contents('db/upgrade.sql');
// $con->query($sql);


$itemEditor = null;
$itemEditorHtml = null;
$error = '';

try {
    if (isset($_POST['r']) === true) {
        $ItemM = new ItemManager($con);
        // mappings are matched to items by item id (bigseller_sku_map[<item id>][<mapping id>|new]) - the former
        // positional pairing turned PHP's auto-index of new rows into the ids of other items' mappings
        $mappings = isset($_POST['bigseller_sku_map']) && is_array($_POST['bigseller_sku_map']) ? $_POST['bigseller_sku_map'] : [];
        $Items = ItemManager::itemsFromRequest(is_array($_POST['r']) ? $_POST['r'] : [], $mappings);
        $ItemM->update($Items);
        // header('HTTP/1.1 205');
    } else
        if (isset($_GET['itemCode'])) {
        $ItemM = new ItemManager($con);
        $ItemEditor = new ItemEditor(
            $ItemM->getItemLikeItemCode($_GET['itemCode']),
            0
        );

        $itemEditorHtml = $ItemEditor->getTable();
    } else {
        $ItemM = new ItemManager($con);
        $ItemEditor = new ItemEditor(
            $ItemM->getItem(),
            0
        );

        $itemEditorHtml = $ItemEditor->getTable();
    }
    $_view_html_path = 'view/ItemManager.html';
} finally {
    $con->close();
}
