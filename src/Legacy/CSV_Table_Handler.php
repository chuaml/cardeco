<?php 
class CSV_Table_Handler{
	protected $FILE;
	protected $Table;

	public function __construct(array $FILE){
		$this->FILE = $FILE;
		$this->Table = [];
	}

	public function setTable(bool $SKIP_HEADER = false, string $FILEDS_DELIMITER = ',', int $LIMIT_LINE_LENGTH = 25000, int $LIMIT_ROW = 25000){
		$file = fopen($this->FILE['tmp_name'], 'r');
		if(!$file){return false;}

		$table = [];
		if($SKIP_HEADER){fgetcsv($file, $LIMIT_ROW, $FILEDS_DELIMITER);}
		for($i=0;$i<$LIMIT_ROW;++$i){
			if(($row = fgetcsv($file, $LIMIT_ROW, $FILEDS_DELIMITER)) === null){
				throw new Exception('fail to get csv data at: '. $i);
				break;
			}
			if($row === false){
				break;
			}
			
			$table[] = $row;
		}

		$this->Table = $table;
		return true;
	}

	public function getField_Value(int $column_index, bool $auto_fill_empty = false){
		$result = [];
		$previous_val = '';
		$val = '';

		if($auto_fill_empty){
			foreach($this->Table as $row){
			$val = trim($row[$column_index]);
			if(strlen($val) > 0){
				$previous_val = $val;
			}

			$result[] = $previous_val;
			}
		} else {
			foreach($this->Table as $row){
				if(!isset($row[$column_index])){
					throw new Exception('Undefined offset, possible invalid file.');
				}
				$val = trim($row[$column_index]);

				$result[] = $val;
			}
		}

		return $result;
	}

	public function getFields_Value(array $columns_index, array $give_indexes_name){
		if(sizeof($columns_index) !== sizeof($give_indexes_name)){
			throw new Exception('getFields_Value() each retrieved indexes must has a new index name.');
			return false;
		}

		$result = [];
		$current_col = [];
		foreach($this->Table as $row){
			if($this->isEmptyLine($row)){continue;}
			$current_col = [];
			foreach($columns_index as $c => $col){
				if(!isset($row[$col])){
					throw new Exception('Undefined offset, possible invalid file.');
				}
				$current_col[$give_indexes_name[$c]] = $row[$col];
			}
			unset($col); unset($c);

			$result[] = $current_col;
		}
		unset($row);

		return $result;
	}

	public function getFile_Content(){
		return file_get_contents($this->FILE['tmp_name']);
	}

	private function isEmptyLine(array $row){
		$empty_col = 0;
		foreach($row as $col){
			if(strlen(trim($col)) <= 0){
				++$empty_col;
			}
		}

		if($empty_col >= sizeof($row)){
			return true;
		}

		return false;
	}
}