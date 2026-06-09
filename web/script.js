"use strict";

const INTERVAL = 10000;
let activeBoiler = "tgm96";
const chartInstances = {};
let alertElement = null,
  lastAlertTime = 0;
const ALERT_COOLDOWN = 3000;
let soundEnabled = false,
  alertSound = null,
  audioUnlocked = false;
let currentLoadRange = { min: 200, max: 480 };

function getCsrfToken() {
  const input = document.querySelector('input[name="csrf_token"]');
  return input ? input.value : "";
}

loadBoilers();

const infoBlock = document.querySelector(".info");
if (infoBlock) {
  load();
  loadStats();
  setInterval(load, INTERVAL);
  setInterval(loadStats, 30000);
}

document.querySelectorAll(".tab").forEach((tab) => {
  tab.addEventListener("click", () => (location.hash = "#" + tab.dataset.tab));
});

async function switchTab(tabName) {
  document
    .querySelectorAll(".tab")
    .forEach((t) => t.classList.remove("active"));
  document
    .querySelectorAll(".tab-content")
    .forEach((c) => (c.style.display = "none"));
  document
    .querySelector(`.tab[data-tab="${tabName}"]`)
    ?.classList.add("active");
  document.getElementById("tab-" + tabName).style.display = "block";
  if (tabName === "charts") {
    const minutes = document.getElementById("chartPeriod")?.value || "30";
    await loadCharts(minutes);
  }
  if (tabName === "table") load();
}

const hash = location.hash.replace("#", "") || "table";
switchTab(hash);
window.addEventListener("hashchange", () =>
  switchTab(location.hash.replace("#", "") || "table"),
);

async function loadStats() {
  try {
    const res = await fetch(`index.php?action=stats&boiler=${activeBoiler}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const stats = await res.json();
    if (stats.error) throw new Error(stats.error);
    const avgEl = document.getElementById("avgLoad");
    const normEl = document.getElementById("normalTime");
    const devEl = document.getElementById("deviationCount");
    if (avgEl) avgEl.textContent = `📊 Средняя нагрузка: ${stats.avg_load} т/ч`;
    if (normEl)
      normEl.textContent = `✅ В норме: ${stats.normal_count} из ${stats.total_count}`;
    if (devEl) devEl.textContent = `⚠️ Отклонений: ${stats.deviation_count}`;
  } catch (e) {
    console.error(e);
    showMessage("error", "Ошибка загрузки статистики: " + e.message);
  }
}

function formatDate(timestamp, minutes) {
  const date = new Date(timestamp);
  const m = parseInt(minutes);
  if (m <= 60)
    return date.toLocaleTimeString("ru-RU", {
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  if (m <= 720)
    return date.toLocaleTimeString("ru-RU", {
      hour: "2-digit",
      minute: "2-digit",
    });
  const day = date.getDate().toString().padStart(2, "0");
  const month = (date.getMonth() + 1).toString().padStart(2, "0");
  const time = date.toLocaleTimeString("ru-RU", {
    hour: "2-digit",
    minute: "2-digit",
  });
  return `${day}.${month} ${time}`;
}

async function loadCharts(minutes) {
  if (!minutes) minutes = document.getElementById("chartPeriod")?.value || "30";
  try {
    const res = await fetch(
      `index.php?action=history&minutes=${minutes}&boiler=${activeBoiler}`,
    );
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const history = await res.json();
    if (!history.length) {
      showMessage("error", "Нет данных для графиков");
      return;
    }
    const labels = history.map((row) => formatDate(row.timestamp, minutes));
    let maxTicks =
      minutes <= 60 ? 12 : minutes <= 360 ? 10 : minutes <= 1440 ? 8 : 6;
    const datasets = [
      {
        param: "flue_gas_temp",
        label: "Температура уходящих газов, °C",
        color: "#ef4444",
        chartId: "chartFlueGas",
      },
      {
        param: "steam_pressure",
        label: "Давление пара, кгс/см²",
        color: "#3b82f6",
        chartId: "chartSteamPressure",
      },
      {
        param: "gas_flow",
        label: "Расход газа, тыс. м³/ч",
        color: "#22c55e",
        chartId: "chartGasFlow",
      },
    ];
    datasets.forEach(({ param, label, color, chartId }) => {
      const values = history.map((row) => row[param]);
      renderChart(chartId, label, labels, values, color, maxTicks);
    });
    const efficiency = history.map((row) =>
      (100 - ((row.flue_gas_temp - 20) / 100) * 5).toFixed(1),
    );
    renderChart(
      "chartEfficiency",
      "КПД, %",
      labels,
      efficiency,
      "#f59e0b",
      maxTicks,
    );
    showMessage("success", "Графики обновлены");
  } catch (e) {
    console.error(e);
    showMessage("error", "Ошибка загрузки графиков: " + e.message);
  }
}

document
  .getElementById("chartPeriod")
  ?.addEventListener("change", () =>
    loadCharts(document.getElementById("chartPeriod").value),
  );

function renderChart(canvasId, label, labels, data, color, maxTicksLimit = 10) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;
  const ctx = canvas.getContext("2d");
  if (chartInstances[canvasId]) chartInstances[canvasId].destroy();
  let displayLabels = labels,
    displayData = data;
  if (labels.length > 200) {
    const step = Math.ceil(labels.length / 200);
    displayLabels = labels.filter((_, i) => i % step === 0);
    displayData = data.filter((_, i) => i % step === 0);
  }
  chartInstances[canvasId] = new Chart(ctx, {
    type: "line",
    data: {
      labels: displayLabels,
      datasets: [
        {
          label,
          data: displayData,
          borderColor: color,
          backgroundColor: color + "20",
          borderWidth: 2,
          pointRadius: labels.length > 100 ? 0 : 2,
          pointHoverRadius: 5,
          tension: 0.3,
          fill: true,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: "index", intersect: false },
      plugins: {
        legend: {
          position: "top",
          labels: { color: "#94a3b8", font: { size: 12 } },
        },
        tooltip: { mode: "index", intersect: false },
      },
      scales: {
        x: {
          ticks: {
            color: "#94a3b8",
            maxTicksLimit,
            maxRotation: labels.length > 50 ? 45 : 0,
          },
          grid: { color: "rgba(255,255,255,0.05)" },
        },
        y: {
          ticks: { color: "#94a3b8" },
          grid: { color: "rgba(255,255,255,0.05)" },
          beginAtZero: false,
        },
      },
    },
  });
}

function escapeHtml(str) {
  if (str === undefined || str === null) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function formatSaving(value) {
  if (value === undefined || value === null) return "—";
  if (value > 0) return `+${value.toFixed(4)} тут/ч`;
  if (value < 0) return `${value.toFixed(4)} тут/ч`;
  return "—";
}

async function load() {
  try {
    const res = await fetch(`index.php?action=monitor&boiler=${activeBoiler}`);
    if (!res.ok) throw new Error(`HTTP ${res.status} ${res.statusText}`);
    const json = await res.json();
    if (json.error) throw new Error(json.error);

    if (json.load_range) {
      currentLoadRange = { min: json.load_range[0], max: json.load_range[1] };
    }

    document.getElementById("boilerName").textContent =
      "Котёл: " + json.boiler + " (" + json.timestamp + ")";
    const tbody = document.querySelector("#dataTable tbody");
    tbody.innerHTML = "";
    let hasWarning = false;

    const loadVal = parseFloat(json.fact.load);
    const nominal = currentLoadRange.max || 480;
    const loadPercent = (loadVal / nominal) * 100;
    const loadRow = document.createElement("tr");
    if (loadPercent >= 60 && loadPercent <= 80)
      loadRow.className = "load-normal";
    else if (
      (loadPercent >= 40 && loadPercent < 60) ||
      (loadPercent > 80 && loadPercent <= 100)
    )
      loadRow.className = "load-warning";
    else loadRow.className = "load-danger";

    addCell(loadRow, "Нагрузка");
    addCell(loadRow, loadVal + " т/ч");
    addCell(loadRow, loadVal + " т/ч");
    addCell(loadRow, "0");
    addStatusCell(loadRow, "Норма", "normal");
    tbody.appendChild(loadRow);

    for (let param in json.deviations) {
      const d = json.deviations[param];
      const row = document.createElement("tr");
      let isWarning = d.status === "⚠️";
      if (isWarning) {
        row.classList.add("warn");
        hasWarning = true;
      }
      let label = param;
      switch (param) {
        case "excess_air":
          label = "Избыток воздуха";
          break;
        case "steam_pressure":
          label = "Давление пара";
          break;
        case "steam_temperature":
          label = "Температура пара";
          break;
        case "flue_gas_temp":
          label = "Температура уходящих газов";
          break;
        case "gas_flow":
          label = "Расход газа";
          break;
        case "o2_content":
          label = "Содержание O₂";
          break;
        case "feedwater_temp":
          label = "Температура питательной воды";
          break;
      }
      addCell(row, label);
      addCell(row, d.fact);
      addCell(row, d.ref);
      addCell(row, d.dev);
      addStatusCell(
        row,
        d.status === "⚠️" ? "Отклонение" : "Норма",
        isWarning ? "error" : "normal",
      );
      tbody.appendChild(row);
    }

    if (json.calculated) {
      const calc = json.calculated;
      addSeparatorRow(tbody);
      addCalcRow(tbody, "КПД (расчётный)", calc.efficiency.toFixed(2) + " %");
      addCalcRow(
        tbody,
        "Расход газа (нормативный)",
        calc.fuel_natural.toFixed(2) + " тыс.м³/ч",
      );
      addCalcRow(
        tbody,
        "Выработка тепла",
        calc.heat_output.toFixed(2) + " Гкал/ч",
      );
      addCalcRow(
        tbody,
        "Коэфф. избытка воздуха (α)",
        calc.excess_air.toFixed(3),
      );
    }

    if (json.efficiency_score) {
      const score = json.efficiency_score;
      const scoreRow = document.createElement("tr");
      scoreRow.style.backgroundColor = "rgba(59,130,246,0.2)";
      const cell = document.createElement("td");
      cell.colSpan = 5;
      cell.style.textAlign = "center";
      cell.style.fontWeight = "bold";
      cell.style.padding = "10px";
      let gradeColor = "";
      if (score.grade === "Отлично") gradeColor = "#22c55e";
      else if (score.grade === "Хорошо") gradeColor = "#3b82f6";
      else if (score.grade === "Удовлетворительно") gradeColor = "#f59e0b";
      else if (score.grade === "Требует внимания") gradeColor = "#f97316";
      else gradeColor = "#ef4444";
      cell.innerHTML = `Оценка эффективности: ${score.score} / 100 <span style="color: ${gradeColor};">(${score.grade})</span>`;
      scoreRow.appendChild(cell);
      tbody.appendChild(scoreRow);

      if (score.recommendations && score.recommendations.length) {
        score.recommendations.forEach((rec) => {
          const recRow = document.createElement("tr");
          recRow.style.backgroundColor = "rgba(239,68,68,0.1)";
          const recCell = document.createElement("td");
          recCell.colSpan = 5;
          recCell.textContent = rec;
          recRow.appendChild(recCell);
          tbody.appendChild(recRow);
        });
      }
    }

    if (json.fuel_savings && Object.keys(json.fuel_savings).length > 0) {
      addSeparatorRow(tbody, "Экономия / перерасход топлива");
      for (let [param, saving] of Object.entries(json.fuel_savings)) {
        let label = param;
        switch (param) {
          case "steam_pressure":
            label = "Давление пара";
            break;
          case "steam_temperature":
            label = "Температура пара";
            break;
          case "feedwater_temp":
            label = "Температура питательной воды";
            break;
          case "o2_content":
            label = "Содержание O₂";
            break;
          default:
            label = param;
        }
        const row = document.createElement("tr");
        addCell(row, label);
        const savingValue = formatSaving(saving);
        const savingCell = document.createElement("td");
        savingCell.colSpan = 2;
        savingCell.textContent = savingValue;
        const typeCell = document.createElement("td");
        typeCell.colSpan = 2;
        typeCell.textContent =
          saving > 0 ? "Экономия" : saving < 0 ? "Перерасход" : "—";
        if (saving > 0) typeCell.style.color = "#22c55e";
        if (saving < 0) typeCell.style.color = "#ef4444";
        row.appendChild(savingCell);
        row.appendChild(typeCell);
        tbody.appendChild(row);
      }
    }

    if (json.optimal_load && json.optimal_load.status !== "optimal") {
      const optRow = document.createElement("tr");
      optRow.className = "optimal-warning";
      const optCell = document.createElement("td");
      optCell.colSpan = 5;
      optCell.innerHTML = `⚠️ ${escapeHtml(json.optimal_load.message)} Рекомендуемая нагрузка: ${json.optimal_load.recommended_load} т/ч`;
      optRow.appendChild(optCell);
      tbody.appendChild(optRow);
    }

    if (hasWarning) playAlert();
  } catch (e) {
    console.error(e);
    document.getElementById("boilerName").textContent =
      "Ошибка загрузки данных";
    showMessage(
      "error",
      "Не удалось загрузить данные мониторинга: " + e.message,
    );
  }
}

function addCell(row, text) {
  const td = document.createElement("td");
  td.textContent = text === undefined || text === null ? "—" : text;
  row.appendChild(td);
  return td;
}

function addStatusCell(row, text, type) {
  const td = document.createElement("td");
  td.textContent = text;
  td.className = "status-" + type;
  row.appendChild(td);
}

function addSeparatorRow(tbody, title = null) {
  const sepRow = document.createElement("tr");
  sepRow.className = "section-separator";
  const sepCell = document.createElement("td");
  sepCell.colSpan = 5;
  sepCell.textContent = title || "──────────────────";
  sepRow.appendChild(sepCell);
  tbody.appendChild(sepRow);
}

function addCalcRow(tbody, label, value) {
  const row = document.createElement("tr");
  addCell(row, label);
  addCell(row, value);
  addCell(row, "—");
  addCell(row, "—");
  addStatusCell(row, "Норма", "normal");
  tbody.appendChild(row);
}

function showMessage(type, text, duration = 4000) {
  const old = document.querySelector(`.${type}`);
  if (old) old.remove();
  const div = document.createElement("div");
  div.className = type;
  const span = document.createElement("span");
  span.textContent = text;
  div.appendChild(span);
  const closeBtn = document.createElement("button");
  closeBtn.innerHTML = "&times;";
  closeBtn.className = "close-btn";
  closeBtn.addEventListener("click", () => {
    div.classList.add("hiding");
    setTimeout(() => div.remove(), 300);
  });
  div.appendChild(closeBtn);
  document.body.appendChild(div);
  requestAnimationFrame(() => div.classList.add("show"));
  if (duration > 0)
    setTimeout(() => {
      if (div.parentNode) {
        div.classList.add("hiding");
        setTimeout(() => div.remove(), 300);
      }
    }, duration);
}

async function sendRequest(url, options, successMessage, errorMessage) {
  try {
    const csrfToken = getCsrfToken();
    if (options.method === "POST" && csrfToken) {
      if (!options.headers) options.headers = {};
      options.headers["X-CSRF-Token"] = csrfToken;
      if (options.body) {
        options.body += `&csrf_token=${encodeURIComponent(csrfToken)}`;
      } else {
        options.body = `csrf_token=${encodeURIComponent(csrfToken)}`;
      }
    }
    const res = await fetch(url, options);
    const data = await res.json();
    if (res.status === 401 || res.status === 403) {
      showMessage(
        "error",
        data.message || "Ошибка доступа. Авторизуйтесь заново.",
      );
      setTimeout(() => location.reload(), 2000);
      return { success: false, message: "Unauthorized" };
    }
    if (data.success) {
      if (successMessage) showMessage("success", successMessage);
    } else {
      if (errorMessage) showMessage("error", errorMessage || data.message);
    }
    return data;
  } catch (e) {
    showMessage("error", "Ошибка сети: " + e.message);
    return { success: false, message: e.message };
  }
}

async function exportToExcel() {
  const minutes = document.getElementById("exportPeriod").value;
  try {
    const res = await fetch(
      `index.php?action=history&minutes=${minutes}&boiler=${activeBoiler}`,
    );
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (!data.length) {
      showMessage("error", "Нет данных для экспорта");
      return;
    }
    const params = [
      "steam_pressure",
      "steam_temperature",
      "flue_gas_temp",
      "gas_flow",
      "excess_air",
    ];
    const labels = [
      "Давление пара",
      "Температура пара",
      "Температура газов",
      "Расход газа",
      "Избыток воздуха",
    ];
    let html = '<table border="1"><thead><tr><th>Время</th><th>Нагрузка</th>';
    labels.forEach((l) => {
      html += `<th>${escapeHtml(l)}</th>`;
    });
    html += "<tr></thead><tbody>";
    data.forEach((row) => {
      html += `<tr><td>${escapeHtml(row.timestamp)}</td><td>${escapeHtml(row.load)}</td>`;
      params.forEach((p) => {
        const val = row[p] !== undefined && row[p] !== null ? row[p] : "-";
        html += `<td>${escapeHtml(val)}</td>`;
      });
      html += "</tr>";
    });
    html += "</tbody></table>";
    const blob = new Blob(["\uFEFF" + html], {
      type: "application/vnd.ms-excel",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `Отчёт_${activeBoiler}_${minutes}мин_${new Date().toLocaleDateString("ru-RU")}.xls`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showMessage("success", "Отчёт скачан");
  } catch (e) {
    showMessage("error", "Ошибка экспорта: " + e.message);
  }
}
document
  .getElementById("exportExcel")
  ?.addEventListener("click", exportToExcel);

const themeBtn = document.getElementById("themeToggle");
function updateThemeIcon() {
  themeBtn.textContent = document.body.classList.contains("light")
    ? "☀️"
    : "🌙";
}
themeBtn.addEventListener("click", () => {
  document.body.classList.toggle("light");
  localStorage.setItem(
    "theme",
    document.body.classList.contains("light") ? "light" : "dark",
  );
  updateThemeIcon();
});
if (localStorage.getItem("theme") === "light")
  document.body.classList.add("light");
updateThemeIcon();

function loadSoundState() {
  soundEnabled = localStorage.getItem("soundEnabled") === "true";
  updateSoundButton();
}
function saveSoundState() {
  localStorage.setItem("soundEnabled", soundEnabled);
}
function initAudio() {
  if (!alertSound) {
    alertSound = new Audio("sounds/warn.mp3");
    alertSound.volume = 0.6;
  }
}
function unlockAudio() {
  if (audioUnlocked || !alertSound) return;
  alertSound
    .play()
    .then(() => {
      alertSound.pause();
      alertSound.currentTime = 0;
      audioUnlocked = true;
      updateSoundButton();
      if (soundEnabled) playSound();
    })
    .catch(() => {});
}
function playSound() {
  if (soundEnabled && audioUnlocked && alertSound) {
    alertSound.currentTime = 0;
    alertSound.play().catch(() => {});
  }
}
function updateSoundButton() {
  const btn = document.getElementById("soundToggle");
  if (!btn) return;
  btn.textContent =
    soundEnabled && audioUnlocked ? "🔊 Звук включён" : "🔇 Звук выключен";
  btn.style.background = soundEnabled && audioUnlocked ? "#22c55e" : "#ef4444";
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
document.addEventListener("DOMContentLoaded", () => {
  initAudio();
  loadSoundState();
  document
    .getElementById("soundToggle")
    ?.addEventListener("click", toggleSound);
});

function playAlert() {
  const now = Date.now();
  if (now - lastAlertTime < ALERT_COOLDOWN) return;
  lastAlertTime = now;
  if (soundEnabled && audioUnlocked) playSound();
  if (alertElement) {
    alertElement.remove();
    alertElement = null;
  }
  const info = document.querySelector(".info");
  if (!info) return;
  alertElement = document.createElement("div");
  alertElement.className = "warning-alert";
  const contentDiv = document.createElement("div");
  contentDiv.className = "warning-content";
  const iconSpan = document.createElement("span");
  iconSpan.className = "warning-icon";
  iconSpan.textContent = "⚠️";
  const textSpan = document.createElement("span");
  textSpan.className = "warning-text";
  textSpan.textContent = "Обнаружены отклонения параметров!";
  const closeBtn = document.createElement("button");
  closeBtn.className = "warning-close";
  closeBtn.textContent = "×";
  contentDiv.appendChild(iconSpan);
  contentDiv.appendChild(textSpan);
  contentDiv.appendChild(closeBtn);
  alertElement.appendChild(contentDiv);
  info.insertBefore(alertElement, info.querySelector("h1").nextSibling);
  let blink = 0;
  const blinkInterval = setInterval(() => {
    if (!alertElement) {
      clearInterval(blinkInterval);
      return;
    }
    blink++;
    alertElement.style.backgroundColor =
      blink % 2 === 0 ? "rgba(220,38,38,0.2)" : "rgba(220,38,38,0.5)";
    if (blink >= 10) clearInterval(blinkInterval);
  }, 500);
  closeBtn.onclick = () => {
    clearInterval(blinkInterval);
    alertElement.remove();
    alertElement = null;
  };
  setTimeout(() => {
    if (alertElement) {
      clearInterval(blinkInterval);
      alertElement.remove();
      alertElement = null;
    }
  }, 10000);
}

async function loadBoilers() {
  try {
    const res = await fetch("index.php?action=boilers");
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const boilers = await res.json();
    if (boilers.error) throw new Error(boilers.error);
    const select = document.getElementById("boilerSelect");
    if (select) {
      select.innerHTML = "";
      boilers.forEach((b) => {
        const opt = document.createElement("option");
        opt.value = b.code;
        opt.textContent = b.name;
        select.appendChild(opt);
      });
      const saved = localStorage.getItem("activeBoiler");
      select.value =
        saved && boilers.find((b) => b.code === saved)
          ? saved
          : boilers[0]?.code || "";
      activeBoiler = select.value;
    }
    const settingsSelect = document.getElementById("settingsBoilerSelect");
    if (settingsSelect) {
      settingsSelect.innerHTML = "";
      boilers.forEach((b) => {
        const opt = document.createElement("option");
        opt.value = b.code;
        opt.textContent = b.name;
        settingsSelect.appendChild(opt);
      });
      settingsSelect.value = activeBoiler;
    }
    load();
    loadStats();
  } catch (e) {
    console.error(e);
    showMessage("error", "Ошибка загрузки списка котлов: " + e.message);
  }
}

document.getElementById("boilerSelect").addEventListener("change", function () {
  activeBoiler = this.value;
  localStorage.setItem("activeBoiler", activeBoiler);
  load();
  loadStats();
  showMessage("success", "Котёл выбран");
});

document
  .getElementById("btn-setting")
  .addEventListener("click", async function () {
    document.querySelector(".settings").classList.add("open");
    await loadSettings();
    document.getElementById("saveReference").onclick = async function () {
      try {
        const boilerCode = document.getElementById(
          "settingsBoilerSelect",
        ).value;
        const rangeSelect = document.getElementById("refRangeSelect");

        if (!rangeSelect) {
          showMessage("error", "Не выбран диапазон нагрузки");
          return;
        }

        var parts = rangeSelect.value.split("|");
        var loadMin = parts[0];
        var loadMax = parts[1];

        var rows = document.querySelectorAll("#referenceTable tbody tr");
        var values = {};
        var hasError = false;

        for (var i = 0; i < rows.length; i++) {
          var row = rows[i];
          var inputs = row.querySelectorAll("input");
          if (inputs.length !== 2) continue;

          var paramId = inputs[0].dataset.param;
          var value = inputs[0].value;
          var deviation = inputs[1].value;

          if (!value || !deviation) {
            hasError = true;
            break;
          }

          values[paramId] = { value: value, deviation: deviation };
        }

        if (hasError) {
          showMessage("error", "Заполните все поля");
          return;
        }

        const csrfToken = getCsrfToken();

        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body:
            "action=saveReference&boiler_id=" +
            encodeURIComponent(boilerCode) +
            "&load_min=" +
            encodeURIComponent(loadMin) +
            "&load_max=" +
            encodeURIComponent(loadMax) +
            "&values=" +
            encodeURIComponent(JSON.stringify(values)) +
            "&csrf_token=" +
            encodeURIComponent(csrfToken),
        });

        const data = await res.json();

        if (data.success) {
          showMessage("success", "Эталоны сохранены");
          await loadSettings();
        } else {
          showMessage("error", data.message || "Ошибка сохранения");
        }
      } catch (e) {
        console.error(e);
        showMessage("error", "Ошибка: " + e.message);
      }
    };
    document.getElementById("saveBoilerParams").onclick = async function () {
      try {
        const boilerCode = document.getElementById(
          "settingsBoilerSelect",
        ).value;
        const csrfToken = getCsrfToken();
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=saveBoilerParams&boiler_id=${encodeURIComponent(boilerCode)}&load_min=${encodeURIComponent(document.getElementById("editLoadMin").value)}&load_max=${encodeURIComponent(document.getElementById("editLoadMax").value)}&csrf_token=${encodeURIComponent(csrfToken)}`,
        });
        const data = await res.json();
        if (data.success) {
          showMessage("success", "Параметры котла сохранены");
          await load();
        } else {
          showMessage("error", data.message || "Ошибка");
        }
      } catch (e) {
        showMessage("error", "Ошибка сети: " + e.message);
      }
    };
    document.getElementById("saveRetention").onclick = async function () {
      try {
        const days = document.getElementById("retentionDays").value;
        const csrfToken = getCsrfToken();
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=updateRetention&days=${encodeURIComponent(days)}&csrf_token=${encodeURIComponent(csrfToken)}`,
        });
        const data = await res.json();
        if (data.success) showMessage("success", "Период хранения обновлён");
        else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети: " + e.message);
      }
    };
    document.getElementById("cleanNow").onclick = async function () {
      try {
        const csrfToken = getCsrfToken();
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=cleanNow&csrf_token=${encodeURIComponent(csrfToken)}`,
        });
        const data = await res.json();
        if (data.success) showMessage("success", "Старые записи удалены");
        else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети: " + e.message);
      }
    };
  });

async function loadSettings() {
  const boiler = activeBoiler;
  try {
    const res = await fetch(`index.php?action=get_settings&boiler=${boiler}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    const ranges = [];
    data.references.forEach((ref) => {
      const found = ranges.find(function (r) {
        return (
          parseFloat(r.load_min) === parseFloat(ref.load_min) &&
          parseFloat(r.load_max) === parseFloat(ref.load_max)
        );
      });
      if (!found) {
        ranges.push({ load_min: ref.load_min, load_max: ref.load_max });
      }
    });

    const rangeSelect = document.getElementById("refRangeSelect");
    if (rangeSelect && ranges.length) {
      rangeSelect.innerHTML = "";
      for (let i = 0; i < ranges.length; i++) {
        var r = ranges[i];
        var option = document.createElement("option");
        option.value = r.load_min + "|" + r.load_max;
        option.textContent = r.load_min + " - " + r.load_max + " т/ч";
        rangeSelect.appendChild(option);
      }

      function loadReferencesForRange(loadMin, loadMax) {
        const filtered = [];
        for (let i = 0; i < data.references.length; i++) {
          var ref = data.references[i];
          if (
            parseFloat(ref.load_min) === parseFloat(loadMin) &&
            parseFloat(ref.load_max) === parseFloat(loadMax)
          ) {
            filtered.push(ref);
          }
        }

        const tbody = document.querySelector("#referenceTable tbody");
        if (!tbody) return;
        tbody.innerHTML = "";

        for (let i = 0; i < filtered.length; i++) {
          var ref = filtered[i];
          var row = tbody.insertRow();

          var cellName = row.insertCell(0);
          cellName.textContent = ref.name + " (" + ref.unit + ")";

          var cellValue = row.insertCell(1);
          var valueInput = document.createElement("input");
          valueInput.type = "number";
          valueInput.step = "0.001";
          valueInput.value = ref.reference_value;
          valueInput.dataset.param = ref.parameter_id;
          cellValue.appendChild(valueInput);

          var cellDeviation = row.insertCell(2);
          var devInput = document.createElement("input");
          devInput.type = "number";
          devInput.step = "0.001";
          devInput.value = ref.max_deviation;
          devInput.dataset.param = ref.parameter_id;
          cellDeviation.appendChild(devInput);
        }
      }

      var firstRange = ranges[0];
      loadReferencesForRange(firstRange.load_min, firstRange.load_max);

      rangeSelect.onchange = function () {
        var parts = this.value.split("|");
        loadReferencesForRange(parts[0], parts[1]);
      };
    }
  } catch (e) {
    console.error(e);
    showMessage("error", "Ошибка загрузки настроек: " + e.message);
  }
}

document.addEventListener("click", function (e) {
  const settings = document.querySelector(".settings");
  if (
    settings.classList.contains("open") &&
    !settings.contains(e.target) &&
    e.target.id !== "btn-setting"
  )
    settings.classList.remove("open");
});
