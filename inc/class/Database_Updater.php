<?php 
require('Database_Manager.php');
class Database_Updater extends Database_Manager{
	private $itemsList;
	private $filedata_totalItems;
	function __construct(mysqli $con){
		parent::__construct($con);
	}
	
	public function getFileContent(string $string_data){
		$string_data = trim($string_data);
		
		$row = explode("\n", $string_data);
		
		$len = sizeof($row) - 1;
		$totalItems = 0;
		$temprow = [];
		for($i=$len;$i>0;--$i){
			$temprow = explode("\t", $row[$i]);
			if(trim($temprow[0]) === 'Total Items  :'){
				$totalItems = intval($temprow[1]);
				break;
			}
		}
		$this->filedata_totalItems = $totalItems;
		
		$itemList = [];
		$temprow = '';
		foreach($row as $c => $col){
			$temprow = trim($col);
			if(strlen($temprow) === 0){
				
			}
		}
		
	}
}
