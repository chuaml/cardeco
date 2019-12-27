$(document).ready(function(){

    $("#btn_itemList_csv").click(function(){
        $("#itemList_csv").delay(0).toggle(250);
    });
/*
    $(".paper").mouseover(function(){
        $(".paper").css("background-color", "yellow");
    });
*/

    $("button").click(function(event){
        $("#"+event.target.name).toggle(250);
        
        var txt = $("#"+event.target.id).text();
        if(txt === 'Show'){
            $("#"+event.target.id).text('Hide');
        } else if(txt === 'Hide'){
            $("#"+event.target.id).text('Show');
        }
    });
	
	var liststock = $('#liststock tr > td:nth-child(5)');
	var len = liststock.length;
	for(i=0;i<len;++i){
		if(parseInt(liststock[i].innerHTML) <= 0){
			liststock[i].className = 'nostock';
		}
	}
    /*
    var radiobtn = 'itemcode';
    $("#btnSubmit").click(function(event){
        var target = 'itemcode';
        if(typeof(Storage) !== "undefined") {
            if(localStorage.getItem('searchTarget') != null){
                localStorage.setItem("searchTarget", "itemcode");
            }
            // Store
            
            // Retrieve
            document.getElementById("result").innerHTML = localStorage.getItem("searchTarget");
        }
    });
*/

    
});
