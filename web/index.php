<?php
    include_once __DIR__ . "/../classes/csrf.php";
    include_once __DIR__ . "/../database.php";
    include_once __DIR__ . "/api/auth.php";
    include_once __DIR__ . "/api/boilers.php";
    include_once __DIR__ . "/api/history.php";
    include_once __DIR__ . "/api/monitor.php";
    include_once __DIR__ . "/api/stats.php";
    include_once __DIR__ . "/api/get_settings.php";
    include_once __DIR__ . "/api/save_settings.php";
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Мониторинг котлоагрегата</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="error show">
            <span><?= $_SESSION['error']; unset($_SESSION['error']); ?></span>
            <button class="close-btn" onclick="this.parentElement.classList.add('hiding'); setTimeout(() => this.parentElement.remove(), 300)">&times;</button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="success show">
            <span><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
            <button class="close-btn" onclick="this.parentElement.classList.add('hiding'); setTimeout(() => this.parentElement.remove(), 300)">&times;</button>
        </div>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user'])): ?>
        <div class="auth">
            <form method="POST" action="index.php?action=auth">
                <input name="csrf_token" type="hidden" value="<?= generate_csrf_token() ?>">
                <label for="log">Логин:</label>
                <input name="login" id="log" type="text"/>
                <label for="pass">Пароль:</label>
                <input name="pass" id="pass" type="password"/>
                <button type="submit">Войти</button>
            </form>
        </div>
    <?php else: ?>
        <div class="top-bar">
            <div class="top-bar-left">
                <button id="themeToggle" title="Сменить тему">🌙</button>
            </div>
            <div class="top-bar-right">
                <button id="btn-setting">Настройки</button>
                <button id="logout">Выйти</button>
            </div>
        </div>
        <button id="soundToggle">🔇 Звук выключен</button>

        <div class="main-layout">
            <div class="content">
                <div class="info">
                    <input type="hidden" name="csrf_token" value="<?= generate_csrf_token() ?>">
                    <h1 id="boilerName">Загрузка...</h1>
                    <div class="tabs">
                        <button class="tab active" data-tab="table">Таблица</button>
                        <button class="tab" data-tab="charts">Графики</button>
                    </div>

                    <div id="tab-table" class="tab-content active">
                        <div class="export-panel">
                            <select id="exportPeriod">
                                <option value="60">За час</option>
                                <option value="180">За 3 часа</option>
                                <option value="720">За 12 часов</option>
                                <option value="1440">За сутки</option>
                            </select>
                            <button id="exportExcel">📊 Скачать Excel</button>
                        </div>
                        <div class="table-wrapper">
                            <table id="dataTable">
                                <thead>
                                    <tr>
                                        <th>Параметр</th><th>Факт</th><th>Эталон</th><th>Отклонение</th><th>Статус</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="tab-charts" class="tab-content" style="display:none">
                        <div class="chart-controls">
                            <select id="chartPeriod">
                                <option value="30">За 30 минут</option>
                                <option value="60">За 1 час</option>
                                <option value="180">За 3 часа</option>
                                <option value="720">За 12 часов</option>
                                <option value="1440">За сутки</option>
                            </select>
                        </div>
                        <div class="chart-grid">
                            <div class="chart-container">
                                <h3>Температура уходящих газов</h3>
                                <canvas id="chartFlueGas"></canvas>
                            </div>
                            <div class="chart-container">
                                <h3>Давление пара</h3>
                                <canvas id="chartSteamPressure"></canvas>
                            </div>
                            <div class="chart-container">
                                <h3>Расход газа</h3>
                                <canvas id="chartGasFlow"></canvas>
                            </div>
                            <div class="chart-container">
                                <h3>КПД (расчётный)</h3>
                                <canvas id="chartEfficiency"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <aside class="sidebar">
                <div class="sidebar-card">
                    <label for="boilerSelect">Котёл</label>
                    <select id="boilerSelect">
                        <option value="">Загрузка...</option>
                    </select>
                </div>
                <div class="sidebar-card stats-card">
                    <div id="avgLoad">📊 Средняя нагрузка: -- т/ч</div>
                    <div id="normalTime">✅ В норме: --</div>
                    <div id="deviationCount">⚠️ Отклонений: --</div>
                </div>
                <!-- НОВЫЙ БЛОК: Оценка эффективности -->
                <div class="sidebar-card efficiency-card">
                    <h3>Оценка эффективности</h3>
                    <div id="efficiencyScore">--</div>
                    <div id="efficiencyRecommendations"></div>
                </div>
            </aside>
        </div>

        <div class="settings">
            <h1>Настройки</h1>
            
            <div class="settings-section">
                <label>Котёл</label>
                <select id="settingsBoilerSelect">
                    <option value="">Загрузка...</option>
                </select>
            </div>
            
            <div class="settings-section">
                <h2>Эталонные значения</h2>
                <div id="referenceEditor">
                    <div style="margin-bottom:15px;">
                        <label style="display:inline-block; width:150px;">Диапазон нагрузки:</label>
                        <select id="refRangeSelect" style="padding:5px;">
                            <option value="">Загрузка...</option>
                        </select>
                    </div>
                    <table id="referenceTable">
                        <thead>
                            <tr><th>Параметр</th><th>Эталон</th><th>Макс. отклонение</th></tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    <button id="saveReference">Сохранить</button>
                </div>
            </div>
            
            <div class="settings-section">
                <h2>Параметры котла</h2>
                <label>Мин. нагрузка (т/ч): <input type="number" id="editLoadMin" step="1" value="190"></label>
                <label>Макс. нагрузка (т/ч): <input type="number" id="editLoadMax" step="1" value="480"></label>
                <button id="saveBoilerParams">Сохранить</button>
            </div>
            
            <div class="settings-section">
                <h2>Очистка старых записей</h2>
                <label>Хранить записи (дней): <input type="number" id="retentionDays" value="1" min="1" max="30"></label>
                <button id="saveRetention">Применить</button>
                <button id="cleanNow">Очистить сейчас</button>
            </div>
        </div>
    <?php endif; ?>

    <script src="script.js"></script>
    <script>
        document.querySelectorAll('.error, .success').forEach(msg => {
            setTimeout(() => {
                if (msg.parentNode) {
                    msg.classList.add('hiding');
                    setTimeout(() => msg.remove(), 300);
                }
            }, 3000);
        });
    </script>
</body>
</html>