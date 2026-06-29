// Camera barcode scanning for ISBNs (EAN-13 et al).
//
// Uses the native BarcodeDetector API where available (Android Chrome, etc.).
// On browsers that lack it — notably iOS Safari — it lazily loads @zxing/library
// as a fallback so the main bundle stays small.
//
// NOTE: getUserMedia requires a secure context (HTTPS or localhost). Over plain
// http://<lan-ip> the camera will not start — use the https hostname.

const FORMATS = ['ean_13', 'ean_8', 'upc_a', 'upc_e'];

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

    /**
     * Start scanning into the given <video> element. onDetect(rawValue) is called
     * once a barcode is read; the caller is expected to stop() afterwards.
     */
    async start(video, onDetect) {
        this.running = true;

        this.stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
        });
        video.srcObject = this.stream;
        video.setAttribute('playsinline', 'true');
        await video.play();

        if ('BarcodeDetector' in window) {
            await this.#runNative(video, onDetect);
        } else {
            await this.#runZxing(video, onDetect);
        }
    }

    async #runNative(video, onDetect) {
        let formats = FORMATS;
        try {
            const supported = await window.BarcodeDetector.getSupportedFormats();
            formats = FORMATS.filter((f) => supported.includes(f));
        } catch {
            // getSupportedFormats not available; fall back to our list.
        }
        this.detector = new window.BarcodeDetector({ formats });

        const tick = async () => {
            if (!this.running) return;
            try {
                const codes = await this.detector.detect(video);
                if (codes.length && this.running) {
                    onDetect(codes[0].rawValue);
                    return;
                }
            } catch {
                // transient detect errors are ignored; keep scanning
            }
            this.raf = requestAnimationFrame(tick);
        };
        this.raf = requestAnimationFrame(tick);
    }

    async #runZxing(video, onDetect) {
        const { BrowserMultiFormatReader, DecodeHintType, BarcodeFormat } = await import('@zxing/library');

        const hints = new Map();
        hints.set(DecodeHintType.POSSIBLE_FORMATS, [
            BarcodeFormat.EAN_13,
            BarcodeFormat.EAN_8,
            BarcodeFormat.UPC_A,
            BarcodeFormat.UPC_E,
        ]);

        this.zxing = new BrowserMultiFormatReader(hints);
        this.zxing.decodeFromVideoElement(video, (result) => {
            if (result && this.running) {
                onDetect(result.getText());
            }
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
