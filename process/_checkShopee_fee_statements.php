<?php 
require('../inc/class/CSV_Table_Handler.php');
require('../inc/class/Shopee_Fee_Statement.php');


$Shopee_Fee_Stmt = new Shopee_Fee_Statement($_FILES['file_Shopee_Stmt']);

$Shopee_Fee_Stmt->getTransactions();
$Shopee_Fee_Stmt->setDB_Result($con);
$Shopee_Fee_Stmt->compareDifferences();
$table = $Shopee_Fee_Stmt->getOutput();

try{
	if(sizeof($table) === 0){
		throw new Exception('no record matched.');
	}


	$output = '<table><thead><tr><th>' .implode('</th><th>', array_keys($table[array_keys($table)[0]])) .'</th></tr><tbody>';
	foreach ($table as $r) {
		$output .= '<tr><td>' .implode('</td><td>', $r) .'</td></tr>';
	}

	$output .= '</tbody></table>';


	echo $output;
}catch(Exception $e){
	echo $e->getMessage();
}
