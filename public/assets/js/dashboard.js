document.addEventListener('DOMContentLoaded', async () => {
    const dataNode = document.querySelector('#dashboardData');
    if (!dataNode) return;

    const esc = (str) => {
        const div = document.createElement('div');
        div.textContent = String(str ?? '');
        return div.innerHTML;
    };

    const parseData = (attribute) => {
        const value = dataNode.dataset[attribute];
        if (!value) return [];
        try {
            return JSON.parse(value);
        } catch (error) {
            return [];
        }
    };

    let history = parseData('history');
    let goals = parseData('goals');
    let bottlenecks = parseData('bottlenecks');

    if (dataNode.dataset.profileId) {
        try {
            const payload = await App.request(`api/profiles.php?profile_id=${encodeURIComponent(dataNode.dataset.profileId)}&view=dashboard`);
            history = payload.history || history;
            goals = payload.goals || goals;
            bottlenecks = payload.bottlenecks || bottlenecks;
        } catch (error) {
            App.toast(error.message, 'warning');
        }
    }

    const goalsContainer = document.querySelector('#dashboardGoals');
    if (goalsContainer && goals.length) {
        goalsContainer.innerHTML = goals.slice(0, 5).map((goal) => {
            const current = Number(goal.current_progress || 0);
            const target = Number(goal.target_progress || 100);
            const width = target > 0 ? Math.min(100, (current / target) * 100) : 0;
            return `
                <div>
                    <div class="row spread"><span>${esc(goal.name)}</span><span>${esc(goal.status)}</span></div>
                    <div class="progress"><span style="width:${width}%"></span></div>
                </div>`;
        }).join('');
    }

    const bottlenecksContainer = document.querySelector('#dashboardBottlenecks');
    if (bottlenecksContainer && bottlenecks.length) {
        bottlenecksContainer.innerHTML = bottlenecks.slice(0, 5).map((item) => `
            <div class="resource-row"><span>${esc(item.item)}</span><strong>Missing ${Number(item.missing).toLocaleString()}</strong></div>`).join('');
    }

    const canvas = document.querySelector('#networthChart');
    if (canvas && typeof Chart !== 'undefined' && history.length) {
        new Chart(canvas, {
            type: 'line',
            data: {
                labels: history.map((point) => point.timestamp || ''),
                datasets: [
                    {
                        label: 'Networth',
                        data: history.map((point) => Number(point.networth || 0)),
                        borderColor: '#4f8cff',
                        backgroundColor: 'rgba(79, 140, 255, 0.15)',
                        tension: 0.2,
                        fill: true,
                    },
                ],
            },
            options: {
                plugins: { legend: { labels: { color: '#f5f7fa' } } },
                scales: {
                    x: { ticks: { color: '#9aa4b2' }, grid: { color: '#2b323d' } },
                    y: { ticks: { color: '#9aa4b2' }, grid: { color: '#2b323d' } },
                },
            },
        });
    }
});
