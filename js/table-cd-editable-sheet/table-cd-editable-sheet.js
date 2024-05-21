//  WARNING
// code below assume the page has 1 and only 1 table[cd-editable-sheet]
const body = document.body;
const tbody = body.querySelector('table[cd-editable-sheet] > tbody');

// auto adjust column size if too small
tbody.querySelectorAll('tr>td>input').forEach(function (input) {
    if (input.value) {
        input.style['min-width'] = input.value.length + 'ch';
    }
});
tbody.addEventListener('change', function (e) {
    if (e.target.matches('input') === false) return;
    e.target.style['min-width'] = e.target.value.length + 'ch';
});

// movement key for cell focus 
const gotoRowCell = (tr, td_cellIndex) => {
    if (tr !== null) {
        tr.children[td_cellIndex].querySelector('input').focus();
    }
};

tbody.addEventListener('keydown', function (e) {
    if (e.ctrlKey === true) {
        if (e.shiftKey === false) {  // ctrl + delete
            if (e.code === 'Delete') {
                e.target.value = '';
            }
        }
    }
    else { // ctrlKey === false

        if (e.target.matches('input') === false) return;

        const td = e.target.closest('td');
        const tr = td.parentElement;

        const code = e.code;

        const input = e.target;
        if (code === 'Enter') {
            e.preventDefault(); // prevent form submission to save changes

            if (input.readOnly === false) { // is focus, on edit
                input.setAttribute('readonly', '');
                input.blur();
                if (e.shiftKey === true) { // move cell cursor
                    gotoRowCell(tr.previousElementSibling, td.cellIndex)
                }
                else {
                    gotoRowCell(tr.nextElementSibling, td.cellIndex);
                }
            }
            else {
                if (e.ctrlKey === true) { // focus and start editing cell
                    input.removeAttribute('readonly');
                    input.focus();
                }
                else { // move cell cursor
                    if (e.shiftKey === true) {
                        gotoRowCell(tr.previousElementSibling, td.cellIndex)
                    }
                    else {
                        gotoRowCell(tr.nextElementSibling, td.cellIndex);
                    }
                }
            }
            return;
        }
        else if (code === 'Escape') {
            input.setAttribute('readonly', '');
            input.blur();
        }
        else if (
            code.startsWith('Key')
            || code.startsWith('Digit')
            || code.startsWith('Numpad')
        ) { // alphanumeric keys, focus and start editing cell, start typing

            if (e.target.readOnly === true) {
                e.target.removeAttribute('readonly');
                e.target.focus();
                e.target.select();
            }
            return;
        }

        if (e.target.readOnly === true) {
            if (code === 'ArrowUp') {
                e.target.blur();
                gotoRowCell(tr.previousElementSibling, td.cellIndex);
            }
            else if (code === 'ArrowDown') {
                e.target.blur();
                gotoRowCell(tr.nextElementSibling, td.cellIndex);
            }
            else if (code === 'ArrowLeft') {
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
            else if (code === 'ArrowRight') {
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
        }

        if (e.shiftKey === false) { // no ctrl, delete
            if (code === 'Delete') {
                e.target.value = '';
            }
        }
    }

});

// Ctrl S to save
body.addEventListener('keydown', e => {
    if (e.ctrlKey === true && e.code === 'KeyS') {
        const table = e.target.closest('table[cd-editable-sheet]');
        if (table === null) return;

        const form = table.closest('form');
        if (form === null) return;

        form.requestSubmit();
        e.preventDefault();
    }
});



// force double click input only to allow focus and edit
tbody.addEventListener('dblclick', function (e) {
    if (e.target.matches('input') === false) return;
    e.target.removeAttribute('readonly');
});
tbody.addEventListener('focusout', function (e) {
    if (e.target.matches('input') === false) return;
    e.target.setAttribute('readonly', '');
});

// and auto change all input to readonly, only allow double clicking to focus and edit it
tbody.querySelectorAll('td > input').forEach(function (x) {
    x.readOnly = true;
});


// money number formatting
// 1. auto format currency on input, when currency is entered
tbody.addEventListener('change', function (e) {
    if (e.target.matches('input.money') === false) return;
    const input = e.target;
    if (input.value === '') return;

    const money = parseFloat(e.target.value.replace(/[^0-9\.]+/g, ''));
    if (isNaN(money) === true) return;

    // e.g. 123,456,789.00
    input.value = money.toLocaleString('en-US', {
        minimumFractionDigits: 2
    });
});

// 2. then format money number to well format numeric decimal only before submitting to server
body.addEventListener('submit', function (e) {
    if (e.target.closest('table[cd-editable-sheet] > tbody') === null) return;

    e.target.querySelectorAll('input.money').forEach(function (x) {
        x.value = x.value.replace(/[^0-9\.]+/g, '');
    });
});

// maybe needed feature
// if (changeEnterKeyTo_MoveAdjecnt) {
//     document.body.addEventListener('keydown', function (e) {
//         if (e.code !== 'Enter') return;
//         if (e.target.matches('td > input:focus') === true) {
//             e.preventDefault();
//             if (e.shiftKey === true) { // 2.a go to previous input cell
//                 for (
//                     let td = e.target.closest('td').previousElementSibling, step; step < 10000; ++step
//                 ) {
//                     if (td.previousElementSibling === null) {
//                         const tr = td.closest('tr').previousElementSibling;
//                         if (tr === null) {
//                             break;
//                         }
//                         else {
//                             td = tr.children[tr.children.length - 1];
//                         }
//                     }
//                     else {
//                         td = td.previousElementSibling;
//                     }

//                     const nextInput = td.querySelector('input:not(:read-only):not(:disabled)');
//                     if (nextInput === null) continue;
//                     nextInput.focus();
//                     break;
//                 }
//             }
//             else { // 2.b go to next input cell
//                 for (
//                     let td = e.target.closest('td').nextElementSibling, step = 0; step < 10000; ++step
//                 ) {
//                     if (td.nextElementSibling === null) {
//                         const tr = nextElementSibling.closest('tr').nextElementSibling;
//                         if (tr === null) {
//                             break;
//                         }
//                         else {
//                             td = tr.children[0];
//                         }
//                     }
//                     else {
//                         td = td.nextElementSibling;
//                     }
//                     const nextInput = td.querySelector('input:not(:read-only):not(:disabled)');
//                     if (nextInput === null) continue;
//                     nextInput.focus();
//                     break;
//                 }
//             }
//         }
//     });
// }