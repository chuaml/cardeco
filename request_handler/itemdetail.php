<?php

// dd($_SERVER, $_REQUEST, $_GET, $_POST);

$handler = new Stock_Item($con);
$handler->handleRequest($_POST);

require 'view/itemdetail.html.php';
