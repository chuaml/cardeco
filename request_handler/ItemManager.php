<?php

namespace main;

use \Product\ItemEditor;
use \Product\ItemManager;
use \Product\Item;

$itemEditor = '';
$error = '';
try {
    try {
        if (isset($_POST['r']) === true) {
            $ItemM = new ItemManager($con);
            $Items = [];
            foreach ($_POST['r'] as $itemId => $r) {
                $Items[] = new Item($itemId, null, $r['description']);
            }
            $ItemM->update($Items);
            // header('HTTP/1.1 205');
        }

        if (isset($_GET['itemCode'])) {
            $ItemM = new ItemManager($con);
            $ItemEditor = new ItemEditor();

            $ItemEditor->setItems(
                $ItemM->getItemLikeItemCode($_GET['itemCode']),
                0
            );

            $itemEditor = $ItemEditor->getTable();
        }
    } finally {
        $con->close();
    }
} catch (\Exception $e) {
    $error = $e->getMessage();
}

require('view/ItemManager.html');
