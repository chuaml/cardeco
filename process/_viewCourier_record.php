<?php 
require('../inc/class/Courier_Record_Viewer.php');


$Courier_Record_Viewer = new Courier_Record_Viewer;

$Courier_Record_Viewer->setDB_Result($con);
$table = $Courier_Record_Viewer->getResult();
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