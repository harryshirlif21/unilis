/**
 * Python Meeting Media Bridge
 * Captures camera/screen frames from video elements, sends to Python media server,
 * and renders subscribed streams on canvas elements.
 *
 * PHP: auth + meeting state (unchanged)
 * JS: WebRTC signaling + this bridge for Python-rendered video display
 */

class MeetingMediaBridge {
    constructor(config = {}) {
        this.config = {
            mediaServerUrl: config.mediaServerUrl || 'ws://localhost:8765/ws/media',
            frameRate: config.frameRate || 12,
            jpegQuality: config.jpegQuality || 0.65,
            maxWidth: config.maxWidth || 1280,
            maxHeight: config.maxHeight || 720,
            ...config
        };

        this.meetingId = config.meetingId;
        this.userId = config.userId;
        this.role = config.role || 'student';

        this.ws = null;
        this.isConnected = false;
        this.publishers = new Map(); // key -> { intervalId, videoEl, streamType }
        this.renderTargets = new Map(); // `${userId}:${streamType}` -> canvas
        this._reconnectTimer = null;

        this.onStatusChange = config.onStatusChange || (() => {});
        this.onFrame = config.onFrame || (() => {});
        this.onParticipants = config.onParticipants || (() => {});
    }

    async connect() {
        return new Promise((resolve, reject) => {
            try {
                this.ws = new WebSocket(this.config.mediaServerUrl);

                this.ws.onopen = () => {
                    this.isConnected = true;
                    this.onStatusChange('connected');
                    this._send({
                        type: 'join',
                        meeting_id: this.meetingId,
                        user_id: this.userId,
                        role: this.role
                    });
                    resolve();
                };

                this.ws.onmessage = (event) => {
                    this._handleMessage(JSON.parse(event.data));
                };

                this.ws.onclose = () => {
                    this.isConnected = false;
                    this.onStatusChange('disconnected');
                    this._scheduleReconnect();
                };

                this.ws.onerror = (error) => {
                    console.warn('Meeting media bridge error:', error);
                    if (!this.isConnected) {
                        reject(error);
                    }
                };
            } catch (error) {
                reject(error);
            }
        });
    }

    _scheduleReconnect() {
        if (this._reconnectTimer) return;
        this._reconnectTimer = setTimeout(async () => {
            this._reconnectTimer = null;
            try {
                await this.connect();
                this._restorePublishers();
            } catch (error) {
                console.warn('Media bridge reconnect failed:', error);
                this._scheduleReconnect();
            }
        }, 2000);
    }

    _restorePublishers() {
        for (const [key, pub] of this.publishers.entries()) {
            if (pub.videoEl && document.contains(pub.videoEl)) {
                this.publishStream(pub.videoEl, pub.streamType, key);
            }
        }
        for (const [key, canvas] of this.renderTargets.entries()) {
            const [userId, streamType] = key.split(':');
            this.subscribe(parseInt(userId, 10), streamType);
        }
    }

    _send(payload) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(payload));
        }
    }

    _handleMessage(message) {
        switch (message.type) {
            case 'joined':
                this.onParticipants(message.participants || []);
                break;

            case 'participants':
                this.onParticipants(message.participants || []);
                break;

            case 'frame':
                this._renderFrame(message);
                this.onFrame(message);
                break;

            case 'error':
                console.error('Media server error:', message.message);
                break;
        }
    }

    publishStream(videoElement, streamType = 'camera', key = null) {
        if (!videoElement) return;

        const pubKey = key || streamType;
        this.stopPublishing(pubKey);

        const intervalMs = Math.round(1000 / this.config.frameRate);
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        const intervalId = setInterval(() => {
            if (!this.isConnected || videoElement.readyState < 2) return;

            const vw = videoElement.videoWidth;
            const vh = videoElement.videoHeight;
            if (!vw || !vh) return;

            const scale = Math.min(
                this.config.maxWidth / vw,
                this.config.maxHeight / vh,
                1
            );
            canvas.width = Math.round(vw * scale);
            canvas.height = Math.round(vh * scale);
            ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

            const dataUrl = canvas.toDataURL('image/jpeg', this.config.jpegQuality);
            const data = dataUrl.split(',')[1];

            this._send({
                type: 'frame',
                stream_type: streamType,
                data
            });
        }, intervalMs);

        this.publishers.set(pubKey, {
            intervalId,
            videoEl: videoElement,
            streamType
        });
    }

    stopPublishing(key = 'camera') {
        const pub = this.publishers.get(key);
        if (pub) {
            clearInterval(pub.intervalId);
            this.publishers.delete(key);
            this._send({ type: 'clear_stream', stream_type: pub.streamType });
        }
    }

    stopAllPublishing() {
        for (const key of [...this.publishers.keys()]) {
            this.stopPublishing(key);
        }
    }

    subscribe(targetUserId, streamType = 'composite', canvasElement = null) {
        const key = `${targetUserId}:${streamType}`;

        if (canvasElement) {
            this.renderTargets.set(key, canvasElement);
        }

        this._send({
            type: 'subscribe',
            target_user_id: targetUserId,
            stream_type: streamType
        });
    }

    unsubscribe(targetUserId, streamType = 'composite') {
        const key = `${targetUserId}:${streamType}`;
        this.renderTargets.delete(key);
        this._send({
            type: 'unsubscribe',
            target_user_id: targetUserId,
            stream_type: streamType
        });
    }

    attachRenderCanvas(targetUserId, streamType, canvasElement) {
        const key = `${targetUserId}:${streamType}`;
        this.renderTargets.set(key, canvasElement);
        this.subscribe(targetUserId, streamType);
    }

    _renderFrame(message) {
        const key = `${message.user_id}:${message.stream_type}`;
        const canvas = this.renderTargets.get(key);
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        const img = new Image();
        img.onload = () => {
            canvas.width = img.width;
            canvas.height = img.height;
            ctx.drawImage(img, 0, 0);
        };
        img.src = `data:image/jpeg;base64,${message.data}`;
    }

    /**
     * Replace a <video> element with a <canvas> for Python-rendered display.
     * Returns the canvas element.
     */
    replaceVideoWithCanvas(videoElement, targetUserId, streamType = 'composite') {
        const canvas = document.createElement('canvas');
        canvas.className = videoElement.className;
        canvas.id = videoElement.id + '_py';
        canvas.style.cssText = videoElement.style.cssText;
        canvas.width = videoElement.clientWidth || 640;
        canvas.height = videoElement.clientHeight || 360;

        videoElement.parentNode.insertBefore(canvas, videoElement);
        videoElement.classList.add('hidden');
        videoElement.style.display = 'none';

        this.attachRenderCanvas(targetUserId, streamType, canvas);
        return canvas;
    }

    disconnect() {
        this.stopAllPublishing();
        if (this._reconnectTimer) {
            clearTimeout(this._reconnectTimer);
            this._reconnectTimer = null;
        }
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.isConnected = false;
        this.renderTargets.clear();
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = MeetingMediaBridge;
}
