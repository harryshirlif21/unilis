/**
 * API utility functions for WebRTC meeting system
 * Includes fetch wrappers, retry logic, and polling helpers
 */

class MeetingAPI {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
        this.retryDelay = 1000;
        this.maxRetries = 3;
    }

    async fetchWithRetry(url, options = {}, retries = this.maxRetries) {
        try {
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    ...options.headers
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            return await response.json();
        } catch (error) {
            if (retries > 0) {
                console.warn(`API call failed, retrying in ${this.retryDelay}ms:`, error);
                await this.delay(this.retryDelay);
                return this.fetchWithRetry(url, options, retries - 1);
            }
            throw error;
        }
    }

    async delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Signaling API methods
    async sendOffer(meetingId, userId, offer, toUserId = null) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=send_offer`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                offer: offer,
                to_user_id: toUserId
            })
        });
    }

    async sendAnswer(meetingId, userId, answer, toUserId = null) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=send_answer`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                answer: answer,
                to_user_id: toUserId
            })
        });
    }

    async sendCandidate(meetingId, userId, candidate, toUserId = null) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=send_candidate`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                candidate: candidate,
                to_user_id: toUserId
            })
        });
    }

    async getSignals(meetingId, userId, lastSignalId = 0, limit = 10) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=get_signals`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                last_signal_id: lastSignalId,
                limit: limit
            })
        });
    }

    async deleteSignal(meetingId, userId, signalId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=delete_signal`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                signal_id: signalId
            })
        });
    }

    // Meeting state API methods
    async startMeeting(meetingId, userId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/meeting_state.php?action=start_meeting`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId
            })
        });
    }

    async endMeeting(meetingId, userId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/meeting_state.php?action=end_meeting`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId
            })
        });
    }

    async joinMeeting(meetingId, userId, role) {
        return this.fetchWithRetry(`${this.baseUrl}/api/meeting_state.php?action=join_meeting`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                role: role
            })
        });
    }

    async leaveMeeting(meetingId, userId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/meeting_state.php?action=leave_meeting`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId
            })
        });
    }

    async listParticipants(meetingId, userId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/meeting_state.php?action=list_participants`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId
            })
        });
    }

    // Recording API methods
    async uploadRecording(meetingId, userId, recordingData, mimeType = 'video/webm') {
        return this.fetchWithRetry(`${this.baseUrl}/api/recording_upload.php`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                recording_data: recordingData,
                mime_type: mimeType
            })
        });
    }

    async sendRecordingChunk(meetingId, userId, recordingId, chunkIndex, chunkData) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=send_chunk`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                recording_id: recordingId,
                chunk_index: chunkIndex,
                chunk_data: chunkData
            })
        });
    }

    async getRecordingChunks(meetingId, userId, recordingId) {
        return this.fetchWithRetry(`${this.baseUrl}/api/signaling.php?action=get_chunks`, {
            method: 'POST',
            body: JSON.stringify({
                meeting_id: meetingId,
                user_id: userId,
                recording_id: recordingId
            })
        });
    }
}

// Polling helper class
class PollingManager {
    constructor(api, interval = 700) {
        this.api = api;
        this.interval = interval;
        this.timeoutId = null;
        this.isPolling = false;
        this.lastSignalId = 0;
    }

    start(meetingId, userId, callback, errorCallback) {
        this.isPolling = true;
        this.poll(meetingId, userId, callback, errorCallback);
    }

    stop() {
        this.isPolling = false;
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }
    }

    async poll(meetingId, userId, callback, errorCallback) {
        if (!this.isPolling) return;

        try {
            const response = await this.api.getSignals(meetingId, userId, this.lastSignalId);
            
            if (response.success && response.signals.length > 0) {
                // Update last signal ID
                this.lastSignalId = Math.max(...response.signals.map(s => s.id));
                // Process signals
                callback(response.signals);
            }
        } catch (error) {
            console.error('Polling error:', error);
            if (errorCallback) errorCallback(error);
        }

        if (this.isPolling) {
            this.timeoutId = setTimeout(() => {
                this.poll(meetingId, userId, callback, errorCallback);
            }, this.interval);
        }
    }
}

// Chunk upload helper for large recordings
class ChunkedUploader {
    constructor(api, chunkSize = 64 * 1024) { // 64KB chunks
        this.api = api;
        this.chunkSize = chunkSize;
    }

    async uploadRecording(meetingId, userId, blob, onProgress = null) {
        const totalChunks = Math.ceil(blob.size / this.chunkSize);
        const recordingId = Date.now(); // Temporary ID until we have real one
        
        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * this.chunkSize;
            const end = Math.min(start + this.chunkSize, blob.size);
            const chunk = blob.slice(start, end);
            
            const chunkData = await this.blobToBase64(chunk);
            
            await this.api.sendRecordingChunk(meetingId, userId, recordingId, chunkIndex, chunkData);
            
            if (onProgress) {
                onProgress(chunkIndex + 1, totalChunks);
            }
        }
        
        return recordingId;
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
}

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { MeetingAPI, PollingManager, ChunkedUploader };
}