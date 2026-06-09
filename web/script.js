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
    if (!res.ok) return;
    const stats = await res.json();
    const avgEl = document.getElementById("avgLoad");
    const normEl = document.getElementById("normalTime");
    const devEl = document.getElementById("deviationCount");
    if (avgEl) avgEl.textContent = `📊 Средняя нагрузка: ${stats.avg_load} т/ч`;
    if (normEl)
      normEl.textContent = `✅ В норме: ${stats.normal_count} из ${stats.total_count}`;
    if (devEl) devEl.textContent = `⚠️ Отклонений: ${stats.deviation_count}`;
  } catch (e) {
    console.error(e);
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
      showMessage("error", "Нет данных");
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
    showMessage("error", "Ошибка загрузки графиков");
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

async function load() {
  try {
    const res = await fetch(`index.php?action=monitor&boiler=${activeBoiler}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    document.getElementById("boilerName").textContent =
      "Котёл: " + json.boiler + " (" + json.timestamp + ")";
    const tbody = document.querySelector("#dataTable tbody");
    tbody.innerHTML = "";
    let hasWarning = false;
    const loadVal = parseFloat(json.fact.load);
    const nominal = parseFloat(json.boiler_nominal) || 480;
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
    loadRow.innerHTML = `<td>Нагрузка</td><td>${loadVal} т/ч</td><td>${loadVal} т/ч</td><td>0</td><td>✓</td>`;
    tbody.appendChild(loadRow);
    for (let param in json.deviations) {
      const d = json.deviations[param];
      const row = document.createElement("tr");
      if (d.status === "⚠️") {
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
      row.innerHTML = `<td>${label}</td><td>${d.fact}</td><td>${d.ref}</td><td>${d.dev}</td><td>${d.status}</td>`;
      tbody.appendChild(row);
    }
    if (json.calculated) {
      const calc = json.calculated;
      const effRow = document.createElement("tr");
      effRow.innerHTML = `<td>КПД (расчётный)</td><td>${calc.efficiency.toFixed(2)} %</td><td>—</td><td>—</td><td>✓</td>`;
      tbody.appendChild(effRow);
      const fuelRow = document.createElement("tr");
      fuelRow.innerHTML = `<td>Расход газа (нормативный)</td><td>${calc.fuel_natural.toFixed(2)} тыс.м³/ч</td><td>—</td><td>—</td><td>✓</td>`;
      tbody.appendChild(fuelRow);
      const heatRow = document.createElement("tr");
      heatRow.innerHTML = `<td>Выработка тепла</td><td>${calc.heat_output.toFixed(2)} Гкал/ч</td><td>—</td><td>—</td><td>✓</td>`;
      tbody.appendChild(heatRow);
      const airRow = document.createElement("tr");
      airRow.innerHTML = `<td>Коэфф. избытка воздуха (α)</td><td>${calc.excess_air.toFixed(3)}</td><td>—</td><td>—</td><td>✓</td>`;
      tbody.appendChild(airRow);
    }
    if (json.calc_deviations) {
      for (let param in json.calc_deviations) {
        const d = json.calc_deviations[param];
        const row = document.createElement("tr");
        if (d.status === "⚠️") {
          row.classList.add("warn");
          hasWarning = true;
        }
        let label =
          param === "feedwater_temp"
            ? "Температура питательной воды (факт vs расчёт)"
            : param;
        label =
          param === "o2_content" ? "Содержание O₂ (факт vs расчёт)" : label;
        row.innerHTML = `<td>${label}</td><td>${d.fact}</td><td>${d.ref}</td><td>${d.dev}</td><td>${d.status}</td>`;
        tbody.appendChild(row);
      }
    }
    if (hasWarning) playAlert();
  } catch (e) {
    console.error(e);
    document.getElementById("boilerName").textContent =
      "Ошибка загрузки данных";
  }
}

function showMessage(type, text, duration = 4000) {
  const old = document.querySelector(`.${type}`);
  if (old) old.remove();
  const div = document.createElement("div");
  div.className = type;
  div.appendChild(document.createElement("span")).textContent = text;
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
    const res = await fetch(url, options);
    const data = await res.json();
    if (data.success) {
      if (successMessage) showMessage("success", successMessage);
    } else {
      if (errorMessage) showMessage("error", errorMessage || data.message);
    }
    return data;
  } catch (e) {
    showMessage("error", "Ошибка сети");
    return { success: false, message: "Ошибка сети" };
  }
}

async function exportToExcel() {
  const minutes = document.getElementById("exportPeriod").value;
  try {
    const res = await fetch(
      `index.php?action=history&minutes=${minutes}&boiler=${activeBoiler}`,
    );
    const data = await res.json();
    if (!data.length) {
      showMessage("error", "Нет данных");
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
    let html =
      '<table border="1"><thead><tr><th>Время</th><th>Нагрузка</th>' +
      labels.map((l) => `<th>${l}</th>`).join("") +
      "</tr></thead><tbody>";
    data.forEach((row) => {
      html += `<tr><td>${row.timestamp}</td><td>${row.load}</td>`;
      params.forEach(
        (p) =>
          (html += `<td style="mso-number-format:'\\@';">${row[p] ?? "-"}</td>`),
      );
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
    showMessage("error", "Ошибка экспорта");
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
  alertElement.innerHTML = `<div class="warning-content"><span class="warning-icon">⚠️</span><span class="warning-text">ВНИМАНИЕ! Обнаружены отклонения параметров!</span><button class="warning-close">&times;</button></div>`;
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
  alertElement.querySelector(".warning-close").onclick = () => {
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
    const boilers = await res.json();
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
        const loadMin = document.getElementById("refLoadMin").value;
        const loadMax = document.getElementById("refLoadMax").value;
        if (!loadMin || !loadMax) {
          showMessage("error", "Выберите диапазон");
          return;
        }
        const values = {};
        let hasError = false;
        document.querySelectorAll("#referenceTable tbody tr").forEach((row) => {
          const paramId = row.querySelector("input[data-param]").dataset.param;
          const value = row.querySelectorAll("input")[0].value;
          const deviation = row.querySelectorAll("input")[1].value;
          if (!value || !deviation) hasError = true;
          values[paramId] = { value, deviation };
        });
        if (hasError) {
          showMessage("error", "Заполните все поля");
          return;
        }
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=saveReference&boiler_id=${boilerCode}&load_min=${loadMin}&load_max=${loadMax}&values=${encodeURIComponent(JSON.stringify(values))}`,
        });
        const data = await res.json();
        if (data.success) {
          showMessage("success", "Эталоны сохранены");
          await loadSettings();
        } else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети");
      }
    };
    document.getElementById("saveBoilerParams").onclick = async function () {
      try {
        const boilerCode = document.getElementById(
          "settingsBoilerSelect",
        ).value;
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=saveBoilerParams&boiler_id=${boilerCode}&nominal_load=${document.getElementById("editNominalLoad").value}&load_min=${document.getElementById("editLoadMin").value}&load_max=${document.getElementById("editLoadMax").value}`,
        });
        const data = await res.json();
        if (data.success) showMessage("success", "Параметры котла сохранены");
        else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети");
      }
    };
    document.getElementById("saveRetention").onclick = async function () {
      try {
        const days = document.getElementById("retentionDays").value;
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: `action=updateRetention&days=${days}`,
        });
        const data = await res.json();
        if (data.success) showMessage("success", "Период хранения обновлён");
        else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети");
      }
    };
    document.getElementById("cleanNow").onclick = async function () {
      try {
        const res = await fetch("index.php?action=save_settings", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: "action=cleanNow",
        });
        const data = await res.json();
        if (data.success) showMessage("success", "Старые записи удалены");
        else showMessage("error", data.message || "Ошибка");
      } catch (e) {
        showMessage("error", "Ошибка сети");
      }
    };
  });

async function loadSettings() {
  const boiler = activeBoiler;
  try {
    const res = await fetch(`index.php?action=get_settings&boiler=${boiler}`);
    const data = await res.json();
    document.getElementById("editNominalLoad").value = data.boiler.nominal_load;
    document.getElementById("editLoadMin").value = data.boiler.load_min;
    document.getElementById("editLoadMax").value = data.boiler.load_max;
    const ranges = [];
    data.references.forEach((ref) => {
      const key = `${ref.load_min}_${ref.load_max}`;
      if (
        !ranges.find(
          (r) => r.load_min === ref.load_min && r.load_max === ref.load_max,
        )
      )
        ranges.push({ load_min: ref.load_min, load_max: ref.load_max });
    });
    let rangeSelect = document.getElementById("refRangeSelect");
    const container = document.getElementById("refLoadMin").parentElement;
    if (!rangeSelect) {
      const selectHtml = `<div style="margin-bottom:15px;"><label style="display:inline-block; width:150px;">Диапазон нагрузки:</label><select id="refRangeSelect" style="padding:5px;">${ranges.map((r) => `<option value="${r.load_min}|${r.load_max}">${r.load_min} - ${r.load_max} т/ч</option>`).join("")}</select></div>`;
      container.insertAdjacentHTML("beforebegin", selectHtml);
      rangeSelect = document.getElementById("refRangeSelect");
    } else {
      rangeSelect.innerHTML = ranges
        .map(
          (r) =>
            `<option value="${r.load_min}|${r.load_max}">${r.load_min} - ${r.load_max} т/ч</option>`,
        )
        .join("");
    }
    document.getElementById("refLoadMin").style.display = "none";
    document.getElementById("refLoadMax").style.display = "none";
    function loadReferencesForRange(loadMin, loadMax) {
      document.getElementById("refLoadMin").value = loadMin;
      document.getElementById("refLoadMax").value = loadMax;
      const filtered = data.references.filter(
        (ref) =>
          parseFloat(ref.load_min) === parseFloat(loadMin) &&
          parseFloat(ref.load_max) === parseFloat(loadMax),
      );
      const tbody = document.querySelector("#referenceTable tbody");
      tbody.innerHTML = "";
      filtered.forEach((ref) => {
        tbody.innerHTML += `<tr><td>${ref.name} (${ref.unit})</td><td><input type="number" step="0.001" value="${ref.reference_value}" data-param="${ref.parameter_id}"></td><td><input type="number" step="0.001" value="${ref.max_deviation}" data-param="${ref.parameter_id}"></td></tr>`;
      });
    }
    if (ranges.length)
      loadReferencesForRange(ranges[0].load_min, ranges[0].load_max);
    rangeSelect.onchange = () => {
      const [loadMin, loadMax] = rangeSelect.value.split("|");
      loadReferencesForRange(loadMin, loadMax);
    };
  } catch (e) {
    console.error(e);
    showMessage("error", "Ошибка загрузки настроек");
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
