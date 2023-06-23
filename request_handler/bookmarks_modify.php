<?php


?>

<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="css/style.css">
	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/table-sorter.min"></script>
	<script src="js/myscript.js"></script>
	<script src="js/function.js"></script>
</head>
<?php include('inc/html/nav.html');?>

<body>


<div class="paper">
<form name="Bookmark_create" method="POST" action="process/createBookmark">
 <label for="txtBookmark">New Bookmark: </label>
 <input type="search" id="txtBookmark" name="txtBookmark" class="txtInput" maxlength="255" placeholder="Name Bookmark...">
 <input type="submit" name="btnSubmit" value="Create">
</form>
</div>

<hr>

<div class="paper">
<form name="Form_removeBookmarks" method="POST" action="process/updateBookmarks">
	Remove Bookmarks:<br>
	<input type="submit" name="btnSubmit" value="Remove" />
	<br>
	<?php 
		$bk = new Bookmark($con);
		$bookmarks = $bk->getBookmarks();
		$temp = '<ul>';
		
		foreach($bookmarks as $row){
			$temp .= '<li><label for="' .$row['id'] .'">' .$row['name'] .'</lable> ' 
				.'<input type="checkbox" name="chkBookmarks[]" id="' .$row['id'] .'" value="' .$row['id'] .'" /></li>';
		}
		unset($row);
		$temp .= '</ul>';
		echo $temp;
	?>
</form>
</div>

<div class="paper">
<form name="Form_restoreBookmarks" method="POST" action="process/updateBookmarks">
	Restore Bookmarks:<br>
	<input type="submit" name="btnSubmit" value="Restore" />
	<br>
	<?php 
		$bk = new Bookmark($con);
		$bookmarks = $bk->getBookmarks(0);
		$temp = '<ul>';
		
		foreach($bookmarks as $row){
			$temp .= '<li><label for="' .$row['id'] .'">' .$row['name'] .'</lable> ' 
				.'<input type="checkbox" name="chkBookmarks[]" id="' .$row['id'] .'" value="' .$row['id'] .'" /></li>';
		}
		unset($row);
		$temp .= '</ul>';
		echo $temp;
	?>
</form>
</div>



<?php 

mysqli_close($con);

?>


</body>
</html>