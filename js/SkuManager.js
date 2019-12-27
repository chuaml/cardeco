$(document).ready(function () {
    function getItemCode(itemCode) {
        $.ajax({
            type: "GET",
            url: "ajax/stock_items/getItemCode.php",
            data: { itemCode: itemCode },
            dataType: "json",
            success: function (data) {
                if (Array.isArray(data)) {
                    $("#itemCodeList").children().remove();
                    for (i = 0; i < data.length; ++i) {
                        $("#itemCodeList").append(
                            '<option value="' + data[i] + '"></option>'
                        );
                    }
                }
            },
        });
    }

    function getItemCodeSku(itemCode) {
        $.ajax({
            type: "GET",
            url: "ajax/seller_sku/getItemCodeSku.php",
            data: { itemCode: itemCode },
            dataType: "json",
            success: function (data) {
                if (Array.isArray(data)) {
                    $("#skuList").children().remove();
                    if (data.length > 0) {
                        $("#skuList").append(
                            '<p>check for delete: <input type="submit" name="submit" value="delete" /><p>'
                        );
                    }
                    for (i = 0; i < data.length; ++i) {
                        $("#skuList").append(
                            '<li><label for="delSku' + data[i] + '">' + data[i] + '</label>'
                            + '<input id="delSku' + data[i] + '" type="checkbox" class="checkbox" name="skuList[]" value="'
                            + data[i] + '" /></li>'
                        );
                    }
                }
            },
        });
    }

    function getCountSku(sku) {
        $.ajax({
            type: "GET",
            url: "ajax/seller_sku/getItemCodeBySku.php",
            data: { sku: sku },
            success: function (data) {
                if (data.trim().length > 0) {
                    $("form>input#sku").addClass('error');
                    $("span#SkuItemCode").text("*has been added to: " + data);
                } else {
                    $("form>input#sku").removeClass('error');
                    $("span#SkuItemCode").text('');
                }
            },
        });
    }

    //binding
    $("form>input#itemCode").keyup(function (e) {
        let v = e.currentTarget.value.trim();
        if (v.length > 0) {
            getItemCode(v);
            getItemCodeSku(v);
        }
    });

    $("form>input#sku").keyup(function (e) {
        let v = e.currentTarget.value.trim();
        if (v.length > 0) {
            getCountSku(v);
        }
    });
});
