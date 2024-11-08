
// sort row by clicking header
document.body.addEventListener('click', async function (e) {
    // assert: table > thead > tr
    if (e.target.matches('th:not([onclick])') === false) return;
    const th = e.target;
    const rows = th.closest('table').querySelectorAll('tbody > tr');
    const tbody = rows[0].parentElement;

    const _getText = (_ => {
        const td = rows[0].children[th.cellIndex];
        if (td.dataset.value) { // 1. prioritize use and sort by [date-value] text
            return function (td) {
                return td.dataset.value;
            }
        }

        const input = td.querySelector('input,textarea');
        if (input === null) { // 2. sort by innerText | number
            if (isNaN(parseFloat(td.innerText)) === false) {
                return function (td) { // 2.a sort by number
                    return parseFloat(td.innerText);
                };
            }
            else {
                return function (td) { // 2.b sort by innerText
                    return td.innerText;
                };
            }

        }
        else { // 3. sort by input[type],textarea value
            return function (td) {
                return td.querySelector('input,textarea').value;
            };
        }
    })();
    const sortedRow = (_ => {
        if (th.dataset['sortAsc'] === '0') {
            th.parentElement.querySelectorAll('[data-sort-asc]').forEach(th => th.removeAttribute('data-sort-asc'));
            th.dataset['sortAsc'] = '1';
            return Array.prototype.toSorted.call(rows, (tr1, tr2) => {
                const a = _getText(tr1.children[th.cellIndex]);
                const b = _getText(tr2.children[th.cellIndex]);
                if (a > b) {
                    return 1;
                }
                else if (a < b) {
                    return -1;
                }
                else {
                    return 0;
                }
            });
        }
        else {
            th.parentElement.querySelectorAll('[data-sort-asc]').forEach(th => th.removeAttribute('data-sort-asc'));
            th.dataset['sortAsc'] = '0';
            return Array.prototype.toSorted.call(rows, (tr1, tr2) => {
                const a = _getText(tr1.children[th.cellIndex]);
                const b = _getText(tr2.children[th.cellIndex]);
                if (a > b) {
                    return -1;
                }
                else if (a < b) {
                    return 1;
                }
                else {
                    return 0;
                }
            });
        }
    })();

    await new Promise(re => setTimeout(re, 0)); // allow UI update and move a bit

    rows.forEach(function (x) {
        tbody.removeChild(x);
    });
    sortedRow.forEach(function (x) {
        tbody.append(x);
    });

}, { passive: true });


// draggable column
document.addEventListener('readystatechange', function (ev) {
    if (ev.target.readyState !== 'interactive') return;

    let dragged_th = null;
    const handle_dragstart = e => {
        if (('closest' in e.target) === false) return;
        dragged_th = e.target.closest('th');
        dragged_th.classList.add('dragstart');
    };
    const handle_dragend = e => {
        if (dragged_th === null) return;
        dragged_th.classList.remove('dragstart');
        dragged_th = null;
    };

    const handle_dragover = e => { e.preventDefault(); };

    let dragenter_pid = 0;
    const handle_drop = e => {
        if (dragged_th === e.target || dragged_th === null) return;
        clearTimeout(dragenter_pid);
        const dropAt_th = e.target;
        const dropped_th_index = dropAt_th.cellIndex;
        const dragged_th_index = dragged_th.cellIndex;
        const moveColumn = (_ => {
            if (dragged_th_index < dropped_th_index) { // move to right
                return (tr, td, td2) => tr.insertBefore(td, td2.nextSibling);
            }
            else { // move to left
                return (tr, td, td2) => tr.insertBefore(td, td2);
            }
        })();

        moveColumn(dropAt_th.parentElement, dragged_th, dropAt_th);

        dropAt_th.closest('table').querySelectorAll('tbody > tr').forEach(tr => {
            const td_from = tr.children[dragged_th_index];
            const td_to = tr.children[dropped_th_index];
            moveColumn(tr, td_from, td_to);
        });

    };

    const handle_dragenter = e => {
        if (dragged_th === e.target || dragged_th === null) return;
        clearTimeout(dragenter_pid);
        dragenter_pid = setTimeout(_ => {
            handle_drop(e);
        }, 200);
    };
    document.body.querySelectorAll('table > thead > tr > th').forEach(th => {
        th.draggable = true;
        th.addEventListener('dragstart', handle_dragstart);
        th.addEventListener('dragend', handle_dragend);

        th.addEventListener('dragover', handle_dragover);
        // th.addEventListener('drop', handle_drop);
        th.addEventListener('dragenter', handle_dragenter);
    });
});
