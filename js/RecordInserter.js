function setConfirmLeave() {
    //leaving confirmation
    $(window).bind("beforeunload", function () {
        return "gg";
    });
}

function getDateString(TheDate) {
    let MonthVal = [
        "01", "02", "03", "04", "05", "06", "07", "08", "09", "10", "11", "12"
    ];
    let DateVal = TheDate.getUTCDate();
    if (DateVal < 10) {
        DateVal = '0' + DateVal;
    }
    return TheDate.getUTCFullYear() + '-' + MonthVal[TheDate.getUTCMonth()] + '-' + DateVal;
}

function getCountOrderNum(e) {
    const orderNum = this.value.trim();
    if (orderNum.length === 0) { return; }
    const input = $(this);
    $.ajax({
        type: "GET",
        url: "ajax/orders/getCountorderNum.php",
        data: { orderNum: orderNum },
        success: function (c) {
            if (c > 0) {
                input.addClass("error")
                    .attr("title", "Order Number exists");
            } else {
                input.removeClass("error")
                    .removeAttr("title");
            }
        }
    });
}

const tblRecord = "table#RecordInserter";
const th = tblRecord + ">thead>tr>th";
const rows = tblRecord + ">tbody>tr";
const tr = $(rows);

let orderNumCol = $(th + "#orderNum");
orderNumCol = $(th).parent().children().index(orderNumCol) + 1;
let dateCol = $(th + "#date");
dateCol = $(th).parent().children().index(dateCol) + 1;


let col;
let cell;
const targetColId = [
    "orderNum",
    "sku",
    "date",
    "trackingNum",
    "status",
    "sellingPrice",
    "shippingFee",
    "shippingFeeByCust"
];

let cellEnterAction = function (e) {
    if (e.keyCode === 13) {
        $(e.target).focusout();
        e.preventDefault();
    }
};

//bind cell action
const todayDate = getDateString(new Date());
targetColId.forEach(function (colId) {
    col = $(th + "#" + colId).index() + 1;
    for (i = 1; i <= tr.length; ++i) {
        cell = $(rows + ":nth-child(" + i + ")>td:nth-child(" + col + ")");
        cell.keypress(cellEnterAction)
        cell.children("input").change(setConfirmLeave);
    }
});

//bind cell function
$(rows + ":nth-child(1)>td:nth-child(" + orderNumCol + ")").children("input").keyup(getCountOrderNum);
$(rows + ":nth-child(1)>td:nth-child(" + dateCol + ")").children("input").val(todayDate);

//the following added row
const ROW = "<tr>" + $(tr)[0].innerHTML + "</tr>";
const LASTROW = tblRecord + ">tbody:last-child";
$("#btnAddNew").click(function () {
    $(LASTROW).append(ROW);
    let rIndex = $(tblRecord + ">tbody>tr").length - 1;
    for (i = 1; i <= targetColId.length; ++i) {
        $(tblRecord + ">tbody>tr:last>td:nth-child(" + i + ")").children("input")
            .attr("name", "r[" + rIndex + "][" + targetColId[i - 1] + "]")
            .keypress(cellEnterAction)
            .change(setConfirmLeave);
    }
    //bind cell function
    $(LASTROW + ">tr>td:nth-child(" + orderNumCol + ")").children("input").keyup(getCountOrderNum);
    $(LASTROW + ">tr>td:nth-child(" + dateCol + ")").children("input").val(todayDate);
});


$("form#RecordInserterForm").submit(function () {
    if (confirm("confirm add all orders?")) {
        $(window).unbind("beforeunload");
    } else {
        return false;
    }
});



// auto advanced to next input cell when press Enter key
document.body.addEventListener('keydown', function (e) {
    if (e.code !== 'Enter') return;

    const td = e.target.closest('td');
    if (td === null) return;
    const tr = td.closest('tr:last-child');
    if (tr === null) return;

    // 1. auto add new line, new row on last input
    document.getElementById('btnAddNew').click();
    setTimeout(() => {
        tr.nextElementSibling.children[td.cellIndex].querySelector('input').focus();
    }, 0);

});


setTimeout(_ => {
    // auto add few new line
    const btnAddNew = document.getElementById('btnAddNew');
    btnAddNew.click();
    btnAddNew.click();
    btnAddNew.click();
    btnAddNew.click();


    // auto focus 1st input
    requestAnimationFrame(_ => {
        setTimeout(_ => {
            document.querySelector('table#RecordInserter > tbody > tr input').focus();
        }, 200);
    });
}, 0);