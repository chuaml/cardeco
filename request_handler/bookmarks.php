<?php

require('inc/class/Bookmark.php');
require('inc/class/StockBookmark.php');
require('inc/class/Page_Handler_Bookmark.php');

function ctime(){
		list($usec, $sec) = explode(' ', microtime());
		return ((float)$usec + (float)$sec);
	}
$st = ctime();

?>

<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1">

	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/table-sorter.min"></script>
	<script src="js/myscript.js"></script>
	<script src="js/function.js"></script>
</head>
<?php include('inc/html/nav.html');?>

<body>
<?php 
$Bookmark = new Bookmark($con);
$pg;
$sb;
$bookmark_id = isset($_GET['id']) ? intval(trim($_GET['id'])) : false;
if($bookmark_id === false){
	$pg = new Page_Handler_Bookmark($Bookmark);
	echo '<div class="paper">Bookmarks: <br>
	<a class="button" style="float: right" href="bookmarks_modify">Modify</a>
	<ul><li>' 
		.implode('</li><li>', $pg->getBookmark_list()) 
		.'</li></ul></div>';
	
} else {
	$txtInput = isset($_GET['txtInput']) ? trim($_GET['txtInput']) : '';
	$current_page = isset($_GET['page']) ? intval(trim($_GET['page'])) : 1;
	$sb = new StockBookmark($con);
	$bk_id = isset($_REQUEST['id']) ? intval(trim($_REQUEST['id'])) : 0;
	
	$sb->setBookmark_id($bk_id);
	$sb->setQueryArg($txtInput);
	$sb->execute_query($current_page);

	$pages = $sb->getResult_pages();
	$page = '<div id="result_pages">';
	$pg_href = '';
	foreach($pages as $pg){
		$pg_href = http_build_query(array_merge($_GET, array('page' => $pg)));
		$page .= '<a href="bookmarks?' .$pg_href .'">' 
			.$pg  
			.'</a> ';
	}
	unset($pg);
	$page .= '</div>';
	
	$msg = [];
	$msg[] = 'query took: ' .(ctime() - $st) . ' seconds.';
	$msg[] = 'Num of search result: ' . $sb->query_num_rows;
	//$msg[] = 'Total db items: ' . $sb->getTotal_num_stock();
	
	$msg = implode('<br>', $msg);
	echo "<span>$msg</span>"; echo "<br>$sb->query_time";
	
	
	$Bookmark = new Bookmark($con);
	$bookmarks = $Bookmark->getBookmarks();
	$bookmarks_selection = '';
	foreach($bookmarks as $row){
		$bookmarks_selection .= '<option value="' .$row['id'] 
			.'">' .$row['name'] .'</option>';
	}
	unset($row);
	?>
	<form name="itemlistForm" method="GET" action="bookmarks" onsubmit="setTxtInput()">
		<input type="hidden" name="id" value="<?php echo $bk_id;?>" />
		<input type="search" id="txtInput" name="txtInput" class="txtInput" maxlength="255" placeholder="Itemcode / Description...">
		<input type="submit" name="submit">
	</form>
	<hr>
	<form name="Form_Bookmarks" method="POST" action="process/actionBookmarks">
		<input type="hidden" name="id" value="<?php echo $bk_id;?>" />
		<input type="submit" name="btnPrint" value="Print" />

		<label for="items_per_page">Items per page: </label>
		<select name="items_per_page" id="items_per_page" required>
		  <option value="1">1</option>
		  <option value="4">4</option>
		  <option value="9">9</option>
		  <option value="16">16</option>
		  <option value="25">25</option>
		  <option value="36">36</option>
		</select>

		<br>
		<label for="chkWatermark">Watermark: </label>
		<input type="checkbox" name="chkWatermark" id="chkWatermark" checked />
		<input type="submit" name="btnRemove" style="float: right;" id="btnRemove" value="Remove" />
		<br>
		<?php 
		echo $page;
		echo $sb->getResult();?>
	</form>
	
	<?php 

}

mysqli_close($con);

?>

</body>
</html>