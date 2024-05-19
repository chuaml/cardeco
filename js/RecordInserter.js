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



// auto add new line, new row on last input
document.body.addEventListener('keydown', function (e) {
    if (e.code !== 'Enter') return;
    if (e.target.matches('tr:last-child > td:last-child > input') === true) {
        document.getElementById('btnAddNew').click();
        setTimeout(() => {
            e.target.closest('tr').nextElementSibling.querySelector('input').focus();
        }, 0);
    }
    else if (e.target.matches('td > input') === true) {
        for (
            let td = e.target.closest('td').nextElementSibling; ; td = td.nextElementSibling
        ) {
            if(td === null){
                const tr = e.target.closest('tr').nextElementSibling;
                if(tr === null){
                    break;
                }
                else {
                    td = tr.children[0];
                }
            }
            const nextInput = td.querySelector('input:not(:read-only):not(:disabled)');
            if (nextInput === null) continue;
            nextInput.focus();
            break;
        }
    }
});
