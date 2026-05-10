/**
 * Run after DOM is interactive. Safe when the module loads late (dynamic import + idle).
 */
export function domReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}
