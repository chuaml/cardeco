
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


targetColId.forEach(function (colId) {
    col = $(th + "#" + colId).index() + 1;
    for (i = 1; i <= tr.length; ++i) {
        cell = $(rows + ":nth-child(" + i + ")>td:nth-child(" + col + ")");
        cell.dblclick(function () {
            $(this).children("input").attr("readonly", false)
                .focus()
                .select();
        });
        cell.focusout(function () {
            $(this).children("input").attr("readonly", true);
        });
        cell.children("input").change(function () {
            //leaving confirmation
            $(window).bind("beforeunload", function () {
                return "gg";
            });
        })
    }
});

$("form#RecordEditorForm").submit(function () {
    if (confirm("confirm save changes?")) {
        $(window).unbind("beforeunload");
    } else {
        return false;
    }
});


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

document.querySelector('table > tbody').addEventListener('keydown', function (e) {
    if (e.target.matches('input') === false) return;

    const td = e.target.closest('td');
    const tr = td.parentElement;

    const goUp = _ => {
        const tr_above = tr.previousElementSibling;
        if (tr_above !== null) {
            tr_above.children[td.cellIndex].querySelector('input').focus();
        }
    };
    const goDown = _ => {
        const tr_below = tr.nextElementSibling;
        if (tr_below !== null) {
            tr_below.children[td.cellIndex].querySelector('input').focus();
        }
    };

    if (e.code === 'ArrowUp') {
        e.target.blur();
        goUp();
    }
    else if (e.code === 'ArrowDown') {
        e.target.blur();
        goDown();
    }
    else if (e.code === 'ArrowLeft') {
        e.target.blur();
        let cellIndex = td.cellIndex;
        while (cellIndex-- > 0) {
            const input = tr.children[cellIndex].querySelector('input');
            if (input !== null) {
                input.focus();
                break;
            }
        }
    }
    else if (e.code === 'ArrowRight') {
        e.target.blur();
        const len = tr.children.length - 1;
        let cellIndex = td.cellIndex;
        while (cellIndex++ < len) {
            const input = tr.children[cellIndex].querySelector('input');
            if (input !== null) {
                input.focus();
                break;
            }
        }
    }
    else if (e.code === 'Enter') {
        e.preventDefault(); // prevent form submission to save changes

        const input = e.target;
        console.log(input.readOnly);
        if (input.readOnly === false) { // is focus, on edit
            input.setAttribute('readonly', '');
            input.blur();
            if (e.shiftKey === true) { // move cell cursor
                goUp()
            }
            else {
                goDown();
            }
        }
        else {
            if (e.ctrlKey === true) { // focus and start editing cell
                input.removeAttribute('readonly');
                input.focus();
            }
            else { // move cell cursor
                if (e.shiftKey === true) {
                    goUp()
                }
                else {
                    goDown();
                }
            }
        }
    }
    else if (e.code.startsWith('key') && e.code.length === 4) { // any other key, focus and start editing cell, start typing

        if (e.target.readOnly === true) {
            e.target.removeAttribute('readonly');
            e.target.focus();
            e.target.select();
        }

    }

});