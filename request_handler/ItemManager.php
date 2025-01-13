<?php

namespace main;

use \Product\ItemEditor;
use \Product\ItemManager;
use \Product\Item;

$itemEditor = null;
$itemEditorHtml = null;
$error = '';

try {
    if (isset($_POST['r']) === true) {
        $ItemM = new ItemManager($con);
        $Items = [];
        foreach ($_POST['r'] as $itemId => $description) {
            $Items[] = new Item($itemId, null, $description);
        }
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
    require('view/ItemManager.html');
} finally {
    $con->close();
}
