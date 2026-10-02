<?php
require_once __DIR__ . '/src/admin_auth.php';
admin_ensure_csrf();
$config = require __DIR__ . '/src/config.php';
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Câmara Fria • Bugio</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

<style>

:root {
    --bg: #07111f;
    --panel: #0d1b2c;
    --panel2: #102238;
    --text: #edf6ff;
    --muted: #8fa8bf;
    --line: #1b3652;

    --ok: #50e3a4;
    --warn: #ffc857;
    --bad: #ff647c;
    --blue: #61b8ff;
}

* {
    box-sizing: border-box;
}

html {
    color-scheme: dark;
}

body {

    margin: 0;

    min-height: 100vh;

    background:
        radial-gradient(
            circle at 80% 0,
            #12304c 0,
            transparent 35%
        ),
        var(--bg);

    color: var(--text);

    font-family:
        system-ui,
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        Roboto,
        sans-serif;

    font-size: 15px;
    line-height: 1.45;
}


/* =========================================================
   CONTAINER
========================================================= */

.wrap {

    width: 100%;

    max-width: 1100px;

    margin: 0 auto;

    padding:
        28px
        18px
        50px;

}


/* =========================================================
   HEADER
========================================================= */

.top {

    display: flex;

    align-items: flex-start;

    justify-content: space-between;

    gap: 20px;

    margin-bottom: 22px;

}

.eyebrow {

    color: var(--blue);

    font-size: 12px;

    font-weight: 800;

    letter-spacing: .15em;

    text-transform: uppercase;

}

h1 {

    margin:
        4px
        0
        4px;

    font-size:
        clamp(
            25px,
            5vw,
            38px
        );

    letter-spacing: -.03em;

}

.device-select {
    max-width: 100%;
    padding: 0 30px 0 0;
    border: 0;
    background: transparent;
    color: inherit;
    font: inherit;
    font-weight: inherit;
    letter-spacing: inherit;
    cursor: pointer;
}

.device-select:focus-visible {
    outline: 2px solid var(--blue);
    border-radius: 4px;
}

.device-select option {
    background: var(--panel);
    color: var(--text);
    font-size: 18px;
}

.sub {

    color: var(--muted);

}


/* =========================================================
   STATUS
========================================================= */

.status {

    display: flex;

    align-items: center;

    gap: 8px;

    color: var(--text);
    font-weight: 750;
    padding: 9px 13px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--panel2);

}
.status[data-state="NORMAL"] { border-color: #347b65; }
.status[data-state="TEMP_ALTA"], .status[data-state="TEMP_BAIXA"], .status[data-state="OFFLINE"] { border-color: #9d5765; }
.status[data-state="STALE"] { border-color: #a17e3a; }

.header-actions { display: flex; align-items: center; gap: 10px; }
.iconbtn { position: relative; border: 1px solid var(--line); background: var(--panel2); color: var(--text); border-radius: 12px; min-width: 42px; height: 42px; cursor: pointer; font-size: 18px; }
.badge { position: absolute; top: -7px; right: -7px; min-width: 20px; height: 20px; padding: 0 5px; border-radius: 10px; background: var(--bad); color: white; font: 700 11px/20px system-ui; }
.badge[hidden] { display: none; }

.overlay { position: fixed; inset: 0; z-index: 20; display: none; justify-content: flex-end; background: #0009; }
.overlay.open { display: flex; }
.drawer { width: min(470px, 100%); height: 100%; overflow: auto; padding: 22px; background: var(--panel); border-left: 1px solid var(--line); box-shadow: -20px 0 50px #0008; }
.drawerhead { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; }
.drawerhead h2 { margin: 0; font-size: 20px; }
.closebtn { border: 0; background: transparent; color: var(--muted); font-size: 25px; cursor: pointer; }
.toolbar { display: flex; gap: 10px; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.btn { border: 1px solid var(--line); border-radius: 10px; padding: 10px 14px; background: var(--panel2); color: var(--text); cursor: pointer; font-weight: 650; }
.btn.primary { background: #1879bd; border-color: #2998e5; }
.btn:disabled { opacity: .55; cursor: wait; }
.notice-list { display: grid; gap: 10px; }
.notice-item { padding: 13px; border: 1px solid var(--line); border-radius: 13px; background: #0a1929; cursor: pointer; }
.notice-item.unread { border-left: 4px solid var(--blue); background: #10243a; }
.notice-title { font-weight: 750; }
.notice-meta, .notice-data { color: var(--muted); font-size: 12px; margin-top: 4px; }
.notice-message { margin-top: 5px; }
.formgrid { display: grid; grid-template-columns: 1fr 1fr; gap: 13px; }
.field { display: grid; gap: 6px; color: var(--muted); font-size: 13px; }
.field.full { grid-column: 1 / -1; }
.field input, .field select { width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 9px; background: #081624; color: var(--text); }
.check { display: flex; gap: 9px; align-items: center; color: var(--text); }
.check input { width: auto; }
.formsection { grid-column: 1 / -1; margin: 8px 0 0; padding-top: 13px; border-top: 1px solid var(--line); font-weight: 750; }
.feedback { min-height: 22px; margin-top: 12px; color: var(--muted); }
.feedback.error { color: var(--bad); }
.feedback.success { color: var(--ok); }
.empty-panel { color: var(--muted); text-align: center; padding: 30px 10px; }

.dot {

    width: 9px;

    height: 9px;

    border-radius: 50%;

    background: var(--warn);

    box-shadow:
        0
        0
        12px
        currentColor;

}


/* =========================================================
   CARDS
========================================================= */

.cards {

    display: grid;

    grid-template-columns:
        repeat(
            3,
            1fr
        );

    gap: 14px;

}

.card,
.chartbox,
.logs {

    background:
        linear-gradient(
            145deg,
            rgba(16, 34, 56, .96),
            rgba(10, 25, 42, .96)
        );

    border:
        1px
        solid
        var(--line);

    border-radius: 18px;

    box-shadow:
        0
        15px
        35px
        #0004;

}

.card {

    padding: 20px;

}

.label {

    color: var(--muted);

    font-size: 13px;

}

.value {

    margin-top: 7px;

    font-size:
        clamp(
            32px,
            6vw,
            52px
        );

    font-weight: 750;

    letter-spacing: -.05em;

    font-variant-numeric:
        tabular-nums;

}

.unit {

    font-size: .42em;

    color: var(--muted);

    letter-spacing: 0;

}


/* =========================================================
   GRÁFICO / LOG
========================================================= */

.chartbox,
.logs {

    margin-top: 14px;

    padding: 20px;

}

.sectionhead {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 10px;

    margin-bottom: 14px;

}

.sectionhead h2 {

    margin: 0;

    font-size: 16px;

}

.sectionhead span {

    color: var(--muted);

    font-size: 12px;

}

.canvaswrap {

    position: relative;

    height: 310px;

}


/* =========================================================
   TABELA
========================================================= */

.tablewrap {

    overflow: auto;

    max-height: 370px;

}

table {

    width: 100%;

    min-width: 650px;

    border-collapse: collapse;

}

th,
td {

    padding:
        11px
        10px;

    text-align: left;

    border-bottom:
        1px
        solid
        #17314c;

    font-variant-numeric:
        tabular-nums;

}

th {

    position: sticky;

    top: 0;

    z-index: 2;

    background: #102238;

    color: var(--muted);

    font-size: 12px;

    text-transform: uppercase;

    letter-spacing: .05em;

}

td:first-child {

    color: var(--muted);

}

.empty {

    padding: 30px;

    text-align: center;

    color: var(--muted);

}


/* =========================================================
   MOBILE
========================================================= */

@media (
    max-width: 760px
) {

    .wrap {

        padding-top: 20px;

    }

    .top {

        display: block;

    }

    .status {

        margin-top: 12px;

    }

    .header-actions { margin-top: 12px; flex-wrap: wrap; }
    .formgrid { grid-template-columns: 1fr; }
    .field.full, .formsection { grid-column: 1; }

    .cards {

        grid-template-columns: 1fr;

    }

    .canvaswrap {

        height: 260px;

    }

}

.overview { margin-bottom: 18px; }
.overview-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; margin-bottom: 9px; }
.overview-head h2 { margin: 0; font-size: 15px; }
.overview-head span { color: var(--muted); font-size: 12px; }
.device-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.device-tile { width: 100%; min-height: 82px; padding: 11px 13px; text-align: left; color: var(--text); background: var(--panel); border: 1px solid var(--line); border-radius: 14px; cursor: pointer; }
.device-tile[aria-pressed="true"] { border-color: var(--blue); background: var(--panel2); }
.device-tile:focus-visible, .periods button:focus-visible, .logs-toggle:focus-visible, .iconbtn:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
.device-tile-top { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; font-weight: 750; }
.device-tile-temp { font-variant-numeric: tabular-nums; white-space: nowrap; }
.device-tile-bottom { display: flex; justify-content: space-between; gap: 8px; margin-top: 5px; font-size: 12px; color: var(--muted); }
.state-label { font-weight: 700; }
.state-label[data-state="NORMAL"] { color: var(--ok); }
.state-label[data-state="TEMP_ALTA"], .state-label[data-state="TEMP_BAIXA"], .state-label[data-state="OFFLINE"] { color: var(--bad); }
.state-label[data-state="STALE"] { color: var(--warn); }
.exact-time { display: block; margin-top: 2px; color: var(--muted); font-size: 12px; }
.cards { grid-template-columns: 1.4fr 1fr 1fr; }
.card-primary { border-color: #2e658d; }
.card-secondary .value { font-size: clamp(27px, 4vw, 37px); }
.periods { display: flex; gap: 4px; padding: 3px; background: #081624; border: 1px solid var(--line); border-radius: 10px; }
.periods button { min-width: 42px; min-height: 36px; border: 0; border-radius: 7px; background: transparent; color: var(--muted); cursor: pointer; font-weight: 700; }
.periods button[aria-pressed="true"] { background: var(--panel2); color: var(--text); }
.chart-note { color: var(--muted); font-size: 12px; margin-top: 8px; }
.chart-empty { position: absolute; inset: 48px 38px; display: grid; place-items: center; color: var(--muted); background: #0d1b2ca8; text-align: center; }
.chart-empty[hidden] { display: none; }
.history-events { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 9px; }
.history-event { padding: 4px 7px; border: 1px solid var(--line); border-radius: 7px; color: var(--muted); font-size: 11px; }
.history-event.risk { border-color: #774650; color: #ff9aa9; }
.logs-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 42px; border: 0; background: transparent; color: var(--text); cursor: pointer; padding: 0; text-align: left; }
.logs-toggle strong { font-size: 16px; }
.logs-toggle span { color: var(--muted); }
.logs-toggle[aria-expanded="false"] .chevron { transform: rotate(-90deg); }
.logs-body[hidden] { display: none; }
.field-help { grid-column: 1 / -1; margin: -3px 0 2px; color: var(--muted); font-size: 12px; }
.admin-indicator { color: var(--ok); font-size: 12px; font-weight: 700; white-space: nowrap; }
.admin-logout { padding: 7px 10px; min-height: 36px; font-size: 12px; }
.admin-indicator[hidden], .admin-logout[hidden] { display: none; }
.auth-overlay { align-items: center; justify-content: center; padding: 18px; }
.auth-overlay .auth-dialog { width: min(380px, 100%); height: auto; max-height: 100%; border: 1px solid var(--line); border-radius: 18px; box-shadow: 0 20px 50px #0009; }
.auth-form { display: grid; gap: 14px; }
.auth-form input { width: 100%; min-height: 44px; padding: 10px; border: 1px solid var(--line); border-radius: 9px; background: #081624; color: var(--text); }
.auth-form .btn { min-height: 44px; }
@media (max-width: 760px) {
    .cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .card-primary { grid-column: 1 / -1; }
    .card { padding: 15px; }
    .card-secondary { min-width: 0; }
    .card-secondary .value { font-size: clamp(23px, 5vw, 32px); }
    .device-grid { display: flex; overflow-x: auto; scroll-snap-type: x proximity; padding-bottom: 4px; }
    .device-tile { min-width: min(225px, 78vw); scroll-snap-align: start; }
    .top { margin-bottom: 16px; }
    .header-actions { display: flex; align-items: center; gap: 8px; }
    .status { margin-top: 0; margin-right: auto; }
    .chartbox, .logs { padding: 15px; }
}
</style>

</head>


<body>


<main class="wrap">


<!-- =====================================================
     CABEÇALHO
===================================================== -->

<header class="top">

    <div>

        <div class="eyebrow">
            Monitoramento
        </div>

        <h1>
            <select id="deviceSelect" class="device-select" aria-label="Selecionar câmara">
                <?php foreach ($config['devices'] as $deviceId => $device): ?>
                    <option value="<?= htmlspecialchars($deviceId, ENT_QUOTES, 'UTF-8') ?>" <?= $deviceId === $config['default_device'] ? 'selected' : '' ?>><?= htmlspecialchars($device['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </h1>

        <div class="sub" id="last">
            Aguardando primeira leitura…
        </div>
        <time class="exact-time" id="lastExact"></time>

    </div>


    <div class="header-actions">

      <div class="status" id="operationalStatus" role="status">

        <i
            class="dot"
            id="dot"
        ></i>

        <span id="statusText">
            Sem dados
        </span>

      </div>

      <span class="admin-indicator" id="adminIndicator" hidden>Admin ativo</span>
      <button class="btn admin-logout" id="logoutButton" type="button" hidden>Sair</button>

      <button class="iconbtn" id="notificationsButton" type="button" title="Notificações" aria-label="Abrir notificações">
          🔔<span class="badge" id="notificationBadge" hidden>0</span>
      </button>

      <button class="iconbtn" id="settingsButton" type="button" title="Configurações" aria-label="Abrir configurações">⚙</button>

    </div>

</header>

<section class="overview" aria-labelledby="overviewTitle">
  <div class="overview-head"><h2 id="overviewTitle">Visão geral das câmaras</h2><span id="overviewSummary">Carregando…</span></div>
  <div class="device-grid" id="deviceOverview"></div>
</section>



<!-- =====================================================
     CARDS
===================================================== -->

<section class="cards">


    <article class="card card-primary">

        <div class="label">
            Temperatura
        </div>

        <div class="value">

            <span id="temp">
                --
            </span>

            <span class="unit">
                °C
            </span>

        </div>

    </article>



    <article class="card card-secondary">

        <div class="label">
            Umidade relativa
        </div>

        <div class="value">

            <span id="hum">
                --
            </span>

            <span class="unit">
                %
            </span>

        </div>

    </article>



    <article class="card card-secondary">

        <div class="label">
            Pressão
        </div>

        <div class="value">

            <span id="press">
                --
            </span>

            <span class="unit">
                hPa
            </span>

        </div>

    </article>


</section>



<!-- =====================================================
     GRÁFICO
===================================================== -->

<section class="chartbox">

    <div class="sectionhead">

        <h2>
            Histórico
        </h2>

        <div class="periods" role="group" aria-label="Período do histórico">
          <button type="button" data-hours="1" aria-pressed="false">1h</button>
          <button type="button" data-hours="6" aria-pressed="false">6h</button>
          <button type="button" data-hours="24" aria-pressed="true">24h</button>
          <button type="button" data-hours="168" aria-pressed="false">7d</button>
        </div>

    </div>


    <div class="canvaswrap">
        <canvas id="chart"></canvas>
        <div class="chart-empty" id="chartEmpty" hidden>Sem leituras neste período.</div>
    </div>
    <div class="chart-note" id="chartNote">Temperatura em destaque · limites de alerta e eventos do Alert Engine</div>
    <div class="history-events" id="historyEvents" aria-label="Eventos no período"></div>

</section>



<!-- =====================================================
     LOG
===================================================== -->

<section class="logs">

    <button class="logs-toggle" id="logsToggle" type="button" aria-expanded="true" aria-controls="logsBody"><strong>Leituras recentes <span id="count">(0)</span></strong><span class="chevron" aria-hidden="true">⌄</span></button>
    <div class="logs-body" id="logsBody">


    <div class="tablewrap">

        <table>

            <thead>

                <tr>

                    <th>
                        Data / hora
                    </th>

                    <th>
                        Temperatura
                    </th>

                    <th>
                        Umidade
                    </th>

                    <th>
                        Pressão
                    </th>

                </tr>

            </thead>


            <tbody id="tbody">

                <tr>

                    <td
                        colspan="4"
                        class="empty"
                    >
                        Aguardando dados…
                    </td>

                </tr>

            </tbody>

        </table>

    </div>

    </div>

</section>


</main>

<div class="overlay" id="notificationsOverlay" aria-hidden="true">
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="notificationsTitle">
    <div class="drawerhead">
      <h2 id="notificationsTitle">Central de notificações</h2>
      <button class="closebtn" type="button" data-close="notificationsOverlay" aria-label="Fechar">×</button>
    </div>
    <div class="toolbar">
      <button class="btn" id="markAllButton" type="button">Marcar todas como lidas</button>
      <button class="btn" id="testNotificationButton" type="button">🧪 Testar</button>
    </div>
    <div class="feedback" id="notificationsFeedback"></div>
    <div class="notice-list" id="notificationList"><div class="empty-panel">Carregando…</div></div>
  </aside>
</div>

<div class="overlay" id="settingsOverlay" aria-hidden="true">
  <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="settingsTitle">
    <div class="drawerhead">
      <h2 id="settingsTitle">Configurações de gatilhos</h2>
      <button class="closebtn" type="button" data-close="settingsOverlay" aria-label="Fechar">×</button>
    </div>
    <form id="settingsForm" class="formgrid">
      <label class="field full">Dispositivo<select id="configDevice"></select></label>
      <label class="field full">Nome da câmara<input id="configName" maxlength="80" required></label>
      <label class="field full check"><input id="configEnabled" type="checkbox"> Monitoramento ativo</label>
      <div class="formsection">Limites de alerta de temperatura</div>
      <label class="field">Alerta mínimo (°C)<input id="configMin" type="number" min="-50" max="79" step="0.1" required></label>
      <label class="field">Alerta máximo (°C)<input id="configMax" type="number" min="-49" max="80" step="0.1" required></label>
      <label class="field">Histerese (°C)<input id="configHysteresis" type="number" min="0" max="20" step="0.1" required></label>
      <label class="field">Tolerância (min)<input id="configTolerance" type="number" min="0" max="10080" step="1" required></label>
      <p class="field-help">A tolerância exige que a condição persista antes do alerta. A histerese define a margem para ele voltar ao normal.</p>
      <label class="field full check"><input id="configMonitorTemperature" type="checkbox"> Monitorar temperatura</label>
      <div class="formsection">Conectividade</div>
      <label class="field full">Considerar offline após (min)<input id="configOffline" type="number" min="1" max="10080" step="1" required></label>
      <label class="field full check"><input id="configMonitorOffline" type="checkbox"> Monitorar conectividade</label>
      <div class="field full"><button class="btn primary" id="saveSettingsButton" type="submit">Salvar configurações</button></div>
    </form>
    <div class="feedback" id="settingsFeedback"></div>
  </aside>
</div>

<div class="overlay auth-overlay" id="authOverlay" aria-hidden="true">
  <section class="drawer auth-dialog" role="dialog" aria-modal="true" aria-labelledby="authTitle">
    <div class="drawerhead">
      <h2 id="authTitle">Acesso administrativo</h2>
      <button class="closebtn" type="button" data-close="authOverlay" aria-label="Fechar">×</button>
    </div>
    <form class="auth-form" id="authForm">
      <label class="field">Senha administrativa<input id="adminPassword" type="password" autocomplete="current-password" required></label>
      <button class="btn primary" id="loginButton" type="submit">Entrar</button>
    </form>
    <div class="feedback" id="authFeedback" role="status"></div>
  </section>
</div>



<script>

let chart = null;

const $ = id => document.getElementById(id);
let selectedDeviceId = $('deviceSelect').value;
let refreshSequence = 0;
let overviewSequence = 0;
let selectedHours = 24;
let currentRows = [];
let currentEvents = [];
let currentLimits = null;
let latestTimestamp = null;
let adminAuthenticated = false;
let authStatusPromise = null;
let authWaiters = [];
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

const fmt = n => {
    const num = Number(n);
    return Number.isFinite(num)
        ? num.toFixed(1)
        : '--';
};


// ======================================================
// DATA/HORA
// ======================================================

function parseData(timestamp) {

    if (!timestamp) {
        return null;
    }

    const d = new Date(timestamp);

    if (Number.isNaN(d.getTime())) {
        return null;
    }

    return d;
}


function formatarData(timestamp) {

    const d = parseData(timestamp);

    if (!d) {
        return '--';
    }

    return d.toLocaleString('pt-BR', {
        dateStyle: 'short',
        timeStyle: 'medium'
    });
}

function ageText(timestamp) {
    const date = parseData(timestamp);
    if (!date) return 'sem leitura';
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return `há ${seconds} s`;
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `há ${Math.floor(seconds / 3600)} h`;
    return `há ${Math.floor(seconds / 86400)} d`;
}

const stateNames = {NORMAL: 'Normal', TEMP_ALTA: 'Temperatura alta', TEMP_BAIXA: 'Temperatura baixa', OFFLINE: 'Offline'};

function setStatus(state, latest, offlineMinutes) {
    const age = latest ? (Date.now() - parseData(latest.timestamp)?.getTime()) / 60000 : Infinity;
    const displayState = state === 'NORMAL' && age >= offlineMinutes ? 'STALE' : state;
    const label = displayState === 'STALE' ? 'Leitura atrasada' : (stateNames[displayState] || (latest ? 'Estado indisponível' : 'Sem dados'));
    $('operationalStatus').dataset.state = displayState || '';
    $('statusText').textContent = label;
    $('dot').style.background = displayState === 'NORMAL' ? 'var(--ok)' : displayState === 'STALE' ? 'var(--warn)' : (displayState ? 'var(--bad)' : 'var(--warn)');
}

function updateFreshness() {
    $('last').textContent = latestTimestamp ? `Atualizado ${ageText(latestTimestamp)}` : 'Aguardando primeira leitura…';
    $('lastExact').textContent = latestTimestamp ? `Última leitura: ${formatarData(latestTimestamp)}` : '';
    $('lastExact').dateTime = latestTimestamp || '';
}

async function refreshOverview() {
    const sequence = ++overviewSequence;
    try {
        const payload = await apiRequest('api.php?action=overview');
        if (sequence !== overviewSequence) return;
        const entries = Object.entries(payload.devices);
        const attention = entries.filter(([, device]) => {
            const age = device.latest ? (Date.now() - parseData(device.latest.timestamp)?.getTime()) / 60000 : Infinity;
            return device.state !== 'NORMAL' || age >= Number(device.offline_minutes);
        }).length;
        $('overviewSummary').textContent = attention ? `${entries.length} câmaras · ${attention} ${attention === 1 ? 'requer atenção' : 'requerem atenção'}` : `${entries.length} câmaras · todas normais`;
        $('deviceOverview').innerHTML = entries.map(([id, device]) => {
            const ageMinutes = device.latest ? (Date.now() - parseData(device.latest.timestamp)?.getTime()) / 60000 : Infinity;
            const state = device.state === 'NORMAL' && ageMinutes >= Number(device.offline_minutes) ? 'STALE' : device.state;
            const latest = device.latest;
            const label = state === 'STALE' ? 'Leitura atrasada' : (stateNames[state] || (latest ? 'Estado indisponível' : 'Sem dados'));
            const temp = latest ? `${fmt(latest.temperatura)} °C` : '-- °C';
            return `<button type="button" class="device-tile" data-device-id="${escapeHtml(id)}" aria-pressed="${id === selectedDeviceId}">
              <span class="device-tile-top"><span>${escapeHtml(device.name)}</span><span class="device-tile-temp">${temp}</span></span>
              <span class="device-tile-bottom"><span class="state-label" data-state="${escapeHtml(state || '')}">${escapeHtml(label)}</span><span>${escapeHtml(latest ? `Leitura ${ageText(latest.timestamp)}` : 'Sem leitura')}</span></span>
            </button>`;
        }).join('');
    } catch (error) {
        if (sequence === overviewSequence) $('overviewSummary').textContent = 'Falha ao carregar visão geral';
    }
}

$('deviceOverview').addEventListener('click', event => {
    const tile = event.target.closest('.device-tile');
    if (!tile || tile.dataset.deviceId === selectedDeviceId) return;
    $('deviceSelect').value = tile.dataset.deviceId;
    $('deviceSelect').dispatchEvent(new Event('change'));
});

$('logsToggle').addEventListener('click', () => {
    const expanded = $('logsToggle').getAttribute('aria-expanded') === 'true';
    $('logsToggle').setAttribute('aria-expanded', String(!expanded));
    $('logsBody').hidden = expanded;
});
if (matchMedia('(max-width: 760px)').matches) {
    $('logsToggle').setAttribute('aria-expanded', 'false');
    $('logsBody').hidden = true;
}


// ======================================================
// GRÁFICO
// ======================================================

const historyMarks = {
    id: 'historyMarks',
    afterDatasetsDraw(chartInstance) {
        const {ctx, chartArea, scales} = chartInstance;
        if (!chartArea || !currentLimits) return;
        ctx.save();
        const limits = [
            ['Mín.', Number(currentLimits.min), '#ffc857'],
            ['Máx.', Number(currentLimits.max), '#ff8b9e']
        ];
        limits.forEach(([label, value, color]) => {
            if (!Number.isFinite(value)) return;
            const y = scales.y.getPixelForValue(value);
            if (y < chartArea.top || y > chartArea.bottom) return;
            ctx.strokeStyle = color;
            ctx.globalAlpha = .65;
            ctx.setLineDash([4, 5]);
            ctx.beginPath(); ctx.moveTo(chartArea.left, y); ctx.lineTo(chartArea.right, y); ctx.stroke();
            ctx.globalAlpha = 1;
            ctx.fillStyle = color;
            ctx.font = '11px system-ui';
            ctx.textAlign = 'right';
            ctx.fillText(`${label} ${fmt(value)}°`, chartArea.right - 4, Math.max(chartArea.top + 11, y - 4));
        });
        ctx.setLineDash([]);
        let lastLabelX = -Infinity;
        currentEvents.forEach(event => {
            const time = parseData(event.created_at)?.getTime();
            if (!time || time < scales.x.min || time > scales.x.max) return;
            const x = scales.x.getPixelForValue(time);
            const risk = ['TEMP_HIGH', 'TEMP_LOW', 'OFFLINE'].includes(event.type);
            ctx.strokeStyle = risk ? '#ff8b9e' : '#50e3a4';
            ctx.globalAlpha = .75;
            ctx.setLineDash([2, 4]);
            ctx.beginPath(); ctx.moveTo(x, chartArea.top + 15); ctx.lineTo(x, chartArea.bottom); ctx.stroke();
            ctx.setLineDash([]);
            ctx.globalAlpha = 1;
            ctx.fillStyle = risk ? '#ff8b9e' : '#50e3a4';
            ctx.beginPath(); ctx.arc(x, chartArea.top + 8, 4, 0, Math.PI * 2); ctx.fill();
            if (x - lastLabelX > 84 && x < chartArea.right - 60) {
                ctx.font = '10px system-ui';
                ctx.textAlign = 'left';
                ctx.fillText(event.type.replace('TEMP_HIGH', 'Alta').replace('TEMP_LOW', 'Baixa').replace('NORMALIZED', 'Normal').replace('OFFLINE', 'Offline').replace('ONLINE', 'Online'), x + 6, chartArea.top + 11);
                lastLabelX = x;
            }
        });
        ctx.restore();
    }
};

function renderHistory() {
    const until = Date.now();
    const since = until - selectedHours * 3600000;
    const rows = currentRows.filter(row => {
        const time = parseData(row.timestamp)?.getTime();
        return time && time >= since && time <= until;
    });
    $('chartEmpty').hidden = rows.length > 0;
    currentEvents = currentEvents.filter(event => parseData(event.created_at));
    const eventsInPeriod = currentEvents.filter(event => {
        const time = parseData(event.created_at).getTime();
        return time >= since && time <= until;
    });
    $('chartNote').textContent = `Temperatura em destaque · limites de alerta · ${eventsInPeriod.length} ${eventsInPeriod.length === 1 ? 'evento' : 'eventos'} do Alert Engine`;
    $('historyEvents').innerHTML = eventsInPeriod.slice(-6).map(event => {
        const risk = ['TEMP_HIGH', 'TEMP_LOW', 'OFFLINE'].includes(event.type);
        const label = {TEMP_HIGH: 'Temperatura alta', TEMP_LOW: 'Temperatura baixa', OFFLINE: 'Offline', ONLINE: 'Online', NORMALIZED: 'Normalizada'}[event.type] || event.type;
        return `<span class="history-event ${risk ? 'risk' : ''}" title="${escapeHtml(formatarData(event.created_at))}">${escapeHtml(label)} · ${escapeHtml(formatarData(event.created_at))}</span>`;
    }).join('');
    const data = [
        {label: 'Temperatura °C', data: rows.map(row => ({x: parseData(row.timestamp).getTime(), y: Number(row.temperatura)})), yAxisID: 'y', borderColor: '#61b8ff', backgroundColor: '#61b8ff', borderWidth: 3, tension: .2, pointRadius: 0, pointHoverRadius: 4},
        {label: 'Umidade %', data: rows.map(row => ({x: parseData(row.timestamp).getTime(), y: Number(row.umidade)})), yAxisID: 'y1', borderColor: '#50e3a4', backgroundColor: '#50e3a4', borderWidth: 1.5, tension: .2, pointRadius: 0}
    ];
    const values = data[0].data.map(point => point.y).concat(currentLimits ? [Number(currentLimits.min), Number(currentLimits.max)] : []).filter(Number.isFinite);
    const min = values.length ? Math.min(...values) : 0;
    const max = values.length ? Math.max(...values) : 10;
    const padding = Math.max(1, (max - min) * .12);
    if (chart) chart.destroy();
    chart = new Chart($('chart'), {
        type: 'line', data: {datasets: data}, plugins: [historyMarks],
        options: {
            responsive: true, maintainAspectRatio: false, parsing: false, animation: false,
            interaction: {mode: 'nearest', intersect: false},
            plugins: {
                legend: {labels: {color: '#b8cad9', usePointStyle: true}},
                tooltip: {callbacks: {title: items => items.length ? formatarData(new Date(items[0].parsed.x).toISOString()) : ''}}
            },
            scales: {
                x: {type: 'linear', min: since, max: until, ticks: {color: '#7892aa', maxTicksLimit: 6, callback: value => new Date(value).toLocaleString('pt-BR', selectedHours === 168 ? {day: '2-digit', month: '2-digit'} : {hour: '2-digit', minute: '2-digit'})}, grid: {color: '#17314c'}},
                y: {position: 'left', min: min - padding, max: max + padding, ticks: {color: '#7892aa'}, grid: {color: '#17314c'}},
                y1: {position: 'right', ticks: {color: '#7892aa'}, grid: {drawOnChartArea: false}}
            }
        }
    });
}

document.querySelectorAll('.periods button').forEach(button => button.addEventListener('click', () => {
    selectedHours = Number(button.dataset.hours);
    document.querySelectorAll('.periods button').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    refresh();
}));


// ======================================================
// DASHBOARD
// ======================================================

async function refresh() {

    const deviceId = selectedDeviceId;
    const sequence = ++refreshSequence;

    try {

        const response = await fetch(
            'api.php?action=data&device_id='
            + encodeURIComponent(deviceId)
            + '&hours=' + selectedHours
            + '&_=' + Date.now(),
            {
                cache: 'no-store'
            }
        );


        if (!response.ok) {

            throw new Error(
                'HTTP ' + response.status
            );

        }


        const payload =
            await response.json();

        if (sequence !== refreshSequence || deviceId !== selectedDeviceId) return;

        if (
            !payload.ok
            || !Array.isArray(payload.logs)
        ) {
            throw new Error(
                payload.error || 'Resposta inválida da API'
            );
        }

        const leituras = payload.logs.length ? payload.logs : (payload.latest ? [payload.latest] : []);
        const offlineMinutes = Number(payload.offline_minutes) || 5;
        currentRows = leituras;
        currentEvents = Array.isArray(payload.events) ? payload.events : [];
        currentLimits = payload.temperature_limits || null;
        if (payload.device_name) $('deviceSelect').selectedOptions[0].textContent = payload.device_name;


        // =============================================
        // SEM DADOS
        // =============================================

        if (
            leituras.length === 0
        ) {

            $('temp').textContent =
                '--';

            $('hum').textContent =
                '--';

            $('press').textContent =
                '--';

            latestTimestamp = null;
            updateFreshness();
            setStatus(payload.state?.status, null, offlineMinutes);
            $('count').textContent = '(0)';

            $('tbody').innerHTML = `
                <tr>
                    <td
                        colspan="4"
                        class="empty"
                    >
                        Nenhuma leitura registrada.
                    </td>
                </tr>
            `;

            renderHistory();

            return;
        }


        // =============================================
        // ORDENA POR DATA
        // =============================================

        leituras.sort(
            (a, b) => {

                const da =
                    parseData(a.timestamp);

                const db =
                    parseData(b.timestamp);

                if (!da || !db) {
                    return 0;
                }

                return (
                    da.getTime()
                    -
                    db.getTime()
                );

            }
        );


        // =============================================
        // ÚLTIMA LEITURA
        // =============================================

        const atual =
            leituras[
                leituras.length - 1
            ];


        $('temp').textContent =
            fmt(
                atual.temperatura
            );


        $('hum').textContent =
            fmt(
                atual.umidade
            );


        $('press').textContent =
            fmt(
                atual.pressao
            );


        latestTimestamp = atual.timestamp;
        updateFreshness();


        // =============================================
        // STATUS
        // =============================================

        setStatus(payload.state?.status, atual, offlineMinutes);


        // =============================================
        // FILTRA 24 HORAS
        // =============================================

        const limite =
            Date.now()
            -
            (
                24
                *
                60
                *
                60
                *
                1000
            );


        const logs24h =
            leituras.filter(
                leitura => {

                    const d =
                        parseData(
                            leitura.timestamp
                        );

                    return (
                        d
                        &&
                        d.getTime() >= limite
                    );

                }
            );


        // =============================================
        // CONTADOR
        // =============================================

        $('count').textContent = `(${logs24h.length})`;


        // =============================================
        // TABELA
        // =============================================

        const recentes =
            [...logs24h]
            .reverse();


        $('tbody').innerHTML =
            recentes
            .map(
                v => `

                    <tr>

                        <td>
                            ${formatarData(
                                v.timestamp
                            )}
                        </td>

                        <td>
                            ${fmt(
                                v.temperatura
                            )} °C
                        </td>

                        <td>
                            ${fmt(
                                v.umidade
                            )} %
                        </td>

                        <td>
                            ${fmt(
                                v.pressao
                            )} hPa
                        </td>

                    </tr>

                `
            )
            .join('');


        // =============================================
        // GRÁFICO
        // =============================================

        renderHistory();

    }

    catch (e) {

        if (sequence !== refreshSequence || deviceId !== selectedDeviceId) return;

        console.error(
            'ERRO NO DASHBOARD:',
            e
        );


        $('statusText').textContent =
            'Erro ao carregar dados';
        $('operationalStatus').dataset.state = 'OFFLINE';


        $('dot').style.background =
            'var(--bad)';

    }

}

function clearDashboard() {
    $('temp').textContent = '--';
    $('hum').textContent = '--';
    $('press').textContent = '--';
    $('last').textContent = 'Carregando leituras…';
    $('lastExact').textContent = '';
    $('statusText').textContent = 'Carregando…';
    $('operationalStatus').dataset.state = '';
    $('dot').style.background = 'var(--warn)';
    $('count').textContent = '(0)';
    $('tbody').innerHTML = '<tr><td colspan="4" class="empty">Carregando leituras…</td></tr>';
    if (chart) { chart.destroy(); chart = null; }
    currentRows = [];
    currentEvents = [];
    currentLimits = null;
    latestTimestamp = null;
}

$('deviceSelect').addEventListener('change', () => {
    selectedDeviceId = $('deviceSelect').value;
    document.querySelectorAll('.device-tile').forEach(tile => tile.setAttribute('aria-pressed', String(tile.dataset.deviceId === selectedDeviceId)));
    clearDashboard();
    refresh();
});

const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
}[char]));

async function apiRequest(url, options = {}) {
    const response = await fetch(url, {cache: 'no-store', ...options});
    let payload;
    try { payload = await response.json(); } catch (_) { payload = {ok: false, error: 'Resposta inválida do servidor.'}; }
    if (!response.ok || !payload.ok) {
        const error = new Error(payload.error || `HTTP ${response.status}`);
        error.status = response.status;
        throw error;
    }
    return payload;
}

function setAdminState(authenticated) {
    adminAuthenticated = authenticated;
    $('adminIndicator').textContent = 'Admin ativo';
    $('adminIndicator').hidden = !authenticated;
    $('logoutButton').hidden = !authenticated;
}

async function refreshAuthStatus() {
    try {
        const status = await apiRequest('api.php?action=auth_status');
        setAdminState(Boolean(status.authenticated));
    } catch (_) {
        setAdminState(false);
    }
}

function resolveAuthWaiters(success) {
    const waiters = authWaiters;
    authWaiters = [];
    waiters.forEach(resolve => resolve(success));
}

async function ensureAdmin() {
    if (authStatusPromise) await authStatusPromise;
    if (adminAuthenticated) return true;
    $('authFeedback').textContent = '';
    $('authFeedback').className = 'feedback';
    openOverlay('authOverlay');
    $('adminPassword').focus();
    return new Promise(resolve => authWaiters.push(resolve));
}

async function adminPost(url, body) {
    if (!await ensureAdmin()) return null;
    const options = {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN},
        body: JSON.stringify(body)
    };
    try {
        return await apiRequest(url, options);
    } catch (error) {
        if (error.status !== 401) throw error;
        setAdminState(false);
        if (!await ensureAdmin()) return null;
        return apiRequest(url, options);
    }
}

function openOverlay(id) {
    const overlay = $(id);
    overlay.classList.add('open');
    overlay.setAttribute('aria-hidden', 'false');
}

function closeOverlay(id) {
    const overlay = $(id);
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    if (id === 'authOverlay') {
        $('adminPassword').value = '';
        resolveAuthWaiters(false);
    }
}

$('authForm').addEventListener('submit', async event => {
    event.preventDefault();
    const password = $('adminPassword').value;
    $('adminPassword').value = '';
    $('loginButton').disabled = true;
    $('authFeedback').textContent = '';
    try {
        await apiRequest('api.php?action=login', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': CSRF_TOKEN},
            body: JSON.stringify({password})
        });
        setAdminState(true);
        resolveAuthWaiters(true);
        closeOverlay('authOverlay');
    } catch (error) {
        $('authFeedback').className = 'feedback error';
        $('authFeedback').textContent = error.message;
    } finally {
        $('loginButton').disabled = false;
    }
});

$('logoutButton').addEventListener('click', async () => {
    try {
        await apiRequest('api.php?action=logout', {method: 'POST', headers: {'X-CSRF-Token': CSRF_TOKEN}});
        setAdminState(false);
        closeOverlay('settingsOverlay');
    } catch (error) {
        $('adminIndicator').textContent = error.message;
    }
});

document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => closeOverlay(button.dataset.close)));
document.querySelectorAll('.overlay').forEach(overlay => overlay.addEventListener('click', event => {
    if (event.target === overlay) closeOverlay(overlay.id);
}));
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(overlay => closeOverlay(overlay.id));
});

const eventPresentation = {
    TEMP_HIGH: ['🔴', 'Temperatura alta'],
    TEMP_LOW: ['🔵', 'Temperatura baixa'],
    NORMALIZED: ['🟢', 'Temperatura normalizada'],
    OFFLINE: ['⚫', 'Sensor offline'],
    ONLINE: ['🟢', 'Sensor online'],
    TEST_NOTIFICATION: ['🧪', 'Teste de notificação']
};

function relativeTime(timestamp) {
    const date = parseData(timestamp);
    if (!date) return '--';
    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
    if (seconds < 60) return 'agora';
    if (seconds < 3600) return `há ${Math.floor(seconds / 60)} min`;
    if (seconds < 86400) return `há ${Math.floor(seconds / 3600)} h`;
    return `há ${Math.floor(seconds / 86400)} d`;
}

function eventDataText(event) {
    const data = event.data || {};
    if (Number.isFinite(Number(data.temperature))) {
        let text = `${fmt(data.temperature)} °C`;
        if (Number.isFinite(Number(data.limit))) text += ` — limite ${fmt(data.limit)} °C`;
        return text;
    }
    if (event.type === 'OFFLINE' && Number.isFinite(Number(data.age_seconds))) {
        return `Última leitura há ${Math.max(1, Math.round(Number(data.age_seconds) / 60))} min`;
    }
    return '';
}

async function refreshNotifications() {
    try {
        const payload = await apiRequest('api.php?action=events&limit=50');
        $('notificationBadge').textContent = payload.unread_count > 99 ? '99+' : payload.unread_count;
        $('notificationBadge').hidden = payload.unread_count === 0;
        if (!payload.events.length) {
            $('notificationList').innerHTML = '<div class="empty-panel">Nenhum evento registrado.</div>';
            return;
        }
        $('notificationList').innerHTML = payload.events.map(event => {
            const presentation = eventPresentation[event.type] || ['🔔', event.type];
            const dataText = eventDataText(event);
            return `<article class="notice-item ${event.read ? '' : 'unread'}" data-event-id="${escapeHtml(event.id)}" data-read="${event.read ? '1' : '0'}">
                <div class="notice-title">${presentation[0]} ${escapeHtml(presentation[1])}</div>
                <div class="notice-meta">${escapeHtml(event.device_name)} · ${escapeHtml(relativeTime(event.created_at))}</div>
                <div class="notice-message">${escapeHtml(event.message)}</div>
                ${dataText ? `<div class="notice-data">${escapeHtml(dataText)}</div>` : ''}
            </article>`;
        }).join('');
        document.querySelectorAll('.notice-item.unread').forEach(item => item.addEventListener('click', () => markNotificationRead(item.dataset.eventId)));
        $('notificationsFeedback').textContent = '';
    } catch (error) {
        $('notificationsFeedback').className = 'feedback error';
        $('notificationsFeedback').textContent = error.message;
    }
}

async function writeAction(action, body) {
    return adminPost(`api.php?action=${encodeURIComponent(action)}`, body);
}

async function markNotificationRead(eventId) {
    try { if (await writeAction('notification_read', {event_id: eventId})) await refreshNotifications(); }
    catch (error) { $('notificationsFeedback').className = 'feedback error'; $('notificationsFeedback').textContent = error.message; }
}

$('notificationsButton').addEventListener('click', async () => { openOverlay('notificationsOverlay'); await refreshNotifications(); });
$('markAllButton').addEventListener('click', async () => {
    $('markAllButton').disabled = true;
    try { if (await writeAction('notifications_read_all', {})) await refreshNotifications(); }
    catch (error) { $('notificationsFeedback').className = 'feedback error'; $('notificationsFeedback').textContent = error.message; }
    finally { $('markAllButton').disabled = false; }
});
$('testNotificationButton').addEventListener('click', async () => {
    $('testNotificationButton').disabled = true;
    try { if (await writeAction('test_notification', {device_id: selectedDeviceId})) await refreshNotifications(); }
    catch (error) { $('notificationsFeedback').className = 'feedback error'; $('notificationsFeedback').textContent = error.message; }
    finally { $('testNotificationButton').disabled = false; }
});

let editableDevices = {};

function fillSettings(deviceId) {
    const device = editableDevices[deviceId];
    if (!device) return;
    $('configName').value = device.nome;
    $('configEnabled').checked = device.habilitado;
    $('configMin').value = device.temperatura_min;
    $('configMax').value = device.temperatura_max;
    $('configHysteresis').value = device.histerese_temperatura;
    $('configTolerance').value = device.tolerancia_minutos;
    $('configOffline').value = device.offline_minutos;
    $('configMonitorTemperature').checked = device.monitoramentos.temperatura;
    $('configMonitorOffline').checked = device.monitoramentos.offline;
}

async function loadSettings() {
    $('settingsFeedback').className = 'feedback';
    $('settingsFeedback').textContent = 'Carregando…';
    try {
        const payload = await apiRequest('api.php?action=devices');
        editableDevices = payload.devices;
        $('configDevice').innerHTML = Object.entries(editableDevices).map(([id, device]) => `<option value="${escapeHtml(id)}">${escapeHtml(device.nome)} (${escapeHtml(id)})</option>`).join('');
        $('configDevice').value = editableDevices[selectedDeviceId] ? selectedDeviceId : Object.keys(editableDevices)[0];
        fillSettings($('configDevice').value);
        $('settingsFeedback').textContent = '';
    } catch (error) {
        $('settingsFeedback').className = 'feedback error';
        $('settingsFeedback').textContent = error.message;
    }
}

$('configDevice').addEventListener('change', () => fillSettings($('configDevice').value));
$('settingsButton').addEventListener('click', async () => {
    if (!await ensureAdmin()) return;
    openOverlay('settingsOverlay');
    await loadSettings();
});
$('settingsForm').addEventListener('submit', async event => {
    event.preventDefault();
    const deviceId = $('configDevice').value;
    const minimum = Number($('configMin').value);
    const maximum = Number($('configMax').value);
    const tolerance = Number($('configTolerance').value);
    const offline = Number($('configOffline').value);
    const hysteresis = Number($('configHysteresis').value);
    if (!Number.isFinite(minimum) || !Number.isFinite(maximum) || minimum >= maximum || !Number.isInteger(tolerance) || tolerance < 0 || !Number.isInteger(offline) || offline <= 0 || !Number.isFinite(hysteresis) || hysteresis < 0) {
        $('settingsFeedback').className = 'feedback error';
        $('settingsFeedback').textContent = 'Revise os limites: mínima deve ser menor que máxima e os tempos devem ser válidos.';
        return;
    }
    const body = {
        nome: $('configName').value.trim(), habilitado: $('configEnabled').checked,
        temperatura_min: minimum, temperatura_max: maximum,
        tolerancia_minutos: tolerance, offline_minutos: offline,
        histerese_temperatura: hysteresis,
        monitoramentos: {temperatura: $('configMonitorTemperature').checked, offline: $('configMonitorOffline').checked}
    };
    $('saveSettingsButton').disabled = true;
    $('settingsFeedback').className = 'feedback';
    $('settingsFeedback').textContent = 'Salvando…';
    try {
        const payload = await adminPost(`api.php?action=device_config&device_id=${encodeURIComponent(deviceId)}`, body);
        if (!payload) return;
        editableDevices[deviceId] = payload.config;
        $('settingsFeedback').className = 'feedback success';
        $('settingsFeedback').textContent = 'Configurações salvas.';
        const option = Array.from($('deviceSelect').options).find(item => item.value === deviceId);
        if (option) option.textContent = payload.config.nome;
        refreshOverview();
        if (deviceId === selectedDeviceId) refresh();
    } catch (error) {
        $('settingsFeedback').className = 'feedback error';
        $('settingsFeedback').textContent = error.message;
    } finally { $('saveSettingsButton').disabled = false; }
});


// ======================================================
// START
// ======================================================

refresh();
authStatusPromise = refreshAuthStatus();
refreshOverview();
refreshNotifications();

setInterval(
    refresh,
    15000
);

setInterval(refreshNotifications, 15000);
setInterval(refreshOverview, 15000);
setInterval(updateFreshness, 1000);

</script>

</body>

</html>
