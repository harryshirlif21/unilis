<!-- Student View for Smart Lab Projection -->
<div class="smartlab-section student-section">
    <!-- Lab Info Panel -->
    <div class="section-panel lab-info-panel">
        <div class="panel-header">
            <h2>Current Experiment</h2>
        </div>
        <div class="panel-content">
            <div class="info-row">
                <span class="info-label">Lab:</span>
                <span class="info-value" id="studentLabName">Loading...</span>
            </div>
            <div class="info-row">
                <span class="info-label">Experiment:</span>
                <span class="info-value" id="studentExperimentName">Loading...</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value" id="studentStatus">
                    <span class="status-badge ready">Ready</span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Group Size:</span>
                <span class="info-value" id="studentGroupSize">0 students</span>
            </div>
        </div>
    </div>

    <!-- Video Grid Section -->
    <div class="section-panel video-section full-width">
        <div class="panel-header">
            <div>
                <h2>Live Lab Cameras</h2>
                <span class="panel-subtitle" id="cameraCount">0 cameras</span>
            </div>
            <div class="camera-controls">
                <button class="smartlab-btn camera-open-btn" id="studentCameraOpenBtn" onclick="studentView.openCamera()">
                    <span class="camera-icon">📹</span>
                    <span class="camera-text">Open Camera</span>
                </button>
                <button class="smartlab-btn camera-toggle-btn" id="studentCameraToggleBtn" onclick="studentView.toggleAllCameras()">
                    <span class="toggle-icon">📷</span>
                    <span class="toggle-text">Turn On Cameras</span>
                </button>
            </div>
        </div>
        <div class="panel-content">
            <div id="studentVideoGrid" class="video-grid-container">
                <div class="loading">Loading cameras...</div>
            </div>
        </div>
    </div>

    <!-- Sensor Data Panel -->
    <div class="section-panel sensor-panel">
        <div class="panel-header">
            <h2>Lab Conditions</h2>
        </div>
        <div class="panel-content sensor-grid">
            <div class="sensor-item">
                <div class="sensor-icon">🌡️</div>
                <div class="sensor-info">
                    <div class="sensor-label">Temperature</div>
                    <div class="sensor-value" id="tempValue">-- °C</div>
                </div>
            </div>
            <div class="sensor-item">
                <div class="sensor-icon">💨</div>
                <div class="sensor-info">
                    <div class="sensor-label">Gas Level</div>
                    <div class="sensor-value" id="gasValue">-- ppm</div>
                </div>
            </div>
            <div class="sensor-item">
                <div class="sensor-icon">👥</div>
                <div class="sensor-info">
                    <div class="sensor-label">Occupancy</div>
                    <div class="sensor-value" id="occupancyValue">0 people</div>
                </div>
            </div>
            <div class="sensor-item">
                <div class="sensor-icon">💧</div>
                <div class="sensor-info">
                    <div class="sensor-label">Humidity</div>
                    <div class="sensor-value" id="humidityValue">-- %</div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    (function() {
        window.studentView = {
            videoGrid: null,
            cameraStatus: {},

            init: function() {
                // Initialize video grid
                this.videoGrid = new VideoGrid('studentVideoGrid', {
                    autoRotate: true,
                    rotateInterval: 15000
                });
                
                // Store reference for navigation
                const section = document.querySelector('.student-section');
                if (section) {
                    section.videoGrid = this.videoGrid;
                }

                // Load initial data
                this.loadStudentData();
                this.loadCameras();
                this.loadSensors();

                // Refresh data every 30 seconds
                setInterval(() => this.loadSensors(), 30000);
                setInterval(() => this.loadStudentData(), 60000);
            },

            loadStudentData: function() {
                // Get student's current practical info
                const studentInfo = {
                    labName: 'Biology Lab A',
                    experiment: 'Cell Structure Analysis',
                    groupSize: '4 students',
                    status: 'in-progress'
                };

                document.getElementById('studentLabName').textContent = studentInfo.labName;
                document.getElementById('studentExperimentName').textContent = studentInfo.experiment;
                document.getElementById('studentGroupSize').textContent = studentInfo.groupSize;
            },

            loadCameras: function() {
                // Use laptop camera instead of database cameras
                this.initializeLaptopCamera();
            },

            initializeLaptopCamera: function() {
                // Check if browser supports camera
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    console.warn('Camera not supported');
                    document.getElementById('cameraCount').textContent = 'Camera not supported';
                    return;
                }

                // Create a single camera view for laptop
                const videoContainer = document.getElementById('studentVideoGrid');
                videoContainer.innerHTML = `
                    <div class="camera-container">
                        <video id="laptopCamera" autoplay playsinline muted></video>
                        <div class="camera-label">Laptop Camera</div>
                    </div>
                `;

                const video = document.getElementById('laptopCamera');

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
                    document.getElementById('cameraCount').textContent = '1 camera active';
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
                    document.getElementById('cameraCount').textContent = 'Camera unavailable';
                    this.cameraStatus['laptop'] = false;
                    this.updateCameraToggleButton(false);
                    Utils.showToast(errorMessage, 'error');
                });
            },

            loadSensors: function() {
                fetch(`<?= APP_URL ?>/smartlab/apiSensors`)
                    .then(r => {
                        if (!r.ok) throw new Error(`HTTP ${r.status}`);
                        return r.json();
                    })
                    .then(data => {
                        if (data && data.success && data.data) {
                            this.updateSensorDisplay(data.data);
                        } else {
                            console.warn('No sensors data:', data);
                        }
                    })
                    .catch(err => {
                        console.error('Error loading sensors:', err);
                    });
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
                    const videoContainer = document.getElementById('studentVideoGrid');
                    videoContainer.innerHTML = `
                        <div class="camera-container">
                            <video id="studentLaptopCamera" autoplay playsinline muted></video>
                            <div class="camera-label">Laptop Camera</div>
                        </div>
                    `;

                    const video = document.getElementById('studentLaptopCamera');
                    video.srcObject = stream;
                    this.cameraStream = stream;
                    this.cameraStatus['laptop'] = true;
                    document.getElementById('cameraCount').textContent = '1 camera active';
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

                    const videoContainer = document.getElementById('studentVideoGrid');
                    videoContainer.innerHTML = `
                        <div class="camera-error">
                            <div class="error-icon">📷</div>
                            <div class="error-message">${errorMessage}</div>
                            <div class="error-details">${errorDetails}</div>
                        </div>
                    `;
                    document.getElementById('cameraCount').textContent = 'Camera unavailable';
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

            stopLaptopCamera: function() {
                if (this.cameraStream) {
                    // Stop all tracks
                    this.cameraStream.getTracks().forEach(track => track.stop());
                    this.cameraStream = null;
                }

                // Update UI
                const videoContainer = document.getElementById('studentVideoGrid');
                videoContainer.innerHTML = `
                    <div class="camera-placeholder">
                        <div class="placeholder-icon">📷</div>
                        <div class="placeholder-text">Camera Off</div>
                    </div>
                `;

                this.cameraStatus['laptop'] = false;
                document.getElementById('cameraCount').textContent = 'Camera off';
                this.updateCameraToggleButton(false);
                Utils.showToast('Camera turned off', 'info');
            },

            updateCameraToggleButton: function(isActive) {
                const btn = document.getElementById('studentCameraToggleBtn');
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

            updateSensorDisplay: function(sensors) {
                const sensorMap = {};
                
                // Group sensors by type
                sensors.forEach(sensor => {
                    if (!sensorMap[sensor.sensor_type]) {
                        sensorMap[sensor.sensor_type] = [];
                    }
                    sensorMap[sensor.sensor_type].push(sensor);
                });

                // Update temperature (average)
                if (sensorMap['temperature']) {
                    const temps = sensorMap['temperature'].map(s => parseFloat(s.sensor_value));
                    const avgTemp = (temps.reduce((a, b) => a + b, 0) / temps.length).toFixed(1);
                    document.getElementById('tempValue').textContent = avgTemp + ' °C';
                }

                // Update gas level (max or average)
                if (sensorMap['gas']) {
                    const gas = parseFloat(sensorMap['gas'][0].sensor_value).toFixed(1);
                    document.getElementById('gasValue').textContent = gas + ' ppm';
                }

                // Update occupancy
                if (sensorMap['occupancy']) {
                    const occupancy = parseInt(sensorMap['occupancy'][0].sensor_value) || 0;
                    document.getElementById('occupancyValue').textContent = occupancy + ' people';
                }

                // Update humidity
                if (sensorMap['humidity']) {
                    const humidity = parseFloat(sensorMap['humidity'][0].sensor_value).toFixed(1);
                    document.getElementById('humidityValue').textContent = humidity + ' %';
                }
            }
        };

        document.addEventListener('DOMContentLoaded', () => studentView.init());
    })();
</script>
