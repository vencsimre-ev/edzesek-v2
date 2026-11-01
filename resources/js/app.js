import 'bootstrap';

document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.matches('[data-attendance-remove]')) {
        form.elements.participant_picker_open.value = document.getElementById('participant-picker')?.open ? '1' : '0';
    }
});
