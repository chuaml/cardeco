<?php 
require('../inc/class/Lzd_Fee_Checker.php');


$Lzd_Fee_Checker = new Lzd_Fee_Checker;

$Lzd_Fee_Checker->setDB_Result($con);
$table = $Lzd_Fee_Checker->getAll_Conclusion();
try{
	if(sizeof($table) === 0){
		throw new Exception('no record matched.');
	}


	$output = '<table><thead><tr><th>' .implode('</th><th>', array_keys($table[array_keys($table)[0]])) .'</th></tr><tbody>';
	foreach ($table as $r) {
		$output .= '<tr><td>' .implode('</td><td>', $r) .'</td></tr>';
	}

	$output .= '</tbody></table>';

	$style = '<style>td:nth-child(7){background-color: #899427;}</style>';


	echo $output .$style;
}catch(Exception $e){
	echo $e->getMessage();
}