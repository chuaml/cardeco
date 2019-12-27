<?php 
class Bookmark{
	private $con;
	
	function __construct(mysqli $con){
		$this->con = $con;
	}

	public function setBookmarks_items(int $bookmark_id, array $chkItems){
		$bookmark_id = mysqli_real_escape_string($this->con, trim($bookmark_id));
		$items = [];
		$items_id = '';
		$db_bookmarks = [];
		$temp = '';
		$target_items = [];
		$target_itemsUpdate = [];

		foreach($chkItems as $row){
			if(!isset($row['id'])){
				continue;
			}
			$temp = trim($row['id']);
			if(strlen($temp) === 0){
				continue;
			}
			
			$items[] = mysqli_real_escape_string($this->con, $temp);
		}
		unset($row);
		
		$items_id = implode("','", $items);
		
		$sql = "SELECT bookmarks_items.item_id FROM bookmarks INNER JOIN bookmarks_items 
		ON bookmarks.id = bookmarks_items.bookmark_id 
		WHERE bookmarks.id = $bookmark_id 
		AND bookmarks_items.item_id IN ('$items_id');";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$db_bookmarks[$row['item_id']] = 1;
		}
		unset($row);
		mysqli_free_result($stmt);
		
		
		foreach($chkItems as $row){
			if(!isset($row['id'])){
				continue;
			}
			
			$temp = trim($row['id']);
			if(strlen($temp) === 0){
				continue;
			}
			
			if(isset($db_bookmarks[$temp]) === false){
				$target_items[] = mysqli_real_escape_string($this->con, $temp);
			} else {
				$target_itemsUpdate[] = mysqli_real_escape_string($this->con, $temp);
			}
		}
		unset($row);
		
		foreach($target_items as $row){
			$sql = "INSERT INTO bookmarks_items (bookmark_id, item_id, date) 
			VALUES('$bookmark_id', '$row', NOW());";
			$stmt = mysqli_query($this->con, $sql);
			if(!$stmt){
				trigger_error(mysqli_error($this->con));
				return false;
			}
		}
		unset($row);
		
		$temp = implode("','", $target_itemsUpdate);
		$sql = "UPDATE bookmarks_items SET status = 1 
		WHERE bookmark_id = '$bookmark_id' AND 
		item_id IN ('$temp');";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		

	}

	public function createNew_bookmark(string $bookmark_name){
		$bookmark_name = mysqli_real_escape_string($this->con, trim($bookmark_name));
		$sql = "SELECT COUNT(name)AS count_name FROM bookmarks 
		WHERE name = '$bookmark_name';";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			if($row['count_name'] != 0){
				trigger_error('Bookmark Name exist.');
				return false;
			}
		}
		unset($row);
		mysqli_free_result($stmt);
		
		$sql = "INSERT INTO bookmarks (name, date) 
		VALUES('$bookmark_name', NOW());";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
	}
	
	public function updateBookmarks(array $bookmark_id, int $active){
		$bk_id = [];
		$temp = '';
		foreach($bookmark_id as $v){
			$temp = trim($v);
			if(strlen($temp) === 0){
				continue;
			}
			$bk_id[] = mysqli_real_escape_string($this->con, $temp);
		}
		unset($v);
		
		$bk_id = implode("','", $bk_id);
		
		$sql = "UPDATE bookmarks SET status = $active WHERE id IN ('$bk_id');";
		$stmt = mysqli_query($this->con,$sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
	}

	public function getBookmarks(int $active = 1){
		$result = [];
		$sql = "SELECT id, name FROM bookmarks WHERE status = $active;";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
		while($row = mysqli_fetch_assoc($stmt)){
			$result[] = $row;
		}
		
		return $result;
	}
	
	public function removeBookmarks_items(int $bookmark_id, array $chkItems, string $id_index){
		$bk_id = mysqli_real_escape_string($this->con, $bookmark_id);
		$items_id = [];
		$temp = '';
		foreach($chkItems as $row){
			if(!isset($row[$id_index])){
				continue;
			}
			$temp = trim($row[$id_index]);
			if(strlen($temp) === 0){
				continue;
			}
			
			$items_id[] = mysqli_real_escape_string($this->con, $temp);
		}
		unset($row);

		if(sizeof($items_id) === 0){
			echo 'No selected Items for removing';
			return false;
		}

		$items_id = implode("','", $items_id);
		
		$sql = "UPDATE bookmarks_items SET status = 0 
		WHERE bookmark_id = $bk_id AND item_id IN ('$items_id');";
		$stmt = mysqli_query($this->con, $sql);
		if(!$stmt){
			trigger_error(mysqli_error($this->con));
			return false;
		}
		
	}
}
