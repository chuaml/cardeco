
setTimeout(_ => { // unsave changes confirmation
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

    document.querySelector('table').addEventListener('change', function (e) {
        hasChanges = true;
    }, { passive: true });

    document.addEventListener('submitted', function (e) {
        hasChanges = false;
    }, { passive: true });
}, 0);