'use strict';

const INTERVAL = 10000;

const infoBlock = document.querySelector('.info');
if (!infoBlock) {
    console.log('Требуется авторизация');
} else {
    load();
    setInterval(load, INTERVAL);
}

document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', async () => {
        const tabName = tab.dataset.tab;
        
        location.hash = '#' + tabName;

    });
});

async function switchTab(tabName){
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
    
    const activeTab = document.querySelector(`.tab[data-tab="${tabName}"]`);
    const activeContent = document.getElementById('tab-' + tabName);
    
    if (activeTab) activeTab.classList.add('active');
    if (activeContent) activeContent.style.display = 'block';
    
    if (tabName === 'charts') await loadCharts();
    if (tabName === 'table') load();
}

const hash = location.hash.replace('#', '') || 'table';
switchTab(hash);

window.addEventListener('hashchange', () => {
    const tabName = location.hash.replace('#', '') || 'table';
    switchTab(tabName);
});

const chartInstances = {};

async function loadCharts() {
    try {
        const res = await fetch('index.php?action=history&limit=30');
        const history = await res.json();
        
        const labels = history.map(row => row.timestamp.slice(-8));
        
        const datasets = [
            { param: 'flue_gas_temp', label: 'Температура уходящих газов', color: '#ef4444', chartId: 'chartFlueGas' },
            { param: 'steam_pressure', label: 'Давление пара', color: '#3b82f6', chartId: 'chartSteamPressure' },
            { param: 'gas_flow', label: 'Расход газа', color: '#22c55e', chartId: 'chartGasFlow' },
        ];
        
        datasets.forEach(({ param, label, color, chartId }) => {
            const values = history.map(row => row[param]);
            renderChart(chartId, label, labels, values, color);
        });
        
        const efficiency = history.map(row => {
            const q2 = (row.flue_gas_temp - 20) / 100 * 5;
            return (100 - q2).toFixed(1);
        });
        renderChart('chartEfficiency', 'КПД', labels, efficiency, '#f59e0b');
        
    } catch (e) {
        console.error('Ошибка загрузки графиков:', e);
    }
}

function renderChart(canvasId, label, labels, data, color) {
    const ctx = document.getElementById(canvasId).getContext('2d');
    
    if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderColor: color,
                backgroundColor: color + '20',
                borderWidth: 2,
                pointRadius: 2,
                pointHoverRadius: 5,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                x: {
                    ticks: { color: '#94a3b8', maxTicksLimit: 10 },
                    grid: { color: 'rgba(255,255,255,0.05)' }
                },
                y: {
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    beginAtZero: false
                }
            }
        }
    });
}

async function load() {
    try {
        const res = await fetch('index.php?action=monitor');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const json = await res.json();

        document.getElementById('boilerName').textContent = 
            'Котёл: ' + json.boiler + ' (' + json.timestamp + ')';

        const tbody = document.querySelector('#dataTable tbody');
        tbody.innerHTML = '';

        for (let param in json.deviations) {
            const d = json.deviations[param];
            const row = document.createElement('tr');
            if (d.status === '⚠️') row.classList.add('warn');
            row.innerHTML = `
                <td>${param}</td>
                <td>${d.fact}</td>
                <td>${d.ref}</td>
                <td>${d.dev}</td>
                <td>${d.status}</td>
            `;
            tbody.appendChild(row);
        }
    } catch (e) {
        console.error('Ошибка:', e);
        document.getElementById('boilerName').textContent = 'Ошибка загрузки данных';
    }
}

function showMessage(type, text, duration = 4000) {
    const old = document.querySelector(`.${type}`);
    if (old) old.remove();

    const div = document.createElement('div');
    div.className = type;
    
    const span = document.createElement('span');
    span.textContent = text;
    div.appendChild(span);
    
    const closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.className = 'close-btn';
    closeBtn.addEventListener('click', () => {
        div.classList.add('hiding');
        setTimeout(() => div.remove(), 300);
    });
    div.appendChild(closeBtn);
    
    document.body.appendChild(div);
    
    requestAnimationFrame(() => {
        div.classList.add('show');
    });
    
    if (duration > 0) {
        setTimeout(() => {
            if (div.parentNode) {
                div.classList.add('hiding');
                setTimeout(() => div.remove(), 300);
            }
        }, duration);
    }
}

async function sendRequest(url, options, successMessage, errorMessage) {
    try {
        const res = await fetch(url, options);
        const data = await res.json();

        if (data.success) {
            if (successMessage) showMessage('success', successMessage);
        } else {
            if (errorMessage) showMessage('error', errorMessage || data.message);
        }
        return data;
    } catch (e) {
        showMessage('error', 'Ошибка сети');
        return { success: false, message: 'Ошибка сети' };
    }
}