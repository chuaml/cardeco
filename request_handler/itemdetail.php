<?php

// dd($_SERVER, $_REQUEST, $_GET, $_POST);

$handler = new Stock_Item($con);
$handler->handleRequest($_POST);

$_view_html_path = 'view/itemdetail.html.php';
