<?php 
class catalog_generator{
	public $items;
	public $WATERMARK_FILE_PATH;
	public $HEADER_FILE_PATH;
	public $FOOTER_FILE_PATH;
	private $images_i;
	private $comments;
	private $delimiter;
	
	public function __construct(){
		$this->items = [];
		$this->WATERMARK_FILE_PATH = '../public/catalog/watermark.png';
		$this->HEADER_FILE_PATH = '../public/catalog/header.png';
		$this->FOOTER_FILE_PATH = '../public/catalog/footer.png';
		$this->images_i = [];
		$this->comments = [];
		$this->delimiter = '||';
	}
	
	public function generateCatalog(mysqli $con, array $row_list, int $limit_result = 1000){
		$clean_id = '';
		$items_id = [];
		$images_i = [];
		$comments = [];
		$delimiter = $this->delimiter;
		foreach($row_list as $row){
			if(!isset($row['id'])){ //skip no id, not checked
				continue;
			}
			$items_id[] = mysqli_real_escape_string($con, trim($row['id']));
			if(!isset($row['image'])){
				$images_i[] = [0]; //default first image for that row
			} else {
				$images_i[] = $row['image'];
			}
			
			if(strlen(trim($row['comment'])) > 0){
				$comments[] = trim($row['comment']);
			}
		}
		unset($row);

		if(sizeof($items_id) === 0){
			return;
		}

		$clean_id = "'" .implode("','", $items_id) ."'";

		//getItems
		$sql = "SELECT stock_items.item_code, stock_items.description, GROUP_CONCAT(CONCAT(stock_images.directory, stock_images.image)SEPARATOR '$delimiter')AS image FROM stock_items 
		INNER JOIN stock ON stock_items.id = stock.id 
		LEFT JOIN stock_images ON stock_items.id IN (stock_images.item) 
		WHERE stock_items.id IN ($clean_id)
		GROUP BY stock_items.id, stock_items.item_code, stock_items.description 
		LIMIT $limit_result;";
		$stmt = mysqli_query($con,$sql);
		if(!$stmt){
			trigger_error(mysqli_error($con));
			return mysqli_error($con);
		}
		
		$itemList = [];
		while($row = mysqli_fetch_assoc($stmt)){
			$itemList[] = $row;
		}
		unset($row);

		$this->images_i = $images_i;
		$this->items = $itemList;
		$this->comments = $comments;
		
		$this->encodeResult();
	}
	
	public function drawCatalog(int $items_per_page = 1, bool $include_watermark = true){
		//if(sizeof($this->items) === 0 ){return null;}	
		
		return $this->drawPages($items_per_page, $include_watermark);
	}
	
	private function encodeResult(){
		if(!is_array($this->items[0])){
			return false;
		}
		
		$new_itemList = [];
		$temp_row = [];
		$comment = '';
		foreach($this->items as $k => $row){
			$temp_row = [];
			$comment = isset($this->comments[$k]) ? 
			htmlspecialchars($this->comments[$k], ENT_QUOTES, 'UTF-8') : false;
			foreach($row as $f => $col){
				$temp_row[$f] = htmlspecialchars(trim($col), ENT_QUOTES, 'UTF-8');
			}
			unset($col); unset($f);
			if($comment !== false){
				$temp_row['comment'] = $comment;
			}
			$new_itemList[] =  $temp_row;
		}
		
		$this->items = $new_itemList;
	}
	
	private function drawPages(int $item_per_page, bool $watermark){
		$page = [];
		$all_pages = '';
		$PAGE_HEADER = '<div class="header">
			<img src="' .$this->HEADER_FILE_PATH .'" onerror="this.style.display=\'none\'"></div>';
		$PAGE_FOOTER = '<div class="footer">
			<img src="' .$this->FOOTER_FILE_PATH .'" onerror="this.style.display=\'none\'"></div>';
		$max_num_items_square = $this->calcMax_Num_Square($item_per_page);

		$items = '';
		$count = $item_per_page;
		$new_page = false;
		foreach($this->items as $k => $row){
			$new_page = false;
			$items .= $this->drawItem($k, $row, $watermark);
			$count -= 1;
			if($count <= 0){
				$page[] = $items;
				$items = '';
				$count = $item_per_page;
				$new_page = true;
			}
		}
		unset($row); unset($k);
		if($new_page === false){
			$page[] = $items;
			$items = '';
		}
		
		foreach($page as $content){
			
			$all_pages .= '<div class="a4_page">' 
				.$PAGE_HEADER 
				."<div class=\"content\" style=\"--num_items: $max_num_items_square;\">" 
				.$content 
				.'</div>' 
				.$PAGE_FOOTER .'</div>';
		}
		
		return $all_pages;
	}
	
	private function drawItem(string $item_index, array $item_row, bool $watermark){
		$item = '';
		$item_data = '';
		$description = '<div class="description"><table>';
		$th = '';
		foreach($item_row as $c => $col){
			switch($c){
				case 'item_code': 
					$th = 'Item Code'; 
					break;
				case 'description':
					$th = 'Description'; 
					break;
				case 'comment': 
					$th = 'Remark'; 
					break;
				default:
					$th = '';
			}
		if(strlen($th) === 0){continue;}
		$description .= "<tr><th>$th: </th><td>$col</td></tr>";
		}
		$description .= '</table></div>';
		
		$item_data .= $this->drawItem_Image($item_index, $item_row['image'], $watermark);
		$item_data .= $description;
		
		$item .= '<div class="item">' .$item_data .'</div>';
		
		return $item;
	}
	
	private function drawItem_Image(string $item_index, string $column_image_value, bool $watermark){
		$image_id = [];
		$image_container = '';
		$image_data = '';
		$image_watermark = ($watermark === true) ? '<img src="' .$this->WATERMARK_FILE_PATH .'" alt="watermark-img" />' : '';
		$temp_img = '';
		$max_num_imgs_square = 1;
		
		
		if(strlen(trim($column_image_value)) === 0){
			return null;
		}
		
		$image_id = explode($this->delimiter, $column_image_value);
		$num_imgs = sizeof($image_id);
		if($num_imgs === 0){
			return null;
		}

		$num_imgs = 0;
		foreach($this->images_i[$item_index] as $img_index){
			$temp_img = '<img src="../' .$image_id[$img_index] .'" alt="product-image" />';
			
			$image_data .= '<div>' .$temp_img .$image_watermark .'</div>';
			++$num_imgs;
		}

		$max_num_imgs_square = $this->calcMax_Num_Square($num_imgs);
		
		$image_container = "<div class=\"img_container\" style=\"--num_imgs: $max_num_imgs_square;\">" 
			. $image_data . '</div>';
		
		return $image_container;
	}

	private function calcMax_Num_Square(int $num_items){
		if($num_items <= 0){return 1;}
		$found = false;
		$size = 1;
		for($i=1;$i<=50;++$i){
			switch($num_items){
				case $num_items <= ($i ** 2): 
					$size = $i;
					$found = true;
					break;
			}
			if($found === true){
				return $size;
			}
		}

		return $i;
	}
}
