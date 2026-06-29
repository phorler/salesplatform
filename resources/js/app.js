import './bootstrap';
import './barcode';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Register the PWA service worker (installable / home-screen on mobile).
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Service worker is a progressive enhancement; ignore failures.
        });
    });
}
