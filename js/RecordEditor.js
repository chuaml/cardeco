
const tblRecord = "table#RecordEditor";
const th = tblRecord + ">thead>tr>th";
const rows = tblRecord + ">tbody>tr";
const tr = $(rows);

let col;
let cell;
const targetColId = [
    "billno",
    "trackingNum",
    "shippingFee",
    "shippingFeeByCust",
    "voucher",
    "platformChargesAmount",
    "bankIn",
    "cash"
];

{ // unsave changes confirmation
    let hasChanges = false;
    window.addEventListener('beforeunload', function (e) {
        const currentInput = document.activeElement;
        try {
            currentInput.blur(); // focus emit last 'change' event
        } catch (err) { console.error(err); }

        if (hasChanges === false) return;
        e.preventDefault();
        this.dispatchEvent(new Event('pageshow')); // cancel loading screen
        this.setTimeout(_ => currentInput.focus(), 0); // focus back last 'change' input

        e.returnValue = 'abandon unsaved changes?';
        return 'abandon unsaved changes?';
    }, true);

    document.addEventListener('change', function (e) {
        hasChanges = true;
    }, { passive: true });

    document.addEventListener('submitted', function (e) {
        hasChanges = false;
    }, { passive: true });
}

//hideShowCol function
//bind to btn
function hideShowDetailCol() {
    targetColId.forEach(function (colId) {
        col = $(th + "#" + colId).index() + 1;
        $(th + "#" + colId).toggle(500);
        for (i = 1; i <= tr.length; ++i) {
            cell = $(rows + ":nth-child(" + i + ")>td:nth-child(" + col + ")");
            cell.toggle(500);
        }
    });
    setTimeout(function () {
        if (typeof (Storage) !== "undefined") {
            if ($(th + "#" + targetColId[0]).attr("style").includes("display: none")) {
                window.localStorage.setItem("hideShowDetailCol", "hide");
            } else {
                window.localStorage.setItem("hideShowDetailCol", "show");
            }
        }
    }, 500);

}
$("#btnHideShowCol").click(
    hideShowDetailCol
);

//auto trigger when page load
if (typeof (Storage) != "undefined") {
    if (window.localStorage.getItem("hideShowDetailCol") == null) {
        window.localStorage.setItem("hideShowDetailCol", "show");
    } else {
        if (window.localStorage.getItem("hideShowDetailCol") == "hide") {
            targetColId.forEach(function (colId) {
                col = $(th + "#" + colId).index() + 1;
                $(th + "#" + colId).hide();
                for (i = 1; i <= tr.length; ++i) {
                    cell = $(rows + ":nth-child(" + i + ")>td:nth-child(" + col + ")");
                    cell.hide();
                }
            });
        }
    }
}


//coloring for same orderNum
col = $(th + "#" + "orderNum").index() + 1;
let orderNumList = [];
for (i = 1; i <= tr.length; ++i) {
    cell = $(rows + ":nth-child(" + i + ")>td:nth-child(" + col + ")");
    if (orderNumList[cell.text()] === undefined) {
        orderNumList[cell.text()] = [];
    }
    orderNumList[cell.text()].push(i);

}

let OrderNumList = Object.keys(orderNumList);
let sameOrderClass = "object";
OrderNumList.forEach(function (orderNum) {
    let r = orderNumList[orderNum];
    if (r.length > 1) {
        r.forEach(function (i) {
            $(rows + ":nth-child(" + i + ")").addClass(sameOrderClass);
        });
        sameOrderClass = sameOrderClass === 'object' ? 'object2' : 'object';
    }
});
