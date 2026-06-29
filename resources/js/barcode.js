// Camera barcode scanning for ISBNs (book EAN-13).
//
// Uses the native BarcodeDetector API where available (Android Chrome, etc.),
// and lazily loads @zxing/library as a fallback (desktop browsers / iOS Safari).
//
// Reliability measures, because books carry a second (price/currency) barcode and
// single-frame reads misfire:
//   - only accept a valid book ISBN (13 digits, 978/979 prefix, good checksum)
//     so the price barcode and garbage reads are ignored;
//   - require the same value on two consecutive reads before accepting.
//
// NOTE: getUserMedia requires a secure context (HTTPS or localhost).

const FORMATS = ['ean_13'];
const CONSTRAINTS = {
    video: {
        facingMode: { ideal: 'environment' },
        width: { ideal: 1280 },
        height: { ideal: 720 },
    },
    audio: false,
};
const REQUIRED_CONFIRMATIONS = 2;

function digitsOnly(raw) {
    return (raw || '').replace(/\D/g, '');
}

// A book ISBN barcode is an EAN-13 in the 978/979 (Bookland) range.
export function isBookIsbn(raw) {
    const s = digitsOnly(raw);
    if (s.length !== 13 || !(s.startsWith('978') || s.startsWith('979'))) {
        return false;
    }
    let sum = 0;
    for (let i = 0; i < 12; i++) {
        sum += parseInt(s[i], 10) * (i % 2 === 0 ? 1 : 3);
    }
    const check = (10 - (sum % 10)) % 10;
    return check === parseInt(s[12], 10);
}

export class BarcodeScanner {
    constructor() {
        this.stream = null;
        this.detector = null;
        this.zxing = null;
        this.raf = null;
        this.running = false;
        this.candidate = null;
        this.count = 0;
    }

    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    async start(video, onDetect) {
        this.running = true;
        this.candidate = null;
        this.count = 0;
        this.onDetect = onDetect;

        if ('BarcodeDetector' in window) {
            await this._startNative(video);
        } else {
            await this._startZxing(video);
        }
    }

    // Returns true once a confirmed ISBN has been accepted.
    _consider(raw) {
        if (!isBookIsbn(raw)) {
            return false;
        }
        const isbn = digitsOnly(raw);
        if (isbn === this.candidate) {
            this.count += 1;
        } else {
            this.candidate = isbn;
            this.count = 1;
        }
        if (this.count >= REQUIRED_CONFIRMATIONS && this.running) {
            this.onDetect(isbn);
            return true;
        }
        return false;
    }

    async _startNative(video) {
        this.stream = await navigator.mediaDevices.getUserMedia(CONSTRAINTS);
        video.srcObject = this.stream;
        video.setAttribute('playsinline', 'true');
        await video.play();

        // Best-effort continuous autofocus for sharper reads.
        try {
            await this.stream.getVideoTracks()[0].applyConstraints({ advanced: [{ focusMode: 'continuous' }] });
        } catch { /* unsupported; ignore */ }

        let formats = FORMATS;
        try {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            formats = FORMATS.filter((f) => supported.includes(f));
            if (formats.length === 0) formats = ['ean_13'];
        } catch { /* use default */ }
        this.detector = new window.BarcodeDetector({ formats });

        const tick = async () => {
            if (!this.running) return;
            try {
                const codes = await this.detector.detect(video);
                for (const code of codes) {
                    if (this._consider(code.rawValue)) return; // accepted; stop looping
                }
            } catch { /* transient; keep scanning */ }
            this.raf = requestAnimationFrame(tick);
        };
        this.raf = requestAnimationFrame(tick);
    }

    async _startZxing(video) {
        const { BrowserMultiFormatReader, DecodeHintType, BarcodeFormat } = await import('@zxing/library');

        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [BarcodeFormat.EAN_13]);
        hints.set(DecodeHintType.TRY_HARDER, true);

        this.zxing = new BrowserMultiFormatReader(hints);
        await this.zxing.decodeFromConstraints(CONSTRAINTS, video, (result) => {
            if (result) this._consider(result.getText());
        });
    }

    stop() {
        this.running = false;
        if (this.raf) {
            cancelAnimationFrame(this.raf);
            this.raf = null;
        }
        if (this.zxing) {
            try { this.zxing.reset(); } catch { /* noop */ }
            this.zxing = null;
        }
        if (this.stream) {
            this.stream.getTracks().forEach((t) => t.stop());
            this.stream = null;
        }
    }
}

window.BarcodeScanner = BarcodeScanner;
