<?php 
require('../inc/class/Lzd_Fee_Matcher.php');


$Lzd_Fee_Matcher = new Lzd_Fee_Matcher;

$Lzd_Fee_Matcher->setDB_Result($con);
$table = $Lzd_Fee_Matcher->getResult();
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