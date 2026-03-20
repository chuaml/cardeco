<?php


if (isset($_POST['btnSubmit'])) {
	include('process/exportAll_StockImages.php');
}

?>


Export All products images: <br>
<form method="POST" action="image_export.php">
	<input type="checkbox" id="chkConfirm" required />
	<label for="chkConfirm">Export All Products Images</label>
	<input type="submit" name="btnSubmit" value="Export" />
</form>
