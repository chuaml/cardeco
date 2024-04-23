function tableToList(domTable) {
	const keys = domTable.querySelectorAll('thead > tr > th');

	const list = [];
	domTable.querySelectorAll('tbody > tr').forEach(r => {
		const x = {};
		r.querySelectorAll('td').forEach((td, i) => {
			x[keys[i].id] = td.innerText;
		});
		list.push(x);
	});
	return list;
}



function readURL(input) {
	if (input.files && input.files[0]){
		var reader = new FileReader();
	
		reader.onload = function (e) {
		$('#newimage')
			.attr('src', e.target.result)
			.width(420)
			.height(420);
		$('#newimage_container').show(500);
		};

	reader.readAsDataURL(input.files[0]);
	}
}

function printPaper(){
	let newTab;
	let divItemList = document.getElementById('itemList');
	let divNotFound = document.getElementById('notfound');

	let itemList = divItemList.getElementsByTagName('table')[0];
	let notfound = divNotFound.getElementsByTagName('table')[0];

	let th = itemList.getElementsByTagName('thead')[0].innerHTML;
	let itemList_rows = itemList.getElementsByTagName('tbody')[0].innerHTML;
	let notfound_rows = notfound.getElementsByTagName('tbody')[0].innerHTML;

	let len = 0;
	let row;
	let qty_row;
	let current_qty;

	let output = '<table border="1">';
	let style = '<style>body{width: 210mm;} table{width: 210mm; font-size: 18px;} td:nth-child(2){width: 60%}</style>';
	
	output += '<thead>'
		+ th
		+ '</thead><tbody>'
		+ itemList_rows 
		+ notfound_rows
		+ '</tbody>';

	//calculate total
	const colCount = itemList.querySelector('tr').querySelectorAll('th').length;
	const quantityIndex = 2;
	const columnTotals = [];
	for (let c = quantityIndex; c < colCount; ++c) {
		let total = 0;
		row = itemList.getElementsByTagName('tr');
		len = row.length;
		for (i = 1; i < len; ++i) {
			let td = row[i].getElementsByTagName('td');
			qty_row = td[c];
			current_qty = parseInt(qty_row.innerText);
			if (current_qty >= 1) {
				total += current_qty;
			}
		}

		row = notfound.getElementsByTagName('tr');
		len = row.length;
		for (i = 1; i < len; ++i) {
			let td = row[i].getElementsByTagName('td');
			qty_row = td[c];
			current_qty = parseInt(qty_row.innerText);
			if (current_qty >= 1) {
				total += current_qty;
			}
		}
		columnTotals.push(total);

	}
	output += '<tr><td></td>'
		+ '<td>Total: </td> <td>'
		+ columnTotals.join('</td><td>')
		+ '</td></tr>'
		+ '</table>';
	//

	output += style;

	newTab = window.open();
	newTab.document.writeln(output);
	newTab.print();
}

function reportStock(){
	let newTab;
	let itemList = document.getElementById('listdb');
	let itemList_th = itemList.getElementsByTagName('th');
	let itemList_td = itemList.getElementsByTagName('td');
	let qty_list = document.getElementsByName('order_qty[]');
	let k = 0;
	let col = 0;
	let qty = 0;
	let qty_key = 5;
	let len;

	let total_qty = 0;
	let total_row = 0;

	let output = '<h2>AK LEE ORDER</h2><table border="1">';
	let style = '<style>body{width: 210mm;} table{width: 210mm; font-size: 20px;}</style>';
	let buffer = '<tr>';
	
	//th
	len = itemList_th.length;
	for(i=0;i<len;++i){
		buffer += '<th>' + itemList_th[i].innerText + '</th>';
	}
	buffer += '</tr>';
	output += buffer;

	//followings td cells
	buffer = '<tr>';
	len = itemList_td.length;
	k = 0;
	col = 0
	for(i=0;i<len;++i){
		if(col === qty_key){ //on qty column
			qty = parseInt(qty_list[k].value);
			if(qty > 0){
				buffer += '<td>' + qty + '</td></tr>';
				output += buffer;
				++total_row;
				total_qty += qty;
			}

			buffer = '<tr>';
			k += 1;
			col = 0;
			continue;
		}
		buffer += '<td>' +itemList_td[i].innerText +'</td>';
		++col;
	}
	output += '<tr><td></td>' 
		+'<td>Number Items: </td><td>' 
		+total_row 
		+'</td><td></td><td>Total Order: </td><td>' 
		+total_qty 
		+'</td></tr></table>';

	output += style;
	
	newTab = window.open();
	newTab.document.writeln(output);
}

function orderAll_stockreport(quantity = 0, reset = false){
	var order_qty = parseInt(quantity);
	var zero_stock = $('#listdb tr > td:nth-child(5)');
	var stock;
	var len;

	if(order_qty < 0){
		order_qty = 0;
	}

	if(reset === false){
		len = zero_stock.length;
		for(i=0;i<len;++i){
			stock = parseInt(zero_stock[i].innerText);
			if(stock === 0){
				document.getElementsByName('order_qty[]')[i].value = order_qty;
			}
		}
	} else {
		len = document.getElementsByName('order_qty[]').length;
		for(i=0;i<len;++i){
				document.getElementsByName('order_qty[]')[i].value = null;
		}
	}
}

function setTxtInput(){
	var txtInput = document.getElementById('txtInput');
	var text = txtInput.value.trim();
	txtInput.value = text;
	if (typeof(Storage) !== "undefined") {
		window.localStorage.setItem("txtInput", text);
	}
}

function getTxtInput(){
	var txtInput = document.getElementById('txtInput');
	if(txtInput !== undefined){
		if (typeof(Storage) !== undefined) {
			var text = window.localStorage.getItem("txtInput");
			if(text !== null){
				txtInput.value = text;
				txtInput.select();
			} else {
				window.localStorage.setItem("txtInput", '');
			}
		}
	}
}

function setChkShowImage(){
	var chkShowImage = document.getElementById('chkShowImage');
	if(chkShowImage !== undefined){
		if (typeof(Storage) !== undefined) {
			window.localStorage.setItem("chkShowImage", chkShowImage.checked);
		}
	}
	
}

function getChkShowImage(){
	var chkShowImage = document.getElementById('chkShowImage');
	if(chkShowImage !== undefined){
		if (typeof(Storage) !== undefined) {
			var text = window.localStorage.getItem("chkShowImage");
			if(text == 'false'){
				chkShowImage.checked = false;
			} else {
				chkShowImage.checked = true;
			}

		}
	}
}

let allchkChecked = false;
function setAllCheckboxes(header_cell){
	let c = header_cell.cellIndex;
	let liststock = document.getElementById('liststock');
	let row = liststock.getElementsByTagName('tr');
	let col, input;
	let num_rows = row.length;
	
	for(i=1;i<num_rows;++i){
		col = row[i].getElementsByTagName('td')[c];
		input = col.getElementsByTagName('input')[0];
		if(allchkChecked === true){
			input.checked = false;
		} else {
			input.checked = true;
		}
	}
	allchkChecked = !allchkChecked;
}




