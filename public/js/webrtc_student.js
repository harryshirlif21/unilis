/**
 * WebRTC Student implementation
 * Connects to lecturer and handles media streams
 * Supports both PHP polling and WebSocket signaling
 */

class WebRTCStudent {
    constructor(config = {}) {
        this.config = {
            stunServer: 'stun:stun.l.google.com:19302',
            pollingInterval: 700,
            maxRetries: 5,
            ...config
        };

        this.peerConnection = null;
        this.lecturerConnection = null;
        this.localStream = null;
        this.remoteStreams = new Map(); // lecturerId -> MediaStream
        this.api = new MeetingAPI(config.baseUrl);
        this.pollingManager = new PollingManager(this.api, this.config.pollingInterval);
        this.wsSignaling = null;
        this.signalingMode = 'polling';
        
        this.meetingId = config.meetingId;
        this.userId = config.userId;
        this.lecturerId = config.lecturerId;
        
        this.onRemoteStream = config.onRemoteStream || (() => {});
        this.onRemoteStreamEnded = config.onRemoteStreamEnded || (() => {});
        this.onConnectionStateChange = config.onConnectionStateChange || (() => {});
        this.onSignalingStateChange = config.onSignalingStateChange || (() => {});
        
        this.isRequestingPublish = false;
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
            await this.api.joinMeeting(this.meetingId, this.userId, 'student');
        } catch (error) {
            console.error('Failed to join meeting:', error);
        }
    }

    async requestToPublish() {
        if (this.isRequestingPublish) {
            console.warn('Already requesting to publish');
            return;
        }

        this.isRequestingPublish = true;
        
        try {
            // Get local media stream
            this.localStream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });

            // Create peer connection for lecturer
            this.lecturerConnection = this.createPeerConnection(this.lecturerId, 'lecturer');
            
            // Add local tracks
            this.localStream.getTracks().forEach(track => {
                this.lecturerConnection.addTrack(track, this.localStream);
            });

            // The lecturer will send us an offer, we'll respond with answer

        } catch (error) {
            console.error('Failed to request publishing:', error);
            this.isRequestingPublish = false;
            throw error;
        }
    }

    stopPublishing() {
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }

        if (this.lecturerConnection) {
            this.lecturerConnection.close();
            this.lecturerConnection = null;
        }

        this.isRequestingPublish = false;
    }

    createPeerConnection(remoteUserId, role = 'lecturer') {
        const pc = new RTCPeerConnection({
            iceServers: [{ urls: this.config.stunServer }]
        });

        // Handle incoming tracks (for receiving lecturer's screen share)
        pc.ontrack = (event) => {
            const stream = event.streams[0];
            this.remoteStreams.set(remoteUserId, stream);
            this.onRemoteStream(remoteUserId, stream, role);
        };

        // Handle ICE candidates
        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal('candidate', remoteUserId, event.candidate);
            }
        };

        // Monitor connection state
        pc.oniceconnectionstatechange = () => {
            const state = pc.iceConnectionState;
            this.onConnectionStateChange(remoteUserId, state, role);
            
            if (state === 'failed' || state === 'disconnected') {
                console.warn(`ICE connection ${state} for ${role}:`, remoteUserId);
                
                // Attempt to restart ICE for lecturer connection
                if (role === 'lecturer') {
                    setTimeout(() => {
                        if (pc.iceConnectionState === 'failed') {
                            this.restartIce(remoteUserId, role);
                        }
                    }, 2000);
                }
            } else if (state === 'closed') {
                this.remoteStreams.delete(remoteUserId);
                this.onRemoteStreamEnded(remoteUserId, role);
            }
        };

        pc.onsignalingstatechange = () => {
            this.onSignalingStateChange(remoteUserId, pc.signalingState, role);
        };

        return pc;
    }

    async handleSignalingMessage(message) {
        const { signal_type, from_user_id, signal_data } = message;
        
        if (from_user_id === this.userId) return; // Ignore own messages

        try {
            switch (signal_type) {
                case 'offer':
                    await this.handleOffer(from_user_id, JSON.parse(signal_data));
                    break;
                    
                case 'answer':
                    await this.handleAnswer(from_user_id, JSON.parse(signal_data));
                    break;
                    
                case 'candidate':
                    await this.handleCandidate(from_user_id, JSON.parse(signal_data));
                    break;
            }
        } catch (error) {
            console.error('Error handling signaling message:', error);
        }
    }

    async handleOffer(fromUserId, offer) {
        // Only accept offers from lecturer
        if (fromUserId !== this.lecturerId) {
            console.warn('Received offer from non-lecturer:', fromUserId);
            return;
        }

        if (!this.lecturerConnection) {
            console.warn('No lecturer connection available');
            return;
        }

        try {
            await this.lecturerConnection.setRemoteDescription(offer);
            
            // Create and send answer
            const answer = await this.lecturerConnection.createAnswer();
            await this.lecturerConnection.setLocalDescription(answer);
            
            await this.sendSignal('answer', fromUserId, answer);
        } catch (error) {
            console.error('Failed to handle offer:', error);
        }
    }

    async handleAnswer(fromUserId, answer) {
        // Students typically don't receive answers, but handle gracefully
        console.warn('Unexpected answer from:', fromUserId);
    }

    async handleCandidate(fromUserId, candidate) {
        const pc = fromUserId === this.lecturerId ? this.lecturerConnection : null;
        
        if (pc) {
            try {
                await pc.addIceCandidate(candidate);
            } catch (error) {
                console.error('Failed to add ICE candidate:', error);
            }
        }
    }

    async restartIce(remoteUserId, role) {
        const pc = role === 'lecturer' ? this.lecturerConnection : null;
        if (!pc) return;

        try {
            const offer = await pc.createOffer({ iceRestart: true });
            await pc.setLocalDescription(offer);
            await this.sendSignal('offer', remoteUserId, offer);
        } catch (error) {
            console.error('Failed to restart ICE:', error);
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

    disconnect() {
        // Stop polling
        this.pollingManager.stop();
        
        // Close WebSocket
        if (this.wsSignaling) {
            this.wsSignaling.disconnect();
        }
        
        // Close peer connections
        if (this.lecturerConnection) {
            this.lecturerConnection.close();
            this.lecturerConnection = null;
        }
        
        // Stop local stream
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
            this.localStream = null;
        }
        
        this.remoteStreams.clear();
        
        // Leave meeting
        this.api.leaveMeeting(this.meetingId, this.userId).catch(console.error);
    }

    getConnectionState(remoteUserId) {
        const pc = remoteUserId === this.lecturerId ? this.lecturerConnection : null;
        return pc ? pc.iceConnectionState : 'disconnected';
    }

    // Utility method to toggle camera
    async toggleCamera(enabled) {
        if (this.localStream) {
            const videoTrack = this.localStream.getVideoTracks()[0];
            if (videoTrack) {
                videoTrack.enabled = enabled;
            }
        }
    }

    // Utility method to toggle microphone
    async toggleMicrophone(enabled) {
        if (this.localStream) {
            const audioTrack = this.localStream.getAudioTracks()[0];
            if (audioTrack) {
                audioTrack.enabled = enabled;
            }
        }
    }
}

// Export for use in meeting_join.php
if (typeof module !== 'undefined' && module.exports) {
    module.exports = WebRTCStudent;
}