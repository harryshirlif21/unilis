<!-- Technician View for Smart Lab Projection -->
<div class="smartlab-section technician-section">

    <!-- System Monitoring Panel -->
    <div class="section-panel system-status-panel">
        <div class="panel-header">
            <h2>System Status</h2>
            <div class="camera-controls">
                <button class="smartlab-btn camera-open-btn" id="techCameraOpenBtn" onclick="technicianView.openCamera()">
                    <span class="camera-icon">📹</span>
                    <span class="camera-text">Open Camera</span>
                </button>
                <button class="smartlab-btn camera-toggle-btn" id="techCameraToggleBtn" onclick="technicianView.toggleAllCameras()">
                    <span class="toggle-icon">📷</span>
                    <span class="toggle-text">Turn On Cameras</span>
                </button>
            </div>
        </div>
        <div class="panel-content status-grid">
            <div class="status-item">
                <div class="status-icon cameras">📹</div>
                <div class="status-info">
                    <div class="status-label">Cameras</div>
                    <div class="status-value" id="cameraStatus">0/0 Active</div>
                    <div class="status-bar">
                        <div class="status-fill" id="cameraFill" style="width: 100%; background-color: #22c55e;"></div>
                    </div>
                </div>
            </div>
            <div class="status-item">
                <div class="status-icon sensors">📊</div>
                <div class="status-info">
                    <div class="status-label">Sensors</div>
                    <div class="status-value" id="sensorStatus">0/0 Active</div>
                    <div class="status-bar">
                        <div class="status-fill" id="sensorFill" style="width: 100%; background-color: #22c55e;"></div>
                    </div>
                </div>
            </div>
            <div class="status-item">
                <div class="status-icon">🌐</div>
                <div class="status-info">
                    <div class="status-label">Network</div>
                    <div class="status-value">Connected</div>
                    <div class="status-indicator connected">⚫ Online</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Safety Alerts Section -->
    <div class="section-panel alerts-panel full-width">
        <div class="panel-header">
            <h2>Safety Alerts</h2>
        </div>
        <div class="panel-content">
            <div id="safetyAlertsList" class="alerts-list">
                <div class="alert-item warning">
                    <span class="alert-icon">⚠️</span>
                    <span class="alert-text">Temperature slightly elevated in Lab C</span>
                    <span class="alert-time">2 mins ago</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Record Panel -->
    <div class="section-panel search-panel full-width">
        <div class="panel-header">
            <h2>Practical Records</h2>
        </div>
        <div class="panel-content">
            <div class="search-group">
                <div class="search-input-wrapper">
                    <input 
                        type="text" 
                        id="barcodSearchInput" 
                        placeholder="Scan or enter student barcode/ID..."
                        class="search-input"
                        onkeypress="technicianView.onSearchKeyPress(event)"
                    />
                    <button class="search-btn" onclick="technicianView.searchStudent()">🔍</button>
                </div>
            </div>

            <!-- Student Details Card -->
            <div id="studentDetailsCard" class="student-card hidden">
                <div class="card-header">
                    <h3>Student Information</h3>
                    <button class="close-btn" onclick="technicianView.clearSearch()">✕</button>
                </div>
                <div class="card-content">
                    <div class="detail-row">
                        <span class="detail-label">Name:</span>
                        <span class="detail-value" id="studentDetailName">--</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Registration:</span>
                        <span class="detail-value" id="studentDetailReg">--</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value" id="studentDetailEmail">--</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Lab:</span>
                        <span class="detail-value" id="studentDetailLab">--</span>
                    </div>

                    <!-- Practical Readings Table -->
                    <div class="readings-section">
                        <h4>Current Practical Readings</h4>
                        <table class="readings-table">
                            <thead>
                                <tr>
                                    <th>Experiment</th>
                                    <th>Start Time</th>
                                    <th>Status</th>
                                    <th>Completion</th>
                                </tr>
                            </thead>
                            <tbody id="readingsTableBody">
                                <tr class="empty-row">
                                    <td colspan="4">No readings available</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Blockchain Hash Info -->
                    <div class="blockchain-info">
                        <div class="info-label">Blockchain Hash:</div>
                        <div class="info-value" id="blockchainHash">--</div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button class="action-btn preview" onclick="technicianView.previewReport()">
                            👁️ Preview Report
                        </button>
                        <button class="action-btn print" onclick="technicianView.printReport()">
                            🖨️ Print
                        </button>
                        <button class="action-btn export" onclick="technicianView.exportPDF()">
                            📥 Export PDF
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    window.technicianView = {
        currentStudent: null,
        currentPractical: null,
        cameraStatus: {}, // Track camera on/off status

        init: function() {
            this.loadSystemStatus();
            this.loadSafetyAlerts();
            // Refresh system status every 30 seconds
            setInterval(() => this.loadSystemStatus(), 30000);
        },

        loadSystemStatus: function() {
            fetch(`<?= APP_URL ?>/smartlab/apiSystemStatus`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const status = data.data;
                        
                        // Update cameras
                        if (status.cameras) {
                            const cameraPercent = status.cameras.total > 0 
                                ? Math.round((status.cameras.active / status.cameras.total) * 100)
                                : 0;
                            document.getElementById('cameraStatus').textContent = 
                                `${status.cameras.active}/${status.cameras.total} Active`;
                            document.getElementById('cameraFill').style.width = cameraPercent + '%';
                            document.getElementById('cameraFill').style.backgroundColor = 
                                cameraPercent >= 80 ? '#22c55e' : (cameraPercent >= 50 ? '#eab308' : '#ef4444');
                            
                            // Track camera status for toggle button
                            const isAllActive = cameraPercent === 100 && status.cameras.total > 0;
                            this.updateCameraToggleButton(isAllActive);
                        }

                        // Update sensors
                        if (status.sensors) {
                            const sensorPercent = status.sensors.total > 0
                                ? Math.round((status.sensors.active / status.sensors.total) * 100)
                                : 0;
                            document.getElementById('sensorStatus').textContent = 
                                `${status.sensors.active}/${status.sensors.total} Active`;
                            document.getElementById('sensorFill').style.width = sensorPercent + '%';
                            document.getElementById('sensorFill').style.backgroundColor = 
                                sensorPercent >= 80 ? '#22c55e' : (sensorPercent >= 50 ? '#eab308' : '#ef4444');
                        }
                    }
                })
                .catch(err => console.error('Error loading system status:', err));
        },

        loadSafetyAlerts: function() {
            // Mock alerts - in production this would fetch from API
            // Alerts are typically generated based on sensor thresholds
        },

        onSearchKeyPress: function(event) {
            if (event.key === 'Enter') {
                this.searchStudent();
            }
        },

        searchStudent: function() {
            const input = document.getElementById('barcodSearchInput').value.trim();
            if (!input) {
                alert('Please enter a student ID or barcode');
                return;
            }

            const url = `<?= APP_URL ?>/smartlab/apiStudent?id=${encodeURIComponent(input)}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        this.currentStudent = data.data;
                        this.displayStudentDetails(data.data);
                        this.loadStudentPractical(data.data.id);
                    } else {
                        alert('Student not found');
                    }
                })
                .catch(err => {
                    console.error('Error searching student:', err);
                    alert('Error searching student');
                });
        },

        displayStudentDetails: function(student) {
            document.getElementById('studentDetailName').textContent = student.full_name;
            document.getElementById('studentDetailReg').textContent = student.reg_number;
            document.getElementById('studentDetailEmail').textContent = student.email;
            document.getElementById('studentDetailLab').textContent = student.lab_id || '--';
            
            const card = document.getElementById('studentDetailsCard');
            card.classList.remove('hidden');
        },

        loadStudentPractical: function(studentId) {
            const url = `<?= APP_URL ?>/smartlab/apiPractical?student_id=${studentId}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        this.currentPractical = data.data;
                        this.displayPracticalReadings(data.data);
                    }
                })
                .catch(err => console.error('Error loading practical:', err));
        },

        displayPracticalReadings: function(practical) {
            const tbody = document.getElementById('readingsTableBody');
            
            const startTime = new Date(practical.start_time).toLocaleString();
            const statusBadge = `<span class="status-badge ${practical.status}">${practical.status}</span>`;
            
            tbody.innerHTML = `
                <tr>
                    <td>${practical.title}</td>
                    <td>${startTime}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="progress-bar small">
                            <div class="progress-fill" style="width: ${practical.completion_percentage}%"></div>
                        </div>
                        ${practical.completion_percentage}%
                    </td>
                </tr>
            `;

            // Display blockchain hash
            document.getElementById('blockchainHash').textContent = practical.blockchain_hash || 'Not yet recorded';
        },

        clearSearch: function() {
            document.getElementById('barcodSearchInput').value = '';
            document.getElementById('studentDetailsCard').classList.add('hidden');
            this.currentStudent = null;
            this.currentPractical = null;
        },

        openCamera: function() {
            // For technician view, open camera for testing/monitoring
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.warn('Camera not supported');
                Utils.showToast('Camera not supported in this browser', 'error');
                return;
            }

            // If camera is already active, show message
            if (this.cameraStatus['test']) {
                Utils.showToast('Test camera is already open', 'info');
                return;
            }

            // Request camera access for testing
            navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user'
                },
                audio: false
            })
            .then(stream => {
                // Create a test camera window/modal
                const testWindow = window.open('', 'cameraTest', 'width=680,height=520,resizable=yes,scrollbars=no');
                if (testWindow) {
                    testWindow.document.write(`
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <title>Camera Test - SmartLab</title>
                            <style>
                                body { margin: 0; padding: 20px; font-family: Arial, sans-serif; background: #f0f0f0; }
                                .container { max-width: 640px; margin: 0 auto; }
                                video { width: 100%; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
                                .label { text-align: center; margin: 10px 0; font-weight: bold; color: #333; }
                                .close-btn { display: block; margin: 10px auto; padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; }
                                .close-btn:hover { background: #c82333; }
                            </style>
                        </head>
                        <body>
                            <div class="container">
                                <div class="label">Camera Test - SmartLab Technician</div>
                                <video id="testCamera" autoplay playsinline muted></video>
                                <button class="close-btn" onclick="window.close()">Close Test Camera</button>
                            </div>
                            <script>
                                const video = document.getElementById('testCamera');
                                video.srcObject = arguments[0];
                            </script>
                        </body>
                        </html>
                    `);
                    testWindow.document.close();
                    
                    // Store stream reference
                    this.testCameraStream = stream;
                    this.cameraStatus['test'] = true;
                    
                    // Listen for window close
                    testWindow.onbeforeunload = () => {
                        if (this.testCameraStream) {
                            this.testCameraStream.getTracks().forEach(track => track.stop());
                            this.testCameraStream = null;
                        }
                        this.cameraStatus['test'] = false;
                    };
                    
                    Utils.showToast('Test camera opened in new window', 'success');
                } else {
                    // Fallback: show in current page
                    Utils.showToast('Popup blocked - camera test not available', 'warning');
                }
            })
            .catch(err => {
                console.error('Camera access error:', err);
                let errorMessage = 'Camera access failed';

                // Check specific error types
                if (err.name === 'NotFoundError') {
                    errorMessage = 'No camera connected to laptop';
                } else if (err.name === 'NotAllowedError') {
                    errorMessage = 'Camera access denied';
                } else if (err.name === 'NotReadableError') {
                    errorMessage = 'Camera is already in use';
                }

                Utils.showToast(errorMessage, 'error');
            });
        },

        toggleAllCameras: function() {
            // For technician view, we'll use a demo camera toggle since they monitor system-wide
            const isActive = this.cameraStatus['demo'] || false;
            const newStatus = !isActive;

            // Update local status
            this.cameraStatus['demo'] = newStatus;

            // Update button UI
            this.updateCameraToggleButton(newStatus);

            // Show notification
            Utils.showToast(
                `System cameras turned ${newStatus ? 'ON' : 'OFF'}`,
                'success'
            );

            // Simulate status update
            setTimeout(() => this.loadSystemStatus(), 500);
        },

        updateCameraToggleButton: function(isActive) {
            const btn = document.getElementById('techCameraToggleBtn');
            if (!btn) return;
            
            const text = btn.querySelector('.toggle-text');
            const icon = btn.querySelector('.toggle-icon');
            
            if (isActive) {
                text.textContent = 'Turn Off Cameras';
                btn.classList.add('active');
                icon.textContent = '📹';
            } else {
                text.textContent = 'Turn On Cameras';
                btn.classList.remove('active');
                icon.textContent = '📷';
            }
        },

        previewReport: function() {
            if (!this.currentStudent) {
                alert('Please search for a student first');
                return;
            }
            // Open preview in new tab
            window.open(
                `<?= APP_URL ?>/smartlab/printableReport?student_id=${this.currentStudent.id}&preview=1`,
                '_blank'
            );
        },

        printReport: function() {
            if (!this.currentStudent) {
                alert('Please search for a student first');
                return;
            }
            window.location.href = `<?= APP_URL ?>/smartlab/printableReport?student_id=${this.currentStudent.id}&print=1`;
        },

        exportPDF: function() {
            if (!this.currentStudent) {
                alert('Please search for a student first');
                return;
            }
            // In production, this would generate and download a PDF
            alert('PDF export functionality coming soon');
        }
    };

    document.addEventListener('DOMContentLoaded', () => technicianView.init());
</script>
