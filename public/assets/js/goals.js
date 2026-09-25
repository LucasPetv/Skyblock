document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('#requirementsContainer');
    const addRequirementButton = document.querySelector('#addRequirement');
    let requirementIndex = container?.querySelectorAll('.requirement-row').length || 1;

    addRequirementButton?.addEventListener('click', () => {
        const row = document.createElement('div');
        row.className = 'requirement-row';
        row.innerHTML = `
            <select name="requirements[${requirementIndex}][type]">
                <option value="skill">Skill</option>
                <option value="collection">Collection</option>
                <option value="dungeon">Dungeon</option>
                <option value="item">Item</option>
                <option value="stat">Stat</option>
            </select>
            <input type="text" name="requirements[${requirementIndex}][key]" placeholder="Key">
            <input type="number" name="requirements[${requirementIndex}][value]" placeholder="Target" min="0" step="0.01">
        `;
        container?.appendChild(row);
        requirementIndex += 1;
    });

    document.querySelector('#goalForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.currentTarget;
        const formData = new FormData(form);
        try {
            const payload = await App.request('api/goals.php', { method: 'POST', body: formData });
            App.toast(payload.message, 'success');
            window.location.reload();
        } catch (error) {
            App.toast(error.message, 'danger');
        }
    });

    document.querySelectorAll('[data-delete-goal]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (!window.confirm('Delete this goal?')) return;
            try {
                const payload = await App.request('api/goals.php', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: button.dataset.deleteGoal, _token: App.csrfToken() }),
                });
                App.toast(payload.message, 'success');
                button.closest('.goal-row')?.remove();
            } catch (error) {
                App.toast(error.message, 'danger');
            }
        });
    });
});
