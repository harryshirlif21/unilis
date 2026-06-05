/**
 * Success audio feedback for RFID, biometric, and QR login.
 */
(function (global) {
    const MESSAGES = {
        rfid: 'RFID login successful',
        biometric: 'Biometric login successful',
        qr: 'QR login successful'
    };

    function playBeep() {
        const AudioContext = global.AudioContext || global.webkitAudioContext;
        if (!AudioContext) {
            return;
        }

        const ctx = new AudioContext();

        const resume = ctx.state === 'suspended' ? ctx.resume() : Promise.resolve();
        resume.then(function () {
            const now = ctx.currentTime;

            function tone(frequency, startOffset, duration, volume) {
                const oscillator = ctx.createOscillator();
                const gain = ctx.createGain();

                oscillator.type = 'sine';
                oscillator.frequency.value = frequency;
                oscillator.connect(gain);
                gain.connect(ctx.destination);

                gain.gain.setValueAtTime(0.0001, now + startOffset);
                gain.gain.exponentialRampToValueAtTime(volume, now + startOffset + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, now + startOffset + duration);

                oscillator.start(now + startOffset);
                oscillator.stop(now + startOffset + duration + 0.02);
            }

            tone(880, 0, 0.12, 0.22);
            tone(1174.66, 0.14, 0.2, 0.24);
            tone(1567.98, 0.3, 0.24, 0.2);
        }).catch(function () {
            // Ignore autoplay restrictions silently.
        });
    }

    function speakMessage(method) {
        if (!('speechSynthesis' in global)) {
            return;
        }

        global.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(MESSAGES[method] || 'Login successful');
        utterance.rate = 1.02;
        utterance.pitch = 1;
        utterance.volume = 0.95;
        global.speechSynthesis.speak(utterance);
    }

    function playAuthSuccessSound(method) {
        playBeep();
        speakMessage(method || 'qr');
    }

    global.playAuthSuccessSound = playAuthSuccessSound;
})(window);
