(function () {

    const tblRecord = "table#lazadaOrders";
    const th = tblRecord + ">thead>tr>th";
    const rows = tblRecord + ">tbody>tr";
    const tr = $(rows);

    function highlightPaidPrice(){
        const SELLINGPRICE_COL = $(th + "#sellingPrice").index() + 1;
        const PAIDPRICE_COL = $(th + "#paidPrice").index() + 1;
        
        //check lazadaOrders table sellingPrice and paidPrice.
        //highlight if not equal
        let cellColor = '';
        for (i = 1; i <= tr.length; ++i) {
            let sellingPrice = parseFloat(
            $(rows + ":nth-child(" + i + ")>td:nth-child(" + SELLINGPRICE_COL + ")").text());

            let paidPrice = parseFloat(
            $(rows + ":nth-child(" + i + ")>td:nth-child(" + PAIDPRICE_COL + ")").text());
            
            if(paidPrice === sellingPrice){
                cellColor = 'greenyellow';
            } else {
                cellColor = 'pink';
            }
            $(rows + ":nth-child(" + i + ")>td:nth-child(" + PAIDPRICE_COL + ")")
                .attr("style", "background-color:" + cellColor);
        }
    }
    highlightPaidPrice();

    function highlightInvalidShippingFee2(){
        const SHIPPING_FEE = $(th + "#shippingFee").index() + 1;
        const SHIPPING_FEE2 = $(th + "#shippingFeeByWeight").index() + 1;

        let shippingFee = 0.00;
        let shippingFee2 = 0.00;
        let cellColor = 'pink';
        for(let i =1; i<tr.length;++i){
            shippingFee = parseFloat(
                $(rows + ":nth-child(" + i + ")>td:nth-child(" + SHIPPING_FEE + ")").text()
            );
            if(shippingFee > 0.00){
                continue;
            }

            shippingFee2 = parseFloat(
                $(rows + ":nth-child(" + i + ")>td:nth-child(" + SHIPPING_FEE2 + ")").text()
            );
            if(shippingFee2 > 0.00){
                continue;
            }

            $(rows + ":nth-child(" + i + ")>td:nth-child(" + SHIPPING_FEE2 + ")")
                .attr("style", "background-color:" + cellColor);
   
        }
    }
    highlightInvalidShippingFee2();

})();
