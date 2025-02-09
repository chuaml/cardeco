<?php

namespace main;

use Report\ShippedItemReport;

$handler = new ShippedItemReport($con);
$handler->handleRequest($_FILES);

require('view/ShippedItemReport.html');
