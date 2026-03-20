<?php

namespace main;

use Report\ShippedItemReport;

$handler = new ShippedItemReport($con, $_FILES);

$_view_html_path = 'view/ShippedItemReport.html';
