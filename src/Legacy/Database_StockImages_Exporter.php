<?php 
require('Database_Manager.php');

class Database_StockImages_Exporter extends Database_Manager implements select{
	private $target_items_id;
	private $bookmark_name;
	
	function __construct(mysqli $con){
		parent::__construct($con);
		$this->target_items_id = [];
		$this->bookmark_name = '';
	}
	
	public function select(string $sql){
		$this->sql = $sql;
		$stmt = $this->exec_query($sql);
		if($stmt === false){
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$this->result[] = $row;
		}
		
		return $this->result;
	}
	
	public function exportFile(string $ROOT_SOURCE_PATH, string $EXPORT_PATH){
		if(!file_exists($ROOT_SOURCE_PATH)){
			trigger_error('Target PATH ' .$ROOT_SOURCE_PATH .'does not exist.');
			return false;
		}
		
		if(!file_exists($EXPORT_PATH)){
			trigger_error('Target PATH ' .$EXPORT_PATH .'does not exist.');
			return false;
		}
		
		if(sizeof($this->target_items_id) === 0){
			trigger_error('No item id.');
			return false;
		}
		
		if(strlen(trim($this->bookmark_name)) === 0){
			trigger_error('empty bookmark name');
			return false;
		}
		
		$dir_to_paste = $EXPORT_PATH ."/$this->bookmark_name/" .date('Y-m-d') .'/';
		if(!file_exists($dir_to_paste)){
			mkdir($dir_to_paste, 0777, true);
		}
		
		$items_id = implode("','", $this->target_items_id);
		$copyFrom = '';
		$pasteTo = '';
		$sql = "SELECT stock_items.description, stock_images.id, CONCAT(stock_images.directory, stock_images.image)AS dir, stock_images.file_type FROM stock_items 
		INNER JOIN stock_images ON stock_images.item IN (stock_items.id) 
		WHERE stock_items.id IN ('$items_id') 
		LIMIT 20000;";
		$result = $this->select($sql);
		if($result === false){
			trigger_error('Fail to retrieve data.');
			return false;
		}

		if(sizeof($result) === 0){
			return true;
		}
		
		foreach($result as $r){
			$copyFrom = $ROOT_SOURCE_PATH .$r['dir'];
			$pasteTo = $this->replaceExisting_Filename($dir_to_paste, $r);
			
			if(!copy($copyFrom, $pasteTo)){
				return false;
			}
		}
		
		return true;
	}
	
	public function setTarget_Items_Images(array $row_list){
		$items_id = [];
		$id = '';
		foreach($row_list as $row){
			if(!isset($row['id'])){ //skip no id, not checked
				continue;
			}
			
			$id = trim($row['id']);
			if(strlen($id) === 0){
				continue;
			}
			
			$items_id[] = mysqli_real_escape_string($this->con, $id);
		}

		if(sizeof($items_id) === 0){
			return false;
		}
		
		$this->target_items_id = $items_id;
	}
	
	public function setBookmark_name(string $bookmark_id){
		$id = trim($bookmark_id);
		$bookmark_name = '';
		if(strlen($id) === 0){
			return false;
		}
		$id = mysqli_real_escape_string($this->con, $id);
		
		$sql = "SELECT name FROM bookmarks WHERE id = $id AND STATUS = 1;";
		$stmt = $this->exec_query($sql);
		
		if($stmt === false){
			return false;
		}
		
		if($stmt->num_rows === 0){
			return false;
		}

		foreach($stmt as $r){
			$bookmark_name = $r['name'];
		}

		$this->bookmark_name = $this->removeSpecialChars($bookmark_name);
	}
	
	public function exportAll_Images(string $ROOT_SOURCE_PATH, string $EXPORT_PATH){
		if(!file_exists($EXPORT_PATH)){
			trigger_error('Target PATH ' .$EXPORT_PATH .'does not exist.');
			return false;
		}

		$dir_to_paste = $EXPORT_PATH .'/Products_Images_Export-' .date('Y-m-d') .'/';
		if(!file_exists($dir_to_paste)){
			mkdir($dir_to_paste, 0777, true);
		} else {
			$dir_to_paste = $EXPORT_PATH .'/Products_Images_Export-' .date('Y-m-d') .'-' .time() .'/';
			if(!file_exists($dir_to_paste)){
				mkdir($dir_to_paste, 0777, true);
			}
		}

		$copyFrom = '';
		$pasteTo = '';
		$new_dir_And_subFolder = '';
		$sql = "SELECT stock_items.description, stock_items.item_group, stock_images.id, CONCAT(stock_images.directory, stock_images.image)AS dir, stock_images.file_type FROM stock_items 
		INNER JOIN stock_images ON stock_images.item IN (stock_items.id);";
		$result = $this->select($sql);
		if($result === false){
			trigger_error('Fail to retrieve data.');
			return false;
		}

		if(sizeof($result) === 0){
			return true;
		}
		
		foreach($result as $r){
			$copyFrom = $ROOT_SOURCE_PATH .$r['dir'];
			$new_dir_And_subFolder = $this->createFolder_Item_Group($dir_to_paste, $r);
			$pasteTo = $this->replaceExisting_Filename($new_dir_And_subFolder, $r);
			
			if(!copy($copyFrom, $pasteTo)){
				return false;
			}
		}
		
		return true;
	}
	
	
	private function removeSpecialChars(string $string) {
		$string = str_replace(' ', '-', $string); // Replaces all spaces with hyphens.
		return preg_replace('/[^A-Za-z0-9\-]/', '', $string); // Removes special chars.
	}

	private function createFolder_Item_Group(string $dir_to_paste, array $row){
		$item_group = trim($row['item_group']);
		if($item_group === null || strlen($item_group) === 0){
			$item_group = 'etc';
		} else {
			$item_group = preg_replace('/[^A-Za-z0-9\-]/', ' ', $item_group);
		}
		$new_dir = $dir_to_paste . "/$item_group/";
		if(!file_exists($new_dir)){
			mkdir($new_dir, 0777, true);
		}
		
		return $new_dir;
	}
	
	private function replaceExisting_Filename(string $dir_to_paste, array $row){
		$filename_only = preg_replace('/[^A-Za-z0-9\-]/', ' ', trim($row['description']));
		$file_type = '.' .trim($row['file_type']);
		$pasteTo = $dir_to_paste .$filename_only .$file_type;
		if(!file_exists($pasteTo)){
			return $pasteTo;
		}

		$pasteTo = $dir_to_paste .$filename_only .'_' .$row['id'] .$file_type;
		if(!file_exists($pasteTo)){
			return $pasteTo;
		}

		$pasteTo = $dir_to_paste .$filename_only .'_' .$row['id'] .'-' .time() .$file_type;
		return $pasteTo;
	}
}
