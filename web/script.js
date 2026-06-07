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
    
    if (tabName === 'charts') {
        const periodEl = document.getElementById('chartPeriod');
        const minutes = periodEl ? periodEl.value : '30';
        await loadCharts(minutes);
    }
    if (tabName === 'table') load();
}

const hash = location.hash.replace('#', '') || 'table';
switchTab(hash);

window.addEventListener('hashchange', () => {
    const tabName = location.hash.replace('#', '') || 'table';
    switchTab(tabName);
});

const chartInstances = {};

// Функция для форматирования даты в читаемый вид
function formatDate(timestamp, minutes) {
    const date = new Date(timestamp);
    const minutesCount = parseInt(minutes);
    
    if (minutesCount <= 60) {
        // До часа: ЧЧ:ММ:СС
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } else if (minutesCount <= 720) {
        // До 12 часов: ЧЧ:ММ
        return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    } else {
        // Более 12 часов: ДД.ММ ЧЧ:ММ (например: 07.06 15:30)
        const day = date.getDate().toString().padStart(2, '0');
        const month = (date.getMonth() + 1).toString().padStart(2, '0');
        const time = date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        return `${day}.${month} ${time}`;
    }
}

// Функция для получения текста периода
function getPeriodText(minutes) {
    const hours = minutes / 60;
    if (minutes <= 60) return `за ${minutes} минут`;
    if (minutes === 120) return 'за 2 часа';
    if (minutes === 180) return 'за 3 часа';
    if (minutes === 360) return 'за 6 часов';
    if (minutes === 720) return 'за 12 часов';
    if (minutes === 1440) return 'за сутки';
    return `за ${hours} часов`;
}

async function loadCharts(minutes) {
    if (!minutes) {
        const periodEl = document.getElementById('chartPeriod');
        minutes = periodEl ? periodEl.value : '30';
    }
    
    const chartContainers = document.querySelectorAll('.chart-container');
    chartContainers.forEach(container => {
        container.style.opacity = '0.5';
    });
    
    try {
        const res = await fetch(`index.php?action=history&minutes=${minutes}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const history = await res.json();
        
        if (!history.length) {
            showMessage('error', `Нет данных за выбранный период`);
            return;
        }
        
        // Форматируем метки времени
        const labels = history.map(row => formatDate(row.timestamp, minutes));
        
        // Определяем оптимальное количество меток на оси X
        let maxTicksLimit = 10;
        if (minutes <= 60) maxTicksLimit = 12;
        else if (minutes <= 360) maxTicksLimit = 10;
        else if (minutes <= 1440) maxTicksLimit = 8;
        else maxTicksLimit = 6;
        
        const datasets = [
            { param: 'flue_gas_temp', label: 'Температура уходящих газов', color: '#ef4444', chartId: 'chartFlueGas', unit: '°C' },
            { param: 'steam_pressure', label: 'Давление пара', color: '#3b82f6', chartId: 'chartSteamPressure', unit: 'кгс/см²' },
            { param: 'gas_flow', label: 'Расход газа', color: '#22c55e', chartId: 'chartGasFlow', unit: 'м³/ч' },
        ];
        
        datasets.forEach(({ param, label, color, chartId, unit }) => {
            const values = history.map(row => row[param]);
            renderChart(chartId, `${label}, ${unit}`, labels, values, color, maxTicksLimit);
        });
        
        const efficiency = history.map(row => {
            const q2 = (row.flue_gas_temp - 20) / 100 * 5;
            return (100 - q2).toFixed(1);
        });
        renderChart('chartEfficiency', 'КПД, %', labels, efficiency, '#f59e0b', maxTicksLimit);
        
        // Показываем одно сообщение о загрузке данных
        showMessage('success', `Данные загружены ${getPeriodText(minutes)}`);
        
    } catch (e) {
        console.error('Ошибка загрузки графиков:', e);
        showMessage('error', 'Ошибка загрузки графиков');
    } finally {
        chartContainers.forEach(container => {
            container.style.opacity = '1';
        });
    }
}

document.getElementById('chartPeriod')?.addEventListener('change', () => {
    const periodEl = document.getElementById('chartPeriod');
    const minutes = periodEl.value;
    loadCharts(minutes);
});

function renderChart(canvasId, label, labels, data, color, maxTicksLimit = 10) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    if (chartInstances[canvasId]) {
        chartInstances[canvasId].destroy();
    }
    
    // Прореживаем данные для лучшей читаемости при большом количестве точек
    let displayLabels = labels;
    let displayData = data;
    const maxPoints = 200;
    
    if (labels.length > maxPoints) {
        const step = Math.ceil(labels.length / maxPoints);
        displayLabels = labels.filter((_, index) => index % step === 0);
        displayData = data.filter((_, index) => index % step === 0);
    }
    
    chartInstances[canvasId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: displayLabels,
            datasets: [{
                label: label,
                data: displayData,
                borderColor: color,
                backgroundColor: color + '20',
                borderWidth: 2,
                pointRadius: labels.length > 100 ? 0 : 2,
                pointHoverRadius: 5,
                tension: 0.3,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            plugins: {
                legend: { 
                    display: true,
                    position: 'top',
                    labels: { color: '#94a3b8', font: { size: 12 } }
                },
                tooltip: { 
                    mode: 'index', 
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    ticks: { 
                        color: '#94a3b8', 
                        maxTicksLimit: maxTicksLimit,
                        maxRotation: labels.length > 50 ? 45 : 0,
                        minRotation: labels.length > 50 ? 45 : 0,
                        autoSkip: true,
                        autoSkipPadding: 10
                    },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    title: {
                        display: true,
                        text: 'Время',
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                },
                y: {
                    ticks: { color: '#94a3b8' },
                    grid: { color: 'rgba(255,255,255,0.05)' },
                    beginAtZero: false,
                    title: {
                        display: true,
                        text: label.split(',')[1] || 'значение',
                        color: '#94a3b8',
                        font: { size: 11 }
                    }
                }
            },
            elements: {
                line: {
                    borderJoin: 'round'
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

        let hasWarning = false;
        
        for (let param in json.deviations) {
            const d = json.deviations[param];
            const row = document.createElement('tr');
            if (d.status === '⚠️') { 
                row.classList.add('warn');
                hasWarning = true;
            }
            let label = param;
            switch(param) {
                case 'excess_air':        label = 'Избыток воздуха'; break;
                case 'steam_pressure':    label = 'Давление пара'; break;
                case 'steam_temperature': label = 'Температура пара'; break;
                case 'flue_gas_temp':     label = 'Температура уходящих газов'; break;
                case 'gas_flow':          label = 'Расход газа'; break;
            }
            row.innerHTML = `
                <td>${label}</td>
                <td>${d.fact}</td>
                <td>${d.ref}</td>
                <td>${d.dev}</td>
                <td>${d.status}</td>
            `;
            tbody.appendChild(row);
        }
        if (hasWarning) playAlert();
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

async function exportToExcel() {
    const minutes = document.getElementById('exportPeriod').value;
    
    try {
        const res = await fetch(`index.php?action=history&minutes=${minutes}`);
        const data = await res.json();
        
        if (!data.length) {
            showMessage('error', 'Нет данных за выбранный период');
            return;
        }
        
        const params = ['steam_pressure', 'steam_temperature', 'flue_gas_temp', 'gas_flow', 'excess_air'];
        const labels = ['Давление пара', 'Температура пара', 'Температура газов', 'Расход газа', 'Избыток воздуха'];
        
        let html = '<table border="1">';
        html += '<tr><th>Время</th><th>Нагрузка</th>';
        labels.forEach(l => html += `<th>${l}</th>`);
        html += '</tr>';

        data.forEach(row => {
            html += '<tr>';
            html += `<td>${row.timestamp}</td>`;
            html += `<td>${row.load}</td>`;
            params.forEach(p => {
                const val = row[p] ?? '-';
                html += `<td style="mso-number-format:'\\@';">${val}</td>`;
            });
            html += '</tr>';
        });

        html += '</table>';
        
        const blob = new Blob(['\uFEFF' + html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Отчёт_котла_${minutes}мин_${new Date().toLocaleDateString('ru-RU')}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        showMessage('success', `Отчёт за ${getPeriodText(minutes)} скачан`);
    } catch (e) {
        console.error(e);
        showMessage('error', 'Ошибка экспорта');
    }
}

document.getElementById('exportExcel')?.addEventListener('click', exportToExcel);

const themeBtn = document.getElementById('themeToggle');

function updateThemeIcon() {
    const isLight = document.body.classList.contains('light');
    themeBtn.textContent = isLight ? '☀️' : '🌙';
}

themeBtn.addEventListener('click', () => {
    document.body.classList.toggle('light');
    localStorage.setItem('theme', document.body.classList.contains('light') ? 'light' : 'dark');
    updateThemeIcon();
});

if (localStorage.getItem('theme') === 'light') {
    document.body.classList.add('light');
}
updateThemeIcon();

// ========== УПРАВЛЕНИЕ ЗВУКОМ ==========
let soundEnabled = false;
let alertSound = null;
let audioUnlocked = false;

function loadSoundState() {
    const savedState = localStorage.getItem('soundEnabled');
    soundEnabled = savedState === 'true';
    updateSoundButton();
}

function saveSoundState() {
    localStorage.setItem('soundEnabled', soundEnabled);
}

function initAudio() {
    if (!alertSound) {
        alertSound = new Audio('sounds/warn.mp3');
        alertSound.volume = 0.6;
        alertSound.load();
    }
}

function unlockAudio() {
    if (audioUnlocked || !alertSound) return;
    
    alertSound.play().then(() => {
        alertSound.pause();
        alertSound.currentTime = 0;
        audioUnlocked = true;
        console.log('Звук разблокирован');
        updateSoundButton();
        if (soundEnabled) playSound();
    }).catch(e => console.log('Нужен клик для разблокировки'));
}

function playSound() {
    if (!soundEnabled || !audioUnlocked || !alertSound) return;
    alertSound.currentTime = 0;
    alertSound.play().catch(e => console.log('Play error:', e));
}

function updateSoundButton() {
    const soundBtn = document.getElementById('soundToggle');
    if (!soundBtn) return;
    
    if (soundEnabled && audioUnlocked) {
        soundBtn.textContent = '🔊 Звук включён';
        soundBtn.style.background = '#22c55e';
    } else {
        soundBtn.textContent = '🔇 Звук выключен';
        soundBtn.style.background = '#ef4444';
    }
}

function toggleSound() {
    if (!audioUnlocked) {
        unlockAudio();
        soundEnabled = true;
        saveSoundState();
        updateSoundButton();
    } else {
        soundEnabled = !soundEnabled;
        saveSoundState();
        updateSoundButton();
        if (soundEnabled) playSound();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    initAudio();
    loadSoundState();
    const soundBtn = document.getElementById('soundToggle');
    if (soundBtn) {
        soundBtn.addEventListener('click', toggleSound);
    }
});

// ========== ФУНКЦИЯ ОПОВЕЩЕНИЯ ==========
let alertElement = null;
let alertTimeout = null;
let hideTimeout = null;
let lastAlertTime = 0;
const ALERT_COOLDOWN = 3000;

function playAlert() {
    const now = Date.now();
    if (now - lastAlertTime < ALERT_COOLDOWN) return;
    lastAlertTime = now;
    
    if (soundEnabled && audioUnlocked) {
        playSound();
    }
    
    if (alertElement) {
        clearTimeout(alertTimeout);
        clearTimeout(hideTimeout);
        if (alertElement.remove) alertElement.remove();
        alertElement = null;
    }
    
    const infoBlock = document.querySelector('.info');
    if (!infoBlock) return;
    
    alertElement = document.createElement('div');
    alertElement.className = 'warning-alert';
    alertElement.innerHTML = `
        <div class="warning-content">
            <span class="warning-icon">⚠️</span>
            <span class="warning-text">
                ВНИМАНИЕ! Обнаружены отклонения параметров котла!
            </span>
            <button class="warning-close">&times;</button>
        </div>
    `;
    
    const h1 = infoBlock.querySelector('h1');
    if (h1 && h1.nextSibling) {
        infoBlock.insertBefore(alertElement, h1.nextSibling);
    } else {
        infoBlock.appendChild(alertElement);
    }
    
    let blinkCount = 0;
    const blinkInterval = setInterval(() => {
        if (!alertElement) {
            clearInterval(blinkInterval);
            return;
        }
        blinkCount++;
        alertElement.style.backgroundColor = blinkCount % 2 === 0 
            ? 'rgba(220, 38, 38, 0.2)' 
            : 'rgba(220, 38, 38, 0.5)';
        if (blinkCount >= 10) clearInterval(blinkInterval);
    }, 500);
    
    alertElement.querySelector('.warning-close').onclick = () => {
        clearInterval(blinkInterval);
        clearTimeout(hideTimeout);
        alertElement.classList.add('hiding');
        setTimeout(() => {
            if (alertElement) alertElement.remove();
            alertElement = null;
        }, 300);
    };
    
    hideTimeout = setTimeout(() => {
        if (alertElement) {
            clearInterval(blinkInterval);
            alertElement.classList.add('hiding');
            setTimeout(() => {
                if (alertElement) alertElement.remove();
                alertElement = null;
            }, 300);
        }
    }, 10000);
    
    if ('vibrate' in navigator && soundEnabled && audioUnlocked) {
        navigator.vibrate(200);
    }
}