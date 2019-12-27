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