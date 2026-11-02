<?php

namespace main;

use Report\ShippedItemReport;

$handler = new ShippedItemReport($con, $_FILES);

require('view/ShippedItemReport.html');
