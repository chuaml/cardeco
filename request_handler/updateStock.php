<!DOCTYPE html>
<html>
<title>CarDeco sys</title>
<head>
	<link rel="stylesheet" href="css/style.css">
	<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<?php include('inc/html/nav.html');?>

<body>
<form id="Form_Update" method="POST" action="process/updateStock" enctype="multipart/form-data">
<label for="Form_Update">
	<p>Enter text <b>.txt</b> data from SQL Accounting to Update <br />
	<b>*Acculumative update, new items will be added to database stock.</b></p>
 </label>
 <p style="color: red; font-weight: bold; width: 50vw;">*Note Please Enter valid text .txt data file without any modification to avoid Invalid or Corrupted data 
	being added to or corrupting database.</p> <br/>
 <input type="file" name="fileTextdata" accept=".txt" required>
 <input type="submit" id="submit" name="btnSubmit" value="UPDATE" />

</form>

</body>
<script defer>
	document.getElementById('Form_Update').addEventListener('submit', e => { 
		document.getElementById('submit').disabled = true; 
	});
</script>
</html>