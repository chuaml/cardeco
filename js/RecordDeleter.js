$(document).ready(function () {

    function getOrdersByInsertLogId(insertLogId) {
        $.ajax({
            type: "GET",
            url: "ajax/orders/getOrdersByInsertLogId.php",
            data: { insertLogId: insertLogId },
            success: function (c) {
                $("#ordersMonthlyRecord").html(
                    c.length + " records: <br>"
                    + displayTable(c)
                );
            }
        });
    }

    function displayTable(insertLogRecords) {
        let COL;
        if (insertLogRecords[0] === undefined) {
            COL = [];
        } else {
            COL = Object.keys(insertLogRecords[0]);
        }

        let thead = "<thead><tr>" + COL.map(field => "<th>" + field + "</th>").join('') + "</tr></thead>";

        let tbody = "<tbody>"
            + insertLogRecords.map(r => "<tr><td>" + Object.values(r).join("</td><td>") + "</td></tr>").join('')
            + "</tbody>";

        return "<table>" + thead + tbody + "</table>";
    }


    $("#insertLogId").on('input', function () {
        let val = this.value.trim();
        if ($('#insertLogIdList option').filter(function () {
            return this.value.toUpperCase() === val.toUpperCase();
        }).length) {
            getOrdersByInsertLogId(val);
        }
    });
    $("#insertLogId").click(function () {
        this.select();
    });

    $("form#deleteRecord").submit(function () {
        if (confirm("confirm to delete all these records?")) {
            $(window).unbind("beforeunload");
        } else {
            return false;
        }
    });


});
