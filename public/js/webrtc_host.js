/**
 * WebRTC Host (Lecturer) implementation
 * Manages multiple peer connections for students
 * Supports both PHP polling and WebSocket signaling
 */

class WebRTCHost {
    constructor(config = {}) {
        this.config = {
            stunServer: 'stun:stun.l.google.com:19302',
            pollingInterval: 700,
            maxRetries: 5,
            ...config
        };

        this.peerConnections = new Map(); // studentId -> RTCPeerConnection
        this.remoteStreams = new Map(); // studentId -> MediaStream
        this.localStream = null;
        this.screenStream = null;
        this.isScreenSharing = false;
        this.api = new MeetingAPI(config.baseUrl);
        this.pollingManager = new PollingManager(this.api, this.config.pollingInterval);
        this.wsSignaling = null;
        this.signalingMode = 'polling'; // 'polling' or 'websocket'
        
        this.meetingId = config.meetingId;
        this.userId = config.userId;
        
        this.onRemoteStream = config.onRemoteStream || (() => {});
        this.onRemoteStreamEnded = config.onRemoteStreamEnded || (() => {});
        this.onSignalingStateChange = config.onSignalingStateChange || (() => {});
        this.onIceConnectionStateChange = config.onIceConnectionStateChange || (() => {});
    }

    async initialize() {
        try {
            // Initialize WebSocket signaling if available
            if (typeof WSSignaling !== 'undefined') {
                this.wsSignaling = new WSSignaling({
                    meetingId: this.meetingId,
                    userId: this.userId,
                    onMessage: this.handleSignalingMessage.bind(this)
                });
                
                await this.wsSignaling.connect();
                this.signalingMode = 'websocket';
                console.log('Using WebSocket signaling');
            } else {
                console.log('Using PHP polling signaling');
            }
        } catch (error) {
            console.warn('WebSocket signaling unavailable, falling back to polling:', error);
            this.signalingMode = 'polling';
        }

        // Start polling if WebSocket is not available or failed
        if (this.signalingMode === 'polling') {
            this.startPolling();
        }

        await this.joinMeeting();
    }

    async joinMeeting() {
        try {
            await this.api.joinMeeting(this.meetingId, this.userId, 'lecturer');
        } catch (error) {
            console.error('Failed to join meeting:', error);
        }
    }

    async startLocalStream(constraints = { video: true, audio: true }) {
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia(constraints);
            return this.localStream;
        } catch (error) {
            console.error('Failed to get local media stream:', error);
            throw error;
        }
    }

    async startScreenShare() {
        try {
            this.screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: true,
                audio: true
            });

            this.isScreenSharing = true;

            // Replace video tracks in all peer connections
            for (const [studentId, pc] of this.peerConnections) {
                const videoTrack = this.screenStream.getVideoTracks()[0];
                const sender = pc.getSenders().find(s => 
                    s.track && s.track.kind === 'video'
                );
                
                if (sender) {
                    await sender.replaceTrack(videoTrack);
                }
            }

            // Handle screen share ending
            this.screenStream.getVideoTracks()[0].onended = () => {
                this.stopScreenShare();
            };

            return this.screenStream;
        } catch (error) {
            console.error('Failed to start screen share:', error);
            throw error;
        }
    }

    async stopScreenShare() {
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(track => track.stop());
            this.screenStream = null;
        }

        this.isScreenSharing = false;

        // Restore camera tracks if available
        if (this.localStream) {
            for (const [studentId, pc] of this.peerConnections) {
                const videoTrack = this.localStream.getVideoTracks()[0];
                const sender = pc.getSenders().find(s => 
                    s.track && s.track.kind === 'video'
                );
                
                if (sender && videoTrack) {
                    await sender.replaceTrack(videoTrack);
                }
            }
        }
    }

    async addStudent(studentId) {
        if (this.peerConnections.has(studentId)) {
            console.warn('Peer connection already exists for student:', studentId);
            return;
        }

        const pc = this.createPeerConnection(studentId);
        this.peerConnections.set(studentId, pc);

        // Add local tracks if available
        const streamToUse = this.isScreenSharing ? this.screenStream : this.localStream;
        if (streamToUse) {
            streamToUse.getTracks().forEach(track => {
                pc.addTrack(track, streamToUse);
            });
        }

        // Create and send offer
        try {
            const offer = await pc.createOffer();
            await pc.setLocalDescription(offer);
            
            await this.sendSignal('offer', studentId, offer);
        } catch (error) {
            console.error('Failed to create offer for student:', studentId, error);
            this.removeStudent(studentId);
        }
    }

    removeStudent(studentId) {
        const pc = this.peerConnections.get(studentId);
        if (pc) {
            pc.close();
            this.peerConnections.delete(studentId);
        }
        
        if (this.remoteStreams.has(studentId)) {
            this.remoteStreams.delete(studentId);
            this.onRemoteStreamEnded(studentId);
        }
    }

    createPeerConnection(studentId) {
        const pc = new RTCPeerConnection({
            iceServers: [{ urls: this.config.stunServer }]
        });

        // Handle incoming tracks
        pc.ontrack = (event) => {
            const stream = event.streams[0];
            this.remoteStreams.set(studentId, stream);
            this.onRemoteStream(studentId, stream);
        };

        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', studentId, event.candidate);
            }
        };

        // Monitor connection state
        pc.oniceconnectionstatechange = () => {
            const state = pc.iceConnectionState;
            this.onIceConnectionStateChange(studentId, state);
            
            if (state === 'failed' || state === 'disconnected') {
                console.warn(`ICE connection ${state} for student:`, studentId);
                // Attempt to restart ICE
                setTimeout(() => {
                    if (pc.iceConnectionState === 'failed') {
                        this.restartIce(studentId);
                    }
                }, 2000);
            } else if (state === 'closed') {
                this.removeStudent(studentId);
            }
        };

        pc.onsignalingstatechange = () => {
            this.onSignalingStateChange(studentId, pc.signalingState);
        };

        return pc;
    }

    async restartIce(studentId) {
        const pc = this.peerConnections.get(studentId);
        if (!pc) return;

        try {
            const offer = await pc.createOffer({ iceRestart: true });
            await pc.setLocalDescription(offer);
            await this.sendSignal('offer', studentId, offer);
        } catch (error) {
            console.error('Failed to restart ICE for student:', studentId, error);
        }
    }

    async handleSignalingMessage(message) {
        const { signal_type, from_user_id, signal_data } = message;
        
        if (from_user_id === this.userId) return; // Ignore own messages

        const pc = this.peerConnections.get(from_user_id);
        if (!pc) {
            console.warn('No peer connection for student:', from_user_id);
            return;
        }

        try {
            switch (signal_type) {
                case 'answer':
                    await pc.setRemoteDescription(JSON.parse(signal_data));
                    break;
                    
                case 'candidate':
                    await pc.addIceCandidate(JSON.parse(signal_data));
                    break;
                    
                case 'offer':
                    // Students shouldn't send offers to host, but handle gracefully
                    console.warn('Unexpected offer from student:', from_user_id);
                    break;
            }
        } catch (error) {
            console.error('Error handling signaling message:', error);
        }
    }

    async sendSignal(type, toUserId, data) {
        const signalData = JSON.stringify(data);
        
        try {
            if (this.signalingMode === 'websocket' && this.wsSignaling) {
                await this.wsSignaling.sendMessage(type, toUserId, signalData);
            } else {
                // Fallback to PHP API
                switch (type) {
                    case 'offer':
                        await this.api.sendOffer(this.meetingId, this.userId, signalData, toUserId);
                        break;
                    case 'answer':
                        await this.api.sendAnswer(this.meetingId, this.userId, signalData, toUserId);
                        break;
                    case 'candidate':
                        await this.api.sendCandidate(this.meetingId, this.userId, signalData, toUserId);
                        break;
                }
            }
        } catch (error) {
            console.error('Failed to send signal:', error);
        }
    }

    startPolling() {
        this.pollingManager.start(
            this.meetingId,
            this.userId,
            this.handlePolledSignals.bind(this),
            (error) => {
                console.error('Polling error:', error);
            }
        );
    }

    handlePolledSignals(signals) {
        signals.forEach(signal => {
            this.handleSignalingMessage(signal);
        });
    }

    async startRecording() {
        if (!this.localStream && !this.screenStream) {
            throw new Error('No media stream available for recording');
        }

        const streams = [];
        if (this.localStream) streams.push(this.localStream);
        if (this.screenStream) streams.push(this.screenStream);
        
        // Add remote streams
        for (const stream of this.remoteStreams.values()) {
            streams.push(stream);
        }

        // For simplicity, we'll record the first available stream
        const streamToRecord = streams[0];
        this.mediaRecorder = new MediaRecorder(streamToRecord, {
            mimeType: 'video/webm;codecs=vp9,opus'
        });

        this.recordedChunks = [];
        
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.recordedChunks.push(event.data);
            }
        };

        this.mediaRecorder.onstop = async () => {
            const blob = new Blob(this.recordedChunks, { type: 'video/webm' });
            await this.uploadRecording(blob);
        };

        this.mediaRecorder.start(1000); // Collect data every second
    }

    stopRecording() {
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
    }

    async uploadRecording(blob) {
        try {
            const base64Data = await this.blobToBase64(blob);
            await this.api.uploadRecording(this.meetingId, this.userId, base64Data);
        } catch (error) {
            console.error('Failed to upload recording:', error);
        }
    }

    blobToBase64(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const base64 = reader.result.split(',')[1];
                resolve(base64);
            };
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    }

    disconnect() {
        // Stop polling
        this.pollingManager.stop();
        
        // Close WebSocket
        if (this.wsSignaling) {
            this.wsSignaling.disconnect();
        }
        
        // Close all peer connections
        for (const [studentId, pc] of this.peerConnections) {
            pc.close();
        }
        this.peerConnections.clear();
        this.remoteStreams.clear();
        
        // Stop local streams
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(track => track.stop());
        }
        
        // Leave meeting
        this.api.leaveMeeting(this.meetingId, this.userId).catch(console.error);
    }

    getConnectionState(studentId) {
        const pc = this.peerConnections.get(studentId);
        return pc ? pc.iceConnectionState : 'disconnected';
    }

    getStats(studentId) {
        const pc = this.peerConnections.get(studentId);
        if (!pc) return null;
        
        return pc.getStats();
    }
}

// Export for use in meeting_host.php
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebRTCHost;
}