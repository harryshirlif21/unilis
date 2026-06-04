<!-- CO2 Monitor Widget with Lab Analytics Modal
     Include this in student, lecturer, and admin dashboards
-->

<div id="co2MonitorWidget" class="co2-widget">
    <div class="co2-card">
        <div class="co2-header">
            <h3 class="co2-title">Lab Air Quality</h3>
            <button type="button" class="btn-analytics" onclick="openLabAnalyticsModal()" title="View detailed analytics">
                📊 Lab Analytics
            </button>
        </div>
        
        <div id="co2Status" class="co2-status">
            <div class="co2-loading">Loading air quality data...</div>
        </div>
        
        <div class="co2-footer">
            <small class="co2-updated">Last updated: <span id="co2Timestamp">--:--:--</span></small>
        </div>
    </div>
</div>

<!-- Lab Analytics Modal -->
<div id="labAnalyticsModal" class="modal-overlay hidden">
    <div class="modal-backdrop" onclick="closeLabAnalyticsModal()"></div>
    <div class="modal-card modal-large">
        <div class="modal-card-header">
            <div>
                <h2>Lab Analytics</h2>
                <p>CO2 levels and air quality trends</p>
            </div>
            <button class="modal-close" onclick="closeLabAnalyticsModal()" aria-label="Close">&#x2715;</button>
        </div>

        <div class="modal-body">
            <!-- Date range selector -->
            <div class="analytics-controls">
                <div class="control-group">
                    <label class="field-label">View period:</label>
                    <div class="date-range">
                        <input type="date" id="analyticsStartDate" class="form-control" />
                        <span class="range-sep">to</span>
                        <input type="date" id="analyticsEndDate" class="form-control" />
                    </div>
                </div>
                <button type="button" class="btn btn-primary btn-sm" onclick="loadAnalyticsData()">Load Data</button>
            </div>

            <!-- Chart -->
            <div id="analyticsChart" class="analytics-chart" style="margin-top: 1.5rem; height: 300px;">
                <canvas id="analyticsChartCanvas" height="240"></canvas>
            </div>

            <!-- Statistics -->
            <div class="analytics-stats" style="margin-top: 1.5rem;">
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="stat-label">Average PPM</div>
                        <div class="stat-value" id="statAverage">--</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Peak PPM</div>
                        <div class="stat-value" id="statPeak">--</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Min PPM</div>
                        <div class="stat-value" id="statMin">--</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Hours in Good</div>
                        <div class="stat-value" id="statGoodHours">--</div>
                    </div>
                </div>
            </div>

            <!-- Data table -->
            <div class="analytics-table" style="margin-top: 1.5rem;">
                <h4>Detailed Readings</h4>
                <div id="dataTableContainer" class="table-container">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>PPM</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="dataTableBody">
                            <tr><td colspan="4" class="text-center">Loading data...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeLabAnalyticsModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* CO2 Monitor Widget Styles */
.co2-widget {
    margin-bottom: 1.5rem;
}

.co2-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1.5px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.25rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    transition: box-shadow .2s;
}

.co2-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,.1);
}

.co2-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1rem;
}

.co2-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
}

.btn-analytics {
    padding: .5rem 1rem;
    border: none;
    border-radius: 8px;
    background: #f1f5f9;
    color: #334155;
    font-size: .85rem;
    font-weight: 500;
    cursor: pointer;
    transition: background .2s, color .2s;
    white-space: nowrap;
}

.btn-analytics:hover {
    background: #e2e8f0;
    color: #0f172a;
}

.co2-status {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.co2-status-item {
    padding: 1rem;
    border-radius: 12px;
    text-align: center;
    border: 2px solid #e2e8f0;
    transition: transform .2s, border-color .2s;
}

.co2-status-item:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
}

.co2-status-item.warning {
    background: #fff1f2;
    border-color: #fca5a5;
}

.co2-ppm {
    font-size: 2rem;
    font-weight: 800;
    margin: 0.5rem 0;
    font-family: 'DM Mono', monospace;
}

.co2-status-text {
    font-size: .85rem;
    font-weight: 600;
    margin: 0.5rem 0 0;
}

.co2-status-text.excellent { color: #2E8B57; }
.co2-status-text.good { color: #1E6FBA; }
.co2-status-text.fair { color: #D4AF37; }
.co2-status-text.poor { color: #DC3545; }

.co2-warning-badge {
    display: inline-block;
    background: #DC3545;
    color: white;
    padding: .3rem .6rem;
    border-radius: 6px;
    font-size: .75rem;
    font-weight: 700;
    margin-top: 0.5rem;
}

.co2-loading {
    padding: 2rem;
    text-align: center;
    color: #64748b;
    font-size: .9rem;
}

.co2-footer {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.co2-updated {
    color: #94a3b8;
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 1100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}

.modal-overlay.hidden {
    display: none !important;
}

.modal-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0,0,0,.52);
    backdrop-filter: blur(2px);
}

.modal-card {
    position: relative;
    z-index: 1;
    width: min(100%, 560px);
    background: #fff;
    border-radius: 18px;
    box-shadow: 0 28px 72px rgba(0,0,0,.22);
    overflow: hidden;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-card.modal-large {
    width: min(100%, 900px);
}

.modal-card-header {
    padding: 1.4rem 1.6rem .8rem;
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    border-bottom: 1px solid #f0f0f0;
}

.modal-card-header h2 {
    margin: 0 0 .2rem;
    font-size: 1.2rem;
    font-weight: 700;
    color: #0f172a;
}

.modal-card-header p {
    margin: 0;
    color: #64748b;
    font-size: .9rem;
}

.modal-close {
    border: none;
    background: transparent;
    font-size: 1.4rem;
    line-height: 1;
    cursor: pointer;
    color: #94a3b8;
    padding: .2rem .4rem;
    border-radius: 6px;
    transition: background .15s;
}

.modal-close:hover {
    background: #f1f5f9;
    color: #334155;
}

.modal-body {
    padding: 1.2rem 1.6rem 1rem;
}

.modal-footer {
    display: flex;
    gap: .65rem;
    align-items: center;
    justify-content: flex-end;
    padding: .85rem 1.6rem 1.25rem;
    border-top: 1px solid #f0f0f0;
}

.btn {
    border: none;
    border-radius: 10px;
    padding: .8rem 1.35rem;
    cursor: pointer;
    font-size: .9rem;
    font-weight: 500;
    transition: opacity .15s, transform .1s;
}

.btn:active { transform: scale(.97); }
.btn:disabled { opacity: .45; cursor: not-allowed; }
.btn-primary { background: #2563eb; color: #fff; }
.btn-primary:hover:not(:disabled) { background: #1d4ed8; }
.btn-secondary { background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; }
.btn-secondary:hover { background: #e2e8f0; }
.btn-sm { padding: .6rem 1rem; font-size: .85rem; }

/* Analytics Controls */
.analytics-controls {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: flex-end;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 12px;
}

.control-group {
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.field-label {
    font-weight: 600;
    font-size: .9rem;
    color: #1e293b;
}

.date-range {
    display: flex;
    gap: .5rem;
    align-items: center;
}

.form-control {
    padding: .7rem .9rem;
    border-radius: 10px;
    border: 1.5px solid #e2e8f0;
    font-size: .9rem;
    outline: none;
    transition: border-color .2s;
}

.form-control:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37,99,235,.1);
}

.range-sep {
    color: #94a3b8;
    padding: 0 .25rem;
}

/* Chart Styles */
.analytics-chart {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.5rem;
    background: #f8fafc;
}

.chart-placeholder {
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: .9rem;
}

/* Statistics Grid */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
}

.stat-box {
    padding: 1rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    background: #f8fafc;
    text-align: center;
}

.stat-label {
    font-size: .8rem;
    color: #94a3b8;
    font-weight: 600;
    margin-bottom: .5rem;
}

.stat-value {
    font-size: 1.5rem;
    font-weight: 800;
    color: #0f172a;
    font-family: 'DM Mono', monospace;
}

/* Data Table */
.table-container {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
    max-height: 400px;
    overflow-y: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8fafc;
    position: sticky;
    top: 0;
}

.data-table th {
    padding: .8rem;
    text-align: left;
    font-weight: 700;
    color: #334155;
    border-bottom: 1.5px solid #e2e8f0;
    font-size: .85rem;
}

.data-table td {
    padding: .8rem;
    border-bottom: 1px solid #e2e8f0;
    color: #475569;
    font-size: .85rem;
}

.data-table tbody tr:hover {
    background: #f8fafc;
}

.data-table .text-center {
    text-align: center;
}

/* Responsive */
@media (max-width: 768px) {
    .modal-card { width: min(100%, 95%); }
    .analytics-controls {
        grid-template-columns: 1fr;
    }
    .date-range {
        flex-direction: column;
    }
    .stat-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<script>
// CO2 Status Update on Page Load
document.addEventListener('DOMContentLoaded', function() {
    updateCo2Status();
    // Refresh CO2 status every 30 seconds
    setInterval(updateCo2Status, 30000);
    
    // Set default date range for analytics (last 7 days)
    var endDate = new Date();
    var startDate = new Date(endDate);
    startDate.setDate(startDate.getDate() - 7);
    
    document.getElementById('analyticsStartDate').valueAsDate = startDate;
    document.getElementById('analyticsEndDate').valueAsDate = endDate;
});

function updateCo2Status() {
    const statusEl = document.getElementById('co2Status');
    if (!statusEl) return;
    
    fetch('<?= APP_URL ?>/includes/SensorServerClient.php?action=co2_status', {
        cache: 'no-store'
    })
    .then(r => r.json())
    .then(data => {
        if (!data.has_reading) {
            statusEl.innerHTML = '<div class="co2-loading">No CO2 data available</div>';
            return;
        }
        
        const warningClass = data.is_warning ? ' warning' : '';
        const statusClass = {
            'Excellent': 'excellent',
            'Good': 'good',
            'Fair (Stale)': 'fair',
            'Poor / Ventilation Required': 'poor'
        }[data.status] || 'good';
        
        let html = `
            <div class="co2-status-item${warningClass}">
                <div style="font-size: 0.8rem; color: #94a3b8; margin-bottom: 0.3rem;">CO2 Level</div>
                <div class="co2-ppm">${data.ppm}</div>
                <div style="font-size: 0.75rem; color: #94a3b8;">PPM</div>
                <div class="co2-status-text ${statusClass}">${data.status}</div>
                ${data.is_warning ? '<div class="co2-warning-badge">⚠️ Ventilation Needed</div>' : ''}
            </div>
        `;
        
        statusEl.innerHTML = html;
        document.getElementById('co2Timestamp').textContent = data.timestamp;
    })
    .catch(err => {
        statusEl.innerHTML = '<div class="co2-loading">Error loading CO2 data</div>';
    });
}

function openLabAnalyticsModal() {
    document.getElementById('labAnalyticsModal').classList.remove('hidden');
    loadAnalyticsData();
}

function closeLabAnalyticsModal() {
    document.getElementById('labAnalyticsModal').classList.add('hidden');
}

function loadAnalyticsData() {
    const startDate = document.getElementById('analyticsStartDate').value;
    const endDate = document.getElementById('analyticsEndDate').value;
    
    if (!startDate || !endDate) {
        alert('Please select a date range');
        return;
    }
    
    fetch(`<?= APP_URL ?>/includes/SensorServerClient.php?action=co2_range&start_date=${startDate}&end_date=${endDate}`, {
        cache: 'no-store'
    })
    .then(r => r.json())
    .then(data => {
        if (!Array.isArray(data) || data.length === 0) {
            document.getElementById('dataTableBody').innerHTML = '<tr><td colspan="4" class="text-center">No data available</td></tr>';
            return;
        }
        
        // Calculate statistics
        const ppms = data.map(r => r.ppm);
        const average = Math.round(ppms.reduce((a,b) => a+b, 0) / ppms.length);
        const peak = Math.max(...ppms);
        const min = Math.min(...ppms);
        const goodCount = ppms.filter(p => p <= 1000).length;
        
        document.getElementById('statAverage').textContent = average;
        document.getElementById('statPeak').textContent = peak;
        document.getElementById('statMin').textContent = min;
        document.getElementById('statGoodHours').textContent = goodCount;
        
        // Build table
        let html = '';
        data.forEach(reading => {
            html += `
                <tr>
                    <td>${reading.date || 'N/A'}</td>
                    <td>${reading.timestamp}</td>
                    <td><strong>${reading.ppm}</strong></td>
                    <td><span style="color: ${reading.color};">●</span> ${reading.status}</td>
                </tr>
            `;
        });
        
        document.getElementById('dataTableBody').innerHTML = html;
    })
    .catch(err => {
        console.error('Analytics error:', err);
        document.getElementById('dataTableBody').innerHTML = '<tr><td colspan="4" class="text-center">Error loading data</td></tr>';
    });
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.id === 'labAnalyticsModal') {
        closeLabAnalyticsModal();
    }
});
</script>

<script>
let analyticsChart = null;

function ensureChartLibrary() {
    if (typeof Chart !== 'undefined') {
        return Promise.resolve();
    }

    if (!window.__smartlabChartLoader) {
        window.__smartlabChartLoader = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Chart.js failed to load'));
            document.head.appendChild(script);
        });
    }

    return window.__smartlabChartLoader;
}

function renderAnalyticsChart(readings) {
    const canvas = document.getElementById('analyticsChartCanvas');
    if (!canvas || typeof Chart === 'undefined') {
        return;
    }

    const context = canvas.getContext('2d');
    if (analyticsChart) {
        analyticsChart.destroy();
    }

    analyticsChart = new Chart(context, {
        type: 'line',
        data: {
            labels: readings.map(reading => `${reading.date || ''} ${reading.timestamp || ''}`.trim()),
            datasets: [{
                label: 'CO2 (PPM)',
                data: readings.map(reading => Number(reading.ppm) || 0),
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                tension: 0.35,
                fill: true,
                pointRadius: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: true }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(148, 163, 184, 0.16)' }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}

function openLabAnalyticsModal() {
    const modal = document.getElementById('labAnalyticsModal');
    if (!modal) return;
    modal.classList.remove('hidden');
    loadAnalyticsData();
}

function closeLabAnalyticsModal() {
    const modal = document.getElementById('labAnalyticsModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function loadAnalyticsData() {
    const startDate = document.getElementById('analyticsStartDate').value;
    const endDate = document.getElementById('analyticsEndDate').value;
    const tableBody = document.getElementById('dataTableBody');

    if (!startDate || !endDate) {
        alert('Please select a date range');
        return;
    }

    if (tableBody) {
        tableBody.innerHTML = '<tr><td colspan="4" class="text-center">Loading data...</td></tr>';
    }

    ensureChartLibrary()
        .then(() => fetch(`<?= APP_URL ?>/includes/SensorServerClient.php?action=co2_range&start_date=${startDate}&end_date=${endDate}`, {
            cache: 'no-store'
        }))
        .then(r => r.json())
        .then(data => {
            if (!Array.isArray(data) || data.length === 0) {
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="4" class="text-center">No data available</td></tr>';
                }
                if (analyticsChart) {
                    analyticsChart.destroy();
                    analyticsChart = null;
                }
                return;
            }

            const readings = data.map(reading => ({
                date: reading.date || 'N/A',
                timestamp: reading.timestamp || '--:--:--',
                ppm: Number(reading.ppm || reading.co2_ppm || 0),
                status: reading.status || 'Unknown',
                color: reading.color || '#64748b'
            }));

            const ppms = readings.map(reading => reading.ppm);
            const average = Math.round(ppms.reduce((sum, value) => sum + value, 0) / ppms.length);
            const peak = Math.max(...ppms);
            const min = Math.min(...ppms);
            const goodCount = ppms.filter(value => value <= 1000).length;

            document.getElementById('statAverage').textContent = average;
            document.getElementById('statPeak').textContent = peak;
            document.getElementById('statMin').textContent = min;
            document.getElementById('statGoodHours').textContent = goodCount;

            renderAnalyticsChart(readings);

            if (tableBody) {
                tableBody.innerHTML = readings.map(reading => `
                    <tr>
                        <td>${reading.date}</td>
                        <td>${reading.timestamp}</td>
                        <td><strong>${reading.ppm}</strong></td>
                        <td><span style="color: ${reading.color};">●</span> ${reading.status}</td>
                    </tr>
                `).join('');
            }
        })
        .catch(err => {
            console.error('Analytics error:', err);
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="4" class="text-center">Error loading data</td></tr>';
            }
        });
}
</script>
