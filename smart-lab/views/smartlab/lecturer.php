<!-- Lecturer View for Smart Lab Projection -->
<div class="smartlab-section lecturer-section">
    
    <!-- Control Panel -->
    <div class="section-panel control-panel">
        <div class="panel-header">
            <h2>Lab Management</h2>
        </div>
        <div class="panel-content">
            <div class="form-group">
                <label for="experimentSelect">Select Experiment:</label>
                <select id="experimentSelect" onchange="lecturerView.onExperimentChange()">
                    <option value="">-- Choose Experiment --</option>
                </select>
            </div>

            <div class="form-group">
                <label for="groupSelect">Select Group:</label>
                <select id="groupSelect" onchange="lecturerView.onGroupChange()">
                    <option value="">-- Choose Group --</option>
                </select>
            </div>

            <div class="button-group">
                <button class="smartlab-btn" onclick="lecturerView.refreshData()">🔄 Refresh Data</button>
                <button class="smartlab-btn" onclick="lecturerView.toggleAutoRefresh()">⏸ Auto Refresh</button>
            </div>
        </div>
    </div>

    <!-- Video Grid Section -->
    <div class="section-panel video-section full-width">
        <div class="panel-header">
            <div>
                <h2>Lab Cameras</h2>
                <span class="panel-subtitle" id="lecturerCameraCount">0 cameras</span>
            </div>
            <div class="camera-controls">
                <button class="smartlab-btn camera-open-btn" id="lecturerCameraOpenBtn" onclick="lecturerView.openCamera()">
                    <span class="camera-icon">📹</span>
                    <span class="camera-text">Open Camera</span>
                </button>
                <button class="smartlab-btn camera-toggle-btn" id="cameraToggleBtn" onclick="lecturerView.toggleAllCameras()">
                    <span class="toggle-icon">📷</span>
                    <span class="toggle-text">Turn On Cameras</span>
                </button>
            </div>
        </div>
        <div class="panel-content">
            <div id="lecturerVideoGrid" class="video-grid-container">
                <div class="loading">Loading cameras...</div>
            </div>
        </div>
    </div>

    <!-- Metadata Grid -->
    <div class="metadata-grid">
        <!-- Attendance Panel -->
        <div class="section-panel metadata-panel">
            <div class="panel-header">
                <h2>Attendance</h2>
            </div>
            <div class="panel-content">
                <div class="stat-item">
                    <span class="stat-label">Total Students:</span>
                    <span class="stat-value" id="totalStudents">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Present:</span>
                    <span class="stat-value success" id="presentStudents">0</span>
                </div>
                <div class="stat-item">
                    <span class="stat-label">Absent:</span>
                    <span class="stat-value warning" id="absentStudents">0</span>
                </div>
                <div class="divider"></div>
                <div id="attendanceList" class="student-list">
                    <div class="empty-state">No group selected</div>
                </div>
            </div>
        </div>

        <!-- Progress Panel -->
        <div class="section-panel metadata-panel">
            <div class="panel-header">
                <h2>Progress</h2>
            </div>
            <div class="panel-content">
                <div class="progress-item">
                    <div class="progress-label">Overall Completion</div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="overallProgress" style="width: 0%"></div>
                    </div>
                    <div class="progress-text" id="overallProgressText">0%</div>
                </div>
                <div class="divider"></div>
                <div id="progressDetails" class="progress-list">
                    <div class="empty-state">No data</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div class="section-panel alerts-panel full-width">
        <div class="panel-header">
            <h2>Alerts & Warnings</h2>
            <button class="clear-alerts-btn" onclick="lecturerView.clearAlerts()">Clear</button>
        </div>
        <div class="panel-content">
            <div id="alertsList" class="alerts-list">
                <div class="empty-state">No alerts</div>
            </div>
        </div>
    </div>

</div>

<script>
    window.lecturerView = {
        videoGrid: null,
        currentExperiment: null,
        currentGroup: null,
        autoRefreshTimer: null,
        isAutoRefreshing: true,
        cameraStatus: {}, // Track camera on/off status

        init: function() {
            this.videoGrid = new VideoGrid('lecturerVideoGrid', {
                autoRotate: false,
                rotateInterval: 15000
            });

            const section = document.querySelector('.lecturer-section');
            if (section) {
                section.videoGrid = this.videoGrid;
            }

            this.loadExperiments();
            this.loadCameras();
            this.startAutoRefresh();
        },

        loadExperiments: function() {
            fetch(`<?= APP_URL ?>/smartlab/apiExperiments`)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const select = document.getElementById('experimentSelect');
                        select.innerHTML = '<option value="">-- Choose Experiment --</option>';
                        data.data.forEach(exp => {
                            const option = document.createElement('option');
                            option.value = exp.id;
                            option.textContent = exp.title;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error('Error loading experiments:', err));
        },

        onExperimentChange: function() {
            const select = document.getElementById('experimentSelect');
            this.currentExperiment = select.value;
            if (this.currentExperiment) {
                this.loadGroupsByExperiment();
            } else {
                document.getElementById('groupSelect').innerHTML = '<option value="">-- Choose Group --</option>';
            }
        },

        loadGroupsByExperiment: function() {
            const url = `<?= APP_URL ?>/smartlab/apiGroupsByExperiment?experiment_id=${this.currentExperiment}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        const select = document.getElementById('groupSelect');
                        select.innerHTML = '<option value="">-- Choose Group --</option>';
                        data.data.forEach(group => {
                            const option = document.createElement('option');
                            option.value = group.group_id;
                            option.textContent = `${group.group_name} (${group.member_count} students)`;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(err => console.error('Error loading groups:', err));
        },

        onGroupChange: function() {
            const select = document.getElementById('groupSelect');
            this.currentGroup = select.value;
            if (this.currentGroup) {
                this.loadGroupMembers();
                this.loadGroupProgress();
            }
        },

        loadGroupMembers: function() {
            const url = `<?= APP_URL ?>/smartlab/apiStudentsByGroup?group_id=${this.currentGroup}`;
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.data) {
                        this.updateAttendanceList(data.data);
                    }
                })
                .catch(err => console.error('Error loading group members:', err));
        },

        updateAttendanceList: function(students) {
            let present = 0;
            let absent = 0;
            
            const html = students.map(student => {
                const isPresent = Math.random() > 0.3; // Mock: random presence
                if (isPresent) present++;
                else absent++;
                
                return `
                    <div class="student-item ${isPresent ? 'present' : 'absent'}">
                        <span class="student-name">${student.full_name}</span>
                        <span class="student-status">${isPresent ? '✓ Present' : '✗ Absent'}</span>
                    </div>
                `;
            }).join('');

            document.getElementById('attendanceList').innerHTML = html;
            document.getElementById('totalStudents').textContent = students.length;
            document.getElementById('presentStudents').textContent = present;
            document.getElementById('absentStudents').textContent = absent;
        },

        loadGroupProgress: function() {
            // Mock progress data
            const progress = Math.floor(Math.random() * 100) + 20;
            document.getElementById('overallProgress').style.width = progress + '%';
            document.getElementById('overallProgressText').textContent = progress + '%';

            const details = `
                <div class="progress-detail">
                    <span>Step 1: Setup</span>
                    <span class="detail-status">✓ Complete</span>
                </div>
                <div class="progress-detail">
                    <span>Step 2: Measurement</span>
                    <span class="detail-status">⟳ In Progress</span>
                </div>
                <div class="progress-detail">
                    <span>Step 3: Analysis</span>
                    <span class="detail-status">⏳ Pending</span>
                </div>
            `;
            document.getElementById('progressDetails').innerHTML = details;
        },

        loadCameras: function() {
            // Use laptop camera instead of database cameras
            this.initializeLaptopCamera();
        },

        initializeLaptopCamera: function() {
            // Check if browser supports camera
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.warn('Camera not supported');
                document.getElementById('lecturerCameraCount').textContent = 'Camera not supported';
                return;
            }

            // Create a single camera view for laptop
            const videoContainer = document.getElementById('lecturerVideoGrid');
            videoContainer.innerHTML = `
                <div class="camera-container">
                    <video id="lecturerLaptopCamera" autoplay playsinline muted></video>
                    <div class="camera-label">Laptop Camera</div>
                </div>
            `;

            const video = document.getElementById('lecturerLaptopCamera');

            // Request camera access
            navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user' // Use front camera
                },
                audio: false
            })
            .then(stream => {
                video.srcObject = stream;
                this.cameraStream = stream;
                this.cameraStatus['laptop'] = true;
                document.getElementById('lecturerCameraCount').textContent = '1 camera active';
                this.updateCameraToggleButton(true);
                Utils.showToast('Camera activated', 'success');
            })
            .catch(err => {
                console.error('Camera access error:', err);
                let errorMessage = 'Camera access failed';
                let errorDetails = 'Please check your camera settings';

                // Check specific error types
                if (err.name === 'NotFoundError') {
                    errorMessage = 'No camera connected to laptop';
                    errorDetails = 'Please connect a camera to your laptop and try again';
                } else if (err.name === 'NotAllowedError') {
                    errorMessage = 'Camera access denied';
                    errorDetails = 'Please allow camera access in your browser';
                } else if (err.name === 'NotReadableError') {
                    errorMessage = 'Camera is already in use';
                    errorDetails = 'Please close other applications using the camera';
                }

                videoContainer.innerHTML = `
                    <div class="camera-error">
                        <div class="error-icon">📷</div>
                        <div class="error-message">${errorMessage}</div>
                        <div class="error-details">${errorDetails}</div>
                    </div>
                `;
                document.getElementById('lecturerCameraCount').textContent = 'Camera unavailable';
                this.cameraStatus['laptop'] = false;
                this.updateCameraToggleButton(false);
                Utils.showToast(errorMessage, 'error');
            });
        },

        stopLaptopCamera: function() {
            if (this.cameraStream) {
                // Stop all tracks
                this.cameraStream.getTracks().forEach(track => track.stop());
                this.cameraStream = null;
            }

            // Update UI
            const videoContainer = document.getElementById('lecturerVideoGrid');
            videoContainer.innerHTML = `
                <div class="camera-placeholder">
                    <div class="placeholder-icon">📷</div>
                    <div class="placeholder-text">Camera Off</div>
                </div>
            `;

            this.cameraStatus['laptop'] = false;
            document.getElementById('lecturerCameraCount').textContent = 'Camera off';
            this.updateCameraToggleButton(false);
            Utils.showToast('Camera turned off', 'info');
        },

        openCamera: function() {
            // Check if browser supports camera
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                console.warn('Camera not supported');
                Utils.showToast('Camera not supported in this browser', 'error');
                return;
            }

            // If camera is already active, show message
            if (this.cameraStatus['laptop']) {
                Utils.showToast('Camera is already open', 'info');
                return;
            }

            // Request camera access
            navigator.mediaDevices.getUserMedia({
                video: {
                    width: { ideal: 640 },
                    height: { ideal: 480 },
                    facingMode: 'user' // Use front camera
                },
                audio: false
            })
            .then(stream => {
                // Create video element
                const videoContainer = document.getElementById('lecturerVideoGrid');
                videoContainer.innerHTML = `
                    <div class="camera-container">
                        <video id="lecturerLaptopCamera" autoplay playsinline muted></video>
                        <div class="camera-label">Laptop Camera</div>
                    </div>
                `;

                const video = document.getElementById('lecturerLaptopCamera');
                video.srcObject = stream;
                this.cameraStream = stream;
                this.cameraStatus['laptop'] = true;
                document.getElementById('lecturerCameraCount').textContent = '1 camera active';
                this.updateCameraToggleButton(true);
                Utils.showToast('Camera opened successfully', 'success');
            })
            .catch(err => {
                console.error('Camera access error:', err);
                let errorMessage = 'Camera access failed';
                let errorDetails = 'Please check your camera settings';

                // Check specific error types
                if (err.name === 'NotFoundError') {
                    errorMessage = 'No camera connected to laptop';
                    errorDetails = 'Please connect a camera to your laptop and try again';
                } else if (err.name === 'NotAllowedError') {
                    errorMessage = 'Camera access denied';
                    errorDetails = 'Please allow camera access in your browser';
                } else if (err.name === 'NotReadableError') {
                    errorMessage = 'Camera is already in use';
                    errorDetails = 'Please close other applications using the camera';
                }

                const videoContainer = document.getElementById('lecturerVideoGrid');
                videoContainer.innerHTML = `
                    <div class="camera-error">
                        <div class="error-icon">📷</div>
                        <div class="error-message">${errorMessage}</div>
                        <div class="error-details">${errorDetails}</div>
                    </div>
                `;
                document.getElementById('lecturerCameraCount').textContent = 'Camera unavailable';
                this.cameraStatus['laptop'] = false;
                this.updateCameraToggleButton(false);
                Utils.showToast(errorMessage, 'error');
            });
        },

        toggleAllCameras: function() {
            const isActive = this.cameraStatus['laptop'] || false;

            if (isActive) {
                // Turn off camera
                this.stopLaptopCamera();
            } else {
                // Turn on camera
                this.initializeLaptopCamera();
            }
        },

        updateCameraToggleButton: function(isActive) {
            const btn = document.getElementById('cameraToggleBtn');
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

        startAutoRefresh: function() {
            this.autoRefreshTimer = setInterval(() => {
                this.refreshData();
            }, 60000); // Every 60 seconds
        },

        stopAutoRefresh: function() {
            if (this.autoRefreshTimer) {
                clearInterval(this.autoRefreshTimer);
                this.autoRefreshTimer = null;
            }
        },

        toggleAutoRefresh: function() {
            this.isAutoRefreshing = !this.isAutoRefreshing;
            if (this.isAutoRefreshing) {
                this.startAutoRefresh();
                alert('Auto-refresh enabled');
            } else {
                this.stopAutoRefresh();
                alert('Auto-refresh disabled');
            }
        },

        refreshData: function() {
            if (this.currentGroup) {
                this.loadGroupMembers();
                this.loadGroupProgress();
            }
            this.loadCameras();
        },

        clearAlerts: function() {
            document.getElementById('alertsList').innerHTML = '<div class="empty-state">No alerts</div>';
        }
    };

    document.addEventListener('DOMContentLoaded', () => lecturerView.init());
</script>
