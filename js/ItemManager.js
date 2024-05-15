const confirmUnsaveChanges = function (e) {
    e.preventDefault();
    return 'Are you sure to leave unsaved changes?';
};

// on 1st edit action, init
document.querySelector('form#ItemEditorForm').addEventListener('input', function (e) {
    if (e.target.matches('[contenteditable]') === false) return;
    window.addEventListener('beforeunload', confirmUnsaveChanges);
}, { once: true });

let lastForcusedInput = null;
document.querySelector('form#ItemEditorForm').addEventListener('input', function (e) {
    if (e.target.matches('[contenteditable]') === false) return;
    lastForcusedInput = e.target;
});

document.querySelector('form#ItemEditorForm').addEventListener('submit', function (e) {
    if (lastForcusedInput !== null && confirm("Confirm save changes?") === true) {
        window.removeEventListener('beforeunload', confirmUnsaveChanges);
    } else {
        lastForcusedInput.focus();
        e.preventDefault();
        return false;
    }

    e.target.querySelectorAll('table > tbody > tr > td[contenteditable]').forEach(function (td) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = td.dataset.name;
        input.length = td.dataset.maxLength || 255;
        input.value = td.innerText;

        td.append(input);
    });
});

document.addEventListener('keydown', e => {
    if (e.ctrlKey === true && e.code === 'KeyS') {
        e.preventDefault();
        document.querySelector('form#ItemEditorForm').requestSubmit();
    }
});