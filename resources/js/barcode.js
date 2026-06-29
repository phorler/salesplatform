// Camera barcode scanning for ISBNs (book EAN-13).
//
// Uses the native BarcodeDetector API where available (Android Chrome, etc.),
// and lazily loads @zxing/library as a fallback (desktop browsers / iOS Safari).
//
// Decoding uses the broad, known-working format set; we then *filter* to a valid
// book ISBN (13 digits, 978/979 prefix, good checksum) so the price/currency
// barcode and misreads are ignored. A live status callback reports what's seen.
//
// NOTE: getUserMedia requires a secure context (HTTPS or localhost).

const NATIVE_FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];
const CONSTRAINTS = { video: { facingMode: { ideal: 'environment' } }, audio: false };

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
    }

    static isSupported() {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }

    async start(video, onDetect, onStatus) {
        this.running = true;
        this.onDetect = onDetect;
        this.onStatus = onStatus || (() => {});

        if ('BarcodeDetector' in window) {
            await this._startNative(video);
        } else {
            await this._startZxing(video);
        }
    }

    // Reports every decode; accepts the first valid book ISBN.
    _consider(raw) {
        const digits = digitsOnly(raw);
        const ok = isBookIsbn(digits);
        this.onStatus(digits, ok);
        if (ok && this.running) {
            this.onDetect(digits);
            return true;
        }
        return false;
    }

    async _startNative(video) {
        this.stream = await navigator.mediaDevices.getUserMedia(CONSTRAINTS);
        video.srcObject = this.stream;
        video.setAttribute('playsinline', 'true');
        await video.play();

        let formats = NATIVE_FORMATS;
        try {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            const filtered = NATIVE_FORMATS.filter((f) => supported.includes(f));
            if (filtered.length) formats = filtered;
        } catch { /* use default list */ }
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
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13,
            BarcodeFormat.EAN_8,
            BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E,
        ]);

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
