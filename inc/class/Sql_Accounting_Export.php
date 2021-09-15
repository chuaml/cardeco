<?php 
class Sql_Accounting_Export{
	private $itemList;
	private $total_items;
	private $page_index;
	private $ROW_DELIMITER;
	private $COL_DELIMITER;
	private $PAGE_DELIMITER;
	private $ITEM_TARGET_COL;

	public function __construct(){
		$this->itemList = [];
		$this->total_items = 0;
		$this->page_index = [];
		$this->ROW_DELIMITER = "\n";
		$this->COL_DELIMITER = "\t";
		$this->PAGE_DELIMITER = [
			0 => 'Item Code',
			3 => 'Description',
			10 => 'UOM',
			12 => 'Book Qty',
			13 => 'Physical Qty',
			16 => 'Remarks'
		];
		$this->ITEM_TARGET_COL = [
			0 => 'item_code',
			3 => 'description',
			10 => 'uom', 
			11 => 'quantity'
		];
	}

	public function validateFile(array $file, string $fileType=null){
		if(sizeof($file) !== 5){
			die('Error: Not $_FILES ??');
		}
		$errormsg = null;
		
		//check has File?, errors?, perform File validating
		if(strlen($file['name']) === 0 || $file['size'] === 0){
			$errormsg = 'No File selected.';
			return ['valid' => false,
				'errormsg' => $errormsg];
		}
		if($file['error'] !== 0){
			$errormsg = 'errors: '.$file['error'];
			return ['valid' => false,
				'errormsg' => $errormsg];
		}
		
		if($fileType !== null && is_string($fileType)){
			$uploaded_fileType = pathinfo(basename($file['name']),PATHINFO_EXTENSION);
			if($uploaded_fileType !== $fileType){
				$errormsg = "File must be text .$fileType file.";
				return ['valid' => false,
				'errormsg' => $errormsg];
			}
		}
		return ['valid' => true,
				'errormsg' => $errormsg];
	}

	public function setItemList(string $file_data){
		$this->itemList = explode("\n", trim($file_data));
	}

	

	public function matrix_ItemList(){
		if($this->setTotal_Items() === false){
			return false;
		}

		if(sizeof($this->itemList) === 0){
			return false;
		}

		$row = [];
		$current_line = '';
		$current_row = [];
		foreach($this->itemList as $k => $line){
			$current_line = trim($line);
			if(strlen($current_line) === 0){continue;}
		
			$current_row = explode("\t", $current_line);
			if(sizeof($current_row) === 0){continue;}
			$row[] = $current_row;
		}
		if(sizeof($row) === 0){
			return false;
		}

		$this->itemList = $row;
		return true;
	}

	public function setPage_Index(){
		$page_index = [];
		$found_page = false;
		$PAGE_DELIMITER = $this->PAGE_DELIMITER;
			end($PAGE_DELIMITER);
		$TARGET_NUM_COL = key($PAGE_DELIMITER) + 1;
		$count = count($this->itemList);
		for($k=0;$k<$count;++$k){
			$row = (array) $this->itemList[$k];
			if(sizeof($row) !== $TARGET_NUM_COL){continue;}
			$found_page = true;
			foreach($this->PAGE_DELIMITER as $c => $col){
				if(!isset($row[$c])){ 
					$found_page = false;
					break;
				}
				if(trim($row[$c]) !== trim($col)){
					$found_page = false;
					break;
				}
			}
			unset($col); unset($c);
			if($found_page){
				$page_index[] = $k;
			}
		
		}
		
		if(sizeof($page_index) === 0){return false;}

		$this->page_index = $page_index;
		return true;
	}

	public function gatherRow_Data(){
		$row = []; $x = 0;
		$temp_col = [];
		$item_group = '';
		$current_row_index = 0;
		$current_row = [];
		$current_group = [];
		$PAGE_LEN = 31;
		$ITEM_TARGET_COL = $this->ITEM_TARGET_COL;
			end($ITEM_TARGET_COL);
		$TARGET_NUM_COL = key($ITEM_TARGET_COL) + 1;
		$ENDLINE = sizeof($this->itemList) - 1 -2; 
		foreach($this->page_index as $r){
			$current_group = $this->itemList[$r + 1];
			for($i=0;$i<$PAGE_LEN;++$i){
				$current_row_index = ($r + 2 + $i); 
				if($current_row_index > $ENDLINE){break;}

				$current_row = (array) $this->itemList[$current_row_index]; 
				if(strtolower(trim($current_row[0])) === 'sub-total for'){
					continue;
				}

				if(sizeof($current_row) !== $TARGET_NUM_COL){
					$current_group = $current_row[0];
					continue;
				}
				$temp_col = [];
				foreach($this->ITEM_TARGET_COL as $c => $col){
					if($col === 'quantity'){
						$temp_col[$col] = intval($current_row[$c]);
						continue;
					}
					$temp_col[$col] = trim($current_row[$c]);
				}
				unset($col); unset($c);
				$temp_col['item_group'] = trim($current_group[0]);

				$row[] = $temp_col;
			}
		}
		$this->itemList = $row; 
	}

	public function getItemList(){
		return $this->itemList;
	}

	private function setTotal_Items(){
		$last = sizeof($this->itemList) - 1;
		$temp_line = '';
		$temp_row = [];
		$temp_string = '';
		$ENDSTRING = 'Total Items  :';
		for($i=$last;$i>0;--$i){
			$temp_line = trim($this->itemList[$i]);
			if(strlen($temp_line) === 0){continue;}

			$temp_row = explode("\t", $temp_line);
			$temp_string = trim($temp_row[0]);
			if(strlen($temp_string) === 0){continue;}
			if($temp_string !== $ENDSTRING){continue;}

			$this->total_items = intval($temp_row[1]);
			return true;
		}
		return false;
	}
}
