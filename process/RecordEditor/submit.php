<?php 
require_once('db/conn_staff.php');
require_once('inc/class/Orders/MonthlyRecordUpdater.php');

use Orders\MonthlyRecordUpdater;

$Updater = new MonthlyRecordUpdater();

$Updater->update($con, $_POST['r']);

// var_dump($_POST);
