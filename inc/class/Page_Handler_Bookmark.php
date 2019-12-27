<?php 
class Page_Handler_Bookmark{
	private $Bookmark;
	private $bookmark_list;
	
	function __construct(Bookmark $bookmark){
		$this->Bookmark = $bookmark;
	}
	
	public function getBookmark_list(){
		$bookmark_list = $this->Bookmark->getBookmarks();
		$result = [];
		
		foreach($bookmark_list as $row){
			$result[] = '<a href="?id=' 
				.$row['id'] 
				.'">' 
				.$row['name'] 
				.'</a>';
		}
		
		return $result;
	}
}