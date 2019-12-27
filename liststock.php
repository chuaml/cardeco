<?php
require_once('../db/conn_staff.php');
require('inc/class/StockManager.php');
require('inc/class/Bookmark.php');
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

<body onload="getTxtInput();getChkShowImage();">
<form name="itemlistForm" method="GET" action="liststock" onsubmit="setTxtInput();setChkShowImage();" />
<label for="chkShowImage" style="line-height: 1.5">Show Images </label>
<input type="checkbox" name="chkShowImage" id="chkShowImage" /> <br />
 <input type="search" id="txtInput" name="txtInput" class="txtInput" maxlength="255" placeholder="Itemcode / Description..." />
 <input type="submit" name="submit" /> 
</form>

<?php 
$txtInput = isset($_GET['txtInput']) ? trim($_GET['txtInput']) : '';
$current_page = isset($_GET['page']) ? intval(trim($_GET['page'])) : 1;
$showImage = isset($_GET['chkShowImage']) ? true : false;
$sm = new StockManager($con);

$sm->setQueryArg($txtInput);
$sm->execute_query($current_page, 0, $showImage);

$pages = $sm->getResult_pages();
$page = '<div id="result_pages">';
$pg_href = '';
foreach($pages as $pg){
	$pg_href = http_build_query(array_merge($_GET, array('page' => $pg)));
	$page .= '<a href="liststock?' .$pg_href .'">' 
		.$pg  
		.'</a> ';
}
unset($pg);
$page .= '</div>';

$msg = [];
$msg[] = 'query took: ' .(ctime() - $st) . ' seconds.';
$msg[] = 'Num of search result: ' . $sm->query_num_rows;
$msg[] = 'Total db items: ' . $sm->getTotal_num_stock();

$msg = implode('<br>', $msg);
echo "<span>$msg</span>";


$Bookmark = new Bookmark($con);
$bookmarks = $Bookmark->getBookmarks();
$bookmarks_selection = '';
foreach($bookmarks as $row){
	$bookmarks_selection .= '<option value="' .$row['id'] 
		.'">' .$row['name'] .'</option>';
}
unset($row);
?>
<hr>
<form name="Form_Bookmarks" method="POST" action="process/actionListstock">
	<select name="bookmark_id" required>
		<option value="">PLEASE SELECT</option>
		<?php 
			echo $bookmarks_selection;
		?>
	</select>
	<input type="submit" name="btnSubmit" value="Bookmark" />
	<input type="submit" name="btnSubmit_exportImg" value="Export Image" />
	<br>
	<?php 
	echo $page;
	echo $sm->getResult();?>
</form>

<?php 

mysqli_close($con);

?>


</body>
</html>