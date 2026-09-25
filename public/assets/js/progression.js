document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.progression-card').forEach((card) => {
        card.addEventListener('click', () => card.classList.toggle('is-expanded'));
    });
});
