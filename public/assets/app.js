/**
 * Matomo リアルタイムダッシュボード フロントエンド
 */

// グローバル変数
let activeChart = null;
let hourlyChart = null;
let lastActiveData = null;
let lastHourlyData = null;

// 設定
const CONFIG = {
    ACTIVE_INTERVAL: 60000,  // 60秒
    HOURLY_INTERVAL: 300000, // 5分
    API_BASE: '../api',
};

/**
 * 初期化
 */
document.addEventListener('DOMContentLoaded', () => {
    initCharts();
    updateCurrentTime();
    loadActiveData();
    loadHourlyData();

    // 定期更新
    setInterval(updateCurrentTime, 1000);
    setInterval(loadActiveData, CONFIG.ACTIVE_INTERVAL);
    setInterval(loadHourlyData, CONFIG.HOURLY_INTERVAL);
});

/**
 * 現在時刻を更新
 */
function updateCurrentTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('ja-JP', { hour12: false });
    document.getElementById('current-time').textContent = timeStr;
}

/**
 * アラート表示
 */
function showAlert(message, type = 'error') {
    const container = document.getElementById('alert-container');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;

    container.appendChild(alert);

    // 5秒後に自動削除
    setTimeout(() => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

/**
 * API呼び出し
 */
async function fetchApi(endpoint) {
    try {
        const response = await fetch(`${CONFIG.API_BASE}/${endpoint}`, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const data = await response.json();

        // エラーレスポンスチェック
        if (data.error) {
            throw new Error(data.error.message || 'APIエラー');
        }

        return data;

    } catch (error) {
        console.error(`API Error (${endpoint}):`, error);
        throw error;
    }
}

/**
 * グラフ初期化
 */
function initCharts() {
    // Chart.jsのデフォルト設定
    Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.color = getComputedStyle(document.documentElement).getPropertyValue('--text-color').trim() || '#333';

    // 直近30分グラフ
    const activeCtx = document.getElementById('active-chart').getContext('2d');
    activeChart = new Chart(activeCtx, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: '合計',
                data: [],
                backgroundColor: 'rgba(52, 152, 219, 0.8)',
                borderColor: 'rgba(52, 152, 219, 1)',
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                    },
                },
            },
        },
    });

    // 時間帯別グラフ
    const hourlyCtx = document.getElementById('hourly-chart').getContext('2d');
    hourlyChart = new Chart(hourlyCtx, {
        type: 'bar',
        data: {
            labels: Array.from({ length: 24 }, (_, i) => `${i}時`),
            datasets: [{
                label: '訪問数',
                data: Array(24).fill(0),
                backgroundColor: 'rgba(46, 204, 113, 0.8)',
                borderColor: 'rgba(46, 204, 113, 1)',
                borderWidth: 1,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false,
                },
                tooltip: {
                    callbacks: {
                        title: (context) => {
                            return `${context[0].label}台`;
                        },
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                    },
                },
            },
        },
    });
}

/**
 * 直近30分データ読み込み
 */
async function loadActiveData() {
    try {
        const data = await fetchApi('active_30.php');

        lastActiveData = data;

        // 数値カード更新
        document.getElementById('active-30-value').textContent = data.total_active_30.toLocaleString();

        const updatedAt = new Date(data.updated_at);
        document.getElementById('active-30-updated').textContent =
            `更新: ${updatedAt.toLocaleTimeString('ja-JP')}`;

        // グラフ更新
        updateActiveChart(data);

    } catch (error) {
        console.error('Active data load error:', error);

        // 最後のデータがあればそれを表示
        if (lastActiveData) {
            showAlert('最新データの取得に失敗しました（キャッシュ表示中）', 'warning');
            updateActiveChart(lastActiveData);
        } else {
            showAlert('直近30分データの取得に失敗しました', 'error');
            document.getElementById('active-30-updated').textContent = 'エラー';
        }
    }
}

/**
 * 時間帯別データ読み込み
 */
async function loadHourlyData() {
    try {
        const data = await fetchApi('hourly_today.php');

        lastHourlyData = data;

        // 今日の総訪問数を計算
        const totalVisits = data.visits.reduce((sum, val) => sum + val, 0);
        document.getElementById('today-total-value').textContent = totalVisits.toLocaleString();

        const updatedAt = new Date(data.updated_at);
        document.getElementById('hourly-updated').textContent =
            `更新: ${updatedAt.toLocaleTimeString('ja-JP')}`;

        // グラフ更新
        updateHourlyChart(data);

    } catch (error) {
        console.error('Hourly data load error:', error);

        // 最後のデータがあればそれを表示
        if (lastHourlyData) {
            showAlert('最新データの取得に失敗しました（キャッシュ表示中）', 'warning');
            updateHourlyChart(lastHourlyData);
        } else {
            showAlert('時間帯別データの取得に失敗しました', 'error');
            document.getElementById('hourly-updated').textContent = 'エラー';
        }
    }
}

/**
 * 直近30分グラフ更新
 */
function updateActiveChart(data) {
    if (!activeChart || !data.by_site) return;

    // サイト別データを準備
    const labels = data.by_site.map(site => `サイト ${site.idSite}`);
    const values = data.by_site.map(site => site.active_30);

    // データセット更新
    activeChart.data.labels = labels;
    activeChart.data.datasets[0].data = values;

    activeChart.update();
}

/**
 * 時間帯別グラフ更新
 */
function updateHourlyChart(data) {
    if (!hourlyChart || !data.visits) return;

    // データセット更新
    hourlyChart.data.datasets[0].data = data.visits;

    hourlyChart.update();
}

/**
 * エラーハンドリング
 */
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
});

window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
});
