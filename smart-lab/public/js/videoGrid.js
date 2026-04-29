/**
 * VideoGrid Module - Reusable component for managing 4-video grid display
 * Features:
 * - Display 4 video streams at a time
 * - Navigation (next/previous)
 * - Auto-rotate capability
 */

class VideoGrid {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        this.videoSources = [];
        this.currentIndex = 0;
        this.autoRotate = options.autoRotate || false;
        this.rotateInterval = options.rotateInterval || 15000; // 15 seconds
        this.rotationTimer = null;
        
        if (!this.container) {
            console.error(`Container with id "${containerId}" not found`);
            return;
        }
        
        this.init();
    }
    
    init() {
        this.render();
        if (this.autoRotate) {
            this.startAutoRotate();
        }
    }
    
    setVideos(videos) {
        this.videoSources = videos || [];
        this.currentIndex = 0;
        this.render();
        if (this.autoRotate && this.rotationTimer) {
            clearInterval(this.rotationTimer);
            this.startAutoRotate();
        }
    }
    
    getDisplayVideos() {
        const videosPerPage = 4;
        const startIndex = this.currentIndex;
        const endIndex = startIndex + videosPerPage;
        return this.videoSources.slice(startIndex, endIndex);
    }
    
    render() {
        const videos = this.getDisplayVideos();
        let html = '<div class="video-grid-wrapper">';
        
        // Render video items
        html += '<div class="video-grid">';
        
        for (let i = 0; i < 4; i++) {
            if (i < videos.length) {
                const video = videos[i];
                html += `
                    <div class="video-item">
                        <div class="video-frame">
                            <img 
                                src="${this.escapeHtml(video.url)}"
                                alt="${this.escapeHtml(video.name)}"
                                class="video-stream"
                                onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22400%22 height=%22300%22%3E%3Crect fill=%22%23333%22 width=%22400%22 height=%22300%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 fill=%22%23fff%22 font-size=%2220%22 text-anchor=%22middle%22 dominant-baseline=%22middle%22%3ECamera Offline%3C/text%3E%3C/svg%3E'"
                            />
                            <div class="video-label">
                                <span class="video-name">${this.escapeHtml(video.name)}</span>
                                <span class="video-status ${video.status === 'active' ? 'active' : 'offline'}">
                                    ${video.status === 'active' ? '● Live' : '○ Offline'}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Empty placeholder
                html += `
                    <div class="video-item placeholder">
                        <div class="video-frame empty">
                            <div class="empty-state">No Camera</div>
                        </div>
                    </div>
                `;
            }
        }
        
        html += '</div>';
        
        // Controls
        html += '<div class="video-grid-controls">';
        html += `<span class="grid-counter">${this.currentIndex + 1} - ${Math.min(this.currentIndex + 4, this.videoSources.length)} of ${this.videoSources.length}</span>`;
        
        if (this.currentIndex > 0) {
            html += '<button class="grid-btn grid-prev" onclick="this.closest(\'.smartlab-section\').videoGrid?.prevPage()">← Previous</button>';
        }
        
        if (this.currentIndex + 4 < this.videoSources.length) {
            html += '<button class="grid-btn grid-next" onclick="this.closest(\'.smartlab-section\').videoGrid?.nextPage()">Next →</button>';
        }
        
        html += '</div></div>';
        
        this.container.innerHTML = html;
    }
    
    nextPage() {
        if (this.currentIndex + 4 < this.videoSources.length) {
            this.currentIndex += 4;
            this.render();
        }
    }
    
    prevPage() {
        if (this.currentIndex > 0) {
            this.currentIndex = Math.max(0, this.currentIndex - 4);
            this.render();
        }
    }
    
    startAutoRotate() {
        if (this.videoSources.length <= 4) {
            return; // No need to rotate if 4 or fewer videos
        }
        
        this.rotationTimer = setInterval(() => {
            if (this.currentIndex + 4 < this.videoSources.length) {
                this.nextPage();
            } else {
                this.currentIndex = 0;
                this.render();
            }
        }, this.rotateInterval);
    }
    
    stopAutoRotate() {
        if (this.rotationTimer) {
            clearInterval(this.rotationTimer);
            this.rotationTimer = null;
        }
    }
    
    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VideoGrid;
}
