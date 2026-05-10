/**
 * AGRIGUARD Landing page: scroll-reveal only (navbar handled by navbar.js)
 *
 * This module is loaded asynchronously (dynamic import + idle). DOMContentLoaded
 * may already have fired by then — always check readyState before waiting on it.
 */
function initLandingScrollReveal() {
    const revealEls = document.querySelectorAll('.scroll-reveal');
    if (!revealEls.length) {
        return;
    }
    const observer = new IntersectionObserver(
        function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('revealed');
                }
            });
        },
        { rootMargin: '0px 0px -60px 0px', threshold: 0.1 }
    );
    revealEls.forEach(function (el) {
        observer.observe(el);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLandingScrollReveal);
} else {
    initLandingScrollReveal();
}
