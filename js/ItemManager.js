$(document).ready(function () {

    const tblRecord = "table#ItemEditor";
    const th = tblRecord + ">thead>tr>th";
    const rows = tblRecord + ">tbody>tr";
    const tr = $(rows);

    let col;
    let cell;
    const targetColId = [
        "description"
    ];

    let cellEnterAction = function (e) {
        if (e.keyCode === 13) {
            e.preventDefault();
            $(e.target).focusout();
        }
    };

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
            cell.keypress(cellEnterAction);
            cell.children("input").change(function () {
                //leaving confirmation
                $(window).bind("beforeunload", function () {
                    return "gg";
                });
            })
        }
    });

    $("form#ItemEditorForm").submit(function () {
        if (confirm("confirm save changes?")) {
            $(window).unbind("beforeunload");
        } else {
            return false;
        }
    });

});
