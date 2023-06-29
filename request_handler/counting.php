<!DOCTYPE html>
<html lang="en">
<head>
	<?php require('view/template/head.php') ?>

	<script src="js/jquery-3.3.1.min"></script>
	<script src="js/myscript"></script>
</head>

<?php include('inc/html/nav.html');?>

<p>*For counting the number of occurrences for each item <br> 
*Case sensitive for items name</p>
<form name="itemlistForm" method="POST" action="counting">
 <label for="itemlistForm">Enter list of items name with each line represents 1 item.<br></label>
 <select name="Select" required>
	<option value="" selected>Please Select</option>
	<option value="normal">normal</option>
	<option value="3column">3columns Excel</option>
 </select>
 <input type="submit" name="submit"> <br>
 <textarea name="txtItems" rows="25" cols="64" maxlength="65536" required></textarea>

</form>

<hr>

<?php
//read Submitted data
if(isset($_POST['submit']) && isset($_POST['Select'])){ ?>
	<input type="date" id="selected_date" onchange="select_date()"><br>
	<?php 
	$data = rtrim($_POST['txtItems']);
	
	//normal 1column or 
	//2column for excel copied table GROUP BY ProductName
	if($_POST['Select'] === 'normal'){
		//normal
		$list = explode("\n",trim($data));
		$temp_list=array();
		foreach($list as $k => $v){
			$v = trim($v);
			if(strlen($v) === 0){
				continue;
			}
			$temp_list[] = $v;
		}
		$list = $temp_list;
		unset($temp_list); unset($k); unset($v);
		
		$len = sizeof($list);
	
		$itemList = array();
		$itemName = $list[0];
		$itemList[] = array($itemName, 1); //first item name, quantity 1
		$foundSame = false;
		for($r=1;$r<$len;++$r){
			$foundSame = false;
			$itemName_list = $list[$r];
			$len2 = sizeof($itemList);
			for($i=0;$i<$len2;++$i){
				$itemName = $itemList[$i][0];
				if($itemName_list === $itemName){
					$itemList[$i][1] += 1;
					$foundSame = true;
					break;
				}
			}
			if($foundSame === false){
				$itemList[] = array($list[$r], 1);
			}
			
		}
		$numrow = sizeof($itemList);
		$data = "Number of Rows: $numrow <br>";
		
		$data .= '<table>
				<thead>
				<tr>
					<th>Product Name</th><th>Quantity</th>
				</tr>
				</thead>
				<tbody>';
		$totalQuantity = 0;
		foreach($itemList as $r){
			$r[0] = htmlspecialchars($r[0], ENT_QUOTES, 'UTF-8');
			$data .= "
					<tr>
					<td>$r[0]</td><td>$r[1]</td>
					</tr>";
			$totalQuantity += intval($r[1]);
		}
		$data .=  "</tbody>
				<tfoot>
				<tr>
					<td>Total: </td><td>$totalQuantity</td>
				</tr></tfoot></table>";
	} else {
		//3columns
		$data = trim($_POST['txtItems']," \n\r\0\x0B"); //preserve \t tabs for fields delimiter
		$list = explode("\r\n",$data); 
		 //array_splice($list,0,1); //remove header
		$temp_list=array();

		if(isset($list[0]) === false){
			die('Error: Empty Row. Expected 3columns and Rows.');
		}

		foreach($list as $k => $v){
			if(strlen(trim($v)) === 0){continue;}
			$v = trim($v," \n\r\0\x0B");
			$v = explode("\t",$v); 
			if(sizeof($v) !== 3){
				die('Error: Invalid fields size at Row: ' .($k+1)
				.'<br>&nbsp Please input proper values copied from Excel.<br>
				&nbsp Expected 3 columns separated with Tabs and excluding Header at row/line 1');
			}
			
			//check item Date
			$itemDate = trim($v[2]);
			if(strlen($itemDate) !== 0){
				if(strlen($itemDate) <= 6){
				die('Error: Invalid date at Row ' . ($k + 1));
				} else {
					if(date_create($itemDate) === false){
						die('Error: Invalid date format at Row ' . ($k+1));
					} else {
						$v[2] = date_format(date_create($itemDate),'Y-m-d');
					}
				}
			}
			
			$temp_list[] = $v;
		}
		$list = $temp_list;
		unset($temp_list); unset($k); unset($v);
		
		$len = sizeof($list);
	
		$itemList = array();
		$itemCode = trim($list[0][0]);
		$itemName = trim($list[0][1]);
		$itemDate = trim($list[0][2]);
		$itemList[] = array($itemCode, $itemName, $itemDate, 1); //first item name, quantity 1
		$foundSame = false;
		for($r=1;$r<$len;++$r){
			$foundSame = false;
			$itemName_temp = trim($list[$r][1]);
			$len2 = sizeof($itemList);
			for($i=0;$i<$len2;++$i){
				$itemName = trim($itemList[$i][1]);
				if($itemName_temp === $itemName){
					$itemList[$i][3] += 1;
					$foundSame = true;
					break;
				}
			}
			if($foundSame === false){
				$itemList[] = array(
							trim($list[$r][0]), 
							trim($list[$r][1]), 
							trim($list[$r][2]),
							1);
			}
			
		}
		
		$numrow = sizeof($itemList);
		$data = "Total Number of Rows: $numrow <br>";
		
		$data .= '<table id="item_list">
				<thead>
				<tr>
					<th>Item Code</th><th>Product Name</th><th>Date</th><th>Quantity</th>
				</tr>
				</thead>
				<tbody>';
		$totalQuantity = 0;
		foreach($itemList as $r){
			$r[0] = htmlspecialchars(trim($r[0]), ENT_QUOTES, 'UTF-8');
			$r[1] = htmlspecialchars(trim($r[1]), ENT_QUOTES, 'UTF-8');
			$r[2] = htmlspecialchars(trim($r[2]), ENT_QUOTES, 'UTF-8');
			$data .= "
					<tr datevalue=\"$r[2]\">
					<td>$r[0]</td><td>$r[1]</td><td>$r[2]</td><td>$r[3]</td>
					</tr>";
			$totalQuantity += intval($r[3]);
		}

		$data .=  "</tbody>
				<tfoot>
				<tr>
					<td></td><td>Total items: </td><td></td><td id=\"totalQuantity\">$totalQuantity</td>
				</tr></tfoot></table>";
	}
	echo $data;
}


?>





<script>
	function select_date(){
		var selected_date = document.getElementById('selected_date');
		var table = document.getElementById('item_list');
		var r = table.getElementsByTagName('tr');
		var len = r.length -1; //excluding footer
		var totalQuantity = parseInt(document.getElementById('totalQuantity').innerHTML);

		if(selected_date.value !== null){
			totalQuantity = 0;
			//start from index 1 excluding header
			for(i=1;i<len;++i){
				var col = r[i].getElementsByTagName('td');
				var quantity = parseInt(col[3].innerText);
				//if no date is set, list all
				if(selected_date.value === ''){
					r[i].style.display = '';
					totalQuantity += quantity;
					continue;
				}

				//hide row that datevalue not match to the selected one
				var itemDate = r[i].getAttribute('datevalue');
				if(itemDate === selected_date.value){
					r[i].style.display = '';
					totalQuantity += quantity;
				} else {
					r[i].style.display = 'none';
				}
			}
			alert(totalQuantity);
		document.getElementById('totalQuantity').innerHTML = totalQuantity;
		}
	}
</script>

</body>
</html>