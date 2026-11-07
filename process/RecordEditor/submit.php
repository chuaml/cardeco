<?php 
require_once(__DIR__ . '/../../vendor/autoload.php');
require_once(__DIR__ . '/../../db/conn_staff.php');

use Orders\MonthlyRecordUpdater;

$Updater = new MonthlyRecordUpdater();

$Updater->update($con, $_POST['r']);

// var_dump($_POST);
