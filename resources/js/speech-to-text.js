export default function speechToText(config = {}) {
    const SpeechRecognition = typeof window !== 'undefined'
        ? (window.SpeechRecognition || window.webkitSpeechRecognition || null)
        : null;

    return {
        isSupported: Boolean(SpeechRecognition),
        isListening: false,
        isStarting: false,
        selectedLanguage: config.defaultLanguage || 'en-IN',
        targetId: config.targetId || '',
        errorMessage: '',
        interimTranscript: '',
        recognition: null,
        manualStop: false,

        languages: [
            { code: 'en-IN', label: 'English (IN)' },
            { code: 'hi-IN', label: 'हिंदी (IN)' },
            { code: 'gu-IN', label: 'ગુજરાતી (IN)' },
        ],

        init() {
            if (!this.isSupported) {
                return;
            }

            this._handleBeforeUnload = () => {
                this.stopListening();
            };
            window.addEventListener('beforeunload', this._handleBeforeUnload);

            if (typeof this.$cleanup === 'function') {
                this.$cleanup(() => {
                    this.stopListening();
                    window.removeEventListener('beforeunload', this._handleBeforeUnload);
                });
            }
        },

        getTargetElement() {
            if (this.targetId) {
                const el = document.getElementById(this.targetId) || document.querySelector(this.targetId);
                if (el) return el;
            }
            return this.$root.closest('div')?.querySelector('textarea, input[type="text"]') || null;
        },

        toggleListening() {
            if (this.isListening) {
                this.stopListening();
            } else {
                this.startListening();
            }
        },

        startListening() {
            if (!this.isSupported) {
                this.errorMessage = 'Speech recognition is not supported in this browser. Please use Google Chrome or Microsoft Edge.';
                return;
            }

            if (this.isListening || this.isStarting) {
                return;
            }

            const target = this.getTargetElement();
            if (!target) {
                this.errorMessage = 'Could not find the target text field.';
                return;
            }

            this.errorMessage = '';
            this.interimTranscript = '';
            this.manualStop = false;
            this.isStarting = true;

            try {
                if (this.recognition) {
                    try { this.recognition.abort(); } catch (_) {}
                    this.recognition = null;
                }

                const rec = new SpeechRecognition();
                rec.lang = this.selectedLanguage;
                rec.continuous = true;
                rec.interimResults = true;
                rec.maxAlternatives = 1;

                rec.onstart = () => {
                    this.isStarting = false;
                    this.isListening = true;
                    this.errorMessage = '';
                };

                rec.onresult = (event) => {
                    let interim = '';
                    for (let i = event.resultIndex; i < event.results.length; ++i) {
                        const result = event.results[i];
                        const transcript = result[0]?.transcript || '';
                        if (result.isFinal) {
                            this.appendFinalTranscript(transcript);
                        } else {
                            interim += transcript;
                        }
                    }
                    this.interimTranscript = interim.trim();
                };

                rec.onerror = (event) => {
                    this.isStarting = false;
                    const err = event.error;

                    if (err === 'not-allowed' || err === 'service-not-allowed') {
                        this.errorMessage = 'Microphone permission was denied. Please allow microphone access in your browser settings.';
                        this.isListening = false;
                    } else if (err === 'audio-capture') {
                        this.errorMessage = 'No microphone detected. Please verify your microphone is plugged in and enabled.';
                        this.isListening = false;
                    } else if (err === 'network') {
                        this.errorMessage = 'Network connection issue during recognition. Please check your internet connection.';
                        this.isListening = false;
                    } else if (err === 'no-speech') {
                        this.errorMessage = 'No speech detected. Please speak into your microphone.';
                    } else if (err === 'aborted') {
                        if (!this.manualStop) {
                            this.isListening = false;
                        }
                    } else {
                        this.errorMessage = 'Speech recognition error: ' + (event.message || err);
                        this.isListening = false;
                    }
                    this.interimTranscript = '';
                };

                rec.onend = () => {
                    this.isStarting = false;
                    this.isListening = false;
                    this.interimTranscript = '';
                    this.recognition = null;
                };

                this.recognition = rec;
                rec.start();
            } catch (err) {
                this.isStarting = false;
                this.isListening = false;
                if (err.name !== 'InvalidStateError') {
                    this.errorMessage = 'Unable to start speech recognition: ' + err.message;
                }
            }
        },

        stopListening() {
            this.manualStop = true;
            this.isStarting = false;
            this.interimTranscript = '';

            if (this.recognition) {
                try {
                    this.recognition.stop();
                } catch (e) {
                    try { this.recognition.abort(); } catch (_) {}
                }
                this.recognition = null;
            }
            this.isListening = false;
        },

        appendFinalTranscript(text) {
            const clean = (text || '').trim();
            if (!clean) return;

            const target = this.getTargetElement();
            if (!target) return;

            if (target.isContentEditable) {
                target.focus();
                const sel = window.getSelection();
                if (sel && sel.rangeCount > 0 && target.contains(sel.anchorNode)) {
                    // Insert at current cursor position if selection is inside target
                    const range = sel.getRangeAt(0);
                    range.deleteContents();
                    const textNode = document.createTextNode((range.startOffset > 0 ? ' ' : '') + clean + ' ');
                    range.insertNode(textNode);
                    range.setStartAfter(textNode);
                    range.setEndAfter(textNode);
                    sel.removeAllRanges();
                    sel.addRange(range);
                } else {
                    // Otherwise append to innerHTML / textContent with appropriate spacing
                    const hasContent = target.textContent.trim().length > 0;
                    if (hasContent) {
                        target.innerHTML += (/\s$/.test(target.innerHTML) ? '' : ' ') + clean;
                    } else {
                        target.innerHTML = clean;
                    }
                }
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
                target.scrollTop = target.scrollHeight;
                return;
            }

            const current = target.value || '';
            let updated = '';

            if (current.length === 0) {
                updated = clean;
            } else {
                const separator = /\s$/.test(current) ? '' : ' ';
                updated = current + separator + clean;
            }

            target.value = updated;

            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));

            target.scrollTop = target.scrollHeight;
        },

        changeLanguage(newLang) {
            this.selectedLanguage = newLang;
            if (this.isListening) {
                this.stopListening();
                setTimeout(() => {
                    this.startListening();
                }, 200);
            }
        },

        clearError() {
            this.errorMessage = '';
        }
    };
}

