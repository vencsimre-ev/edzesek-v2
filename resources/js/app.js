import 'bootstrap';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-attendance-remove], [data-attendance-restore]')) {
        form.elements.participant_picker_open.value = document.getElementById('participant-picker')?.open ? '1' : '0';
    }
});

const checkInForm = document.getElementById('check-in-form');
if (checkInForm) {
    const button = checkInForm.querySelector('button[type="submit"]');
    const cooldown = checkInForm.querySelector('[data-check-in-cooldown]');
    const deadline = Date.now() + Number(cooldown.dataset.checkInCooldown) * 1000;
    const updateCooldown = () => {
        const remaining = Math.max(0, Math.ceil((deadline - Date.now()) / 1000));
        cooldown.querySelector('[data-check-in-seconds]').textContent = remaining;
        cooldown.hidden = remaining === 0;
        button.disabled = remaining > 0 || checkInForm.dataset.alreadyRecorded === '1';
    };
    updateCooldown();
    const timer = setInterval(() => {
        updateCooldown();
        if (Date.now() >= deadline) clearInterval(timer);
    }, 250);

    const nameInput = document.getElementById('check-in-name');
    if (nameInput) {
        try {
            if (!nameInput.value) nameInput.value = localStorage.getItem('attendeeName') || '';
        } catch {}
        checkInForm.addEventListener('submit', () => {
            try { localStorage.setItem('attendeeName', nameInput.value); } catch {}
        });
    }
}
