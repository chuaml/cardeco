//  WARNING
// code below assume the page has 1 and only 1 table[cd-editable-sheet]
const body = document.body;
const tbody = body.querySelector('table[cd-editable-sheet] > tbody');

// auto format currency on input, when currency is entered
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

// movement key for cell focus 
tbody.addEventListener('keydown', function (e) {
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


// force double click input only to allow focus and edit
tbody.addEventListener('dblclick', function (e) {
    if (e.target.matches('input') === false) return;
    e.target.removeAttribute('readonly');
});
tbody.addEventListener('focusout', function (e) {
    if (e.target.matches('input') === false) return;
    e.target.setAttribute('readonly', '');
});


// format money number to well format numeric decimal only before submitting to server
body.addEventListener('submit', function (e) {
    if (e.target.closest('table[cd-editable-sheet] > tbody') === null) return;

    e.target.querySelectorAll('input.money').forEach(function (x) {
        x.value = x.value.replace(/[^0-9\.]+/g, '');
    });
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
