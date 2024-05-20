
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