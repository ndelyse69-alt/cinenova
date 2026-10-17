(function () {
    'use strict';

    // Fire-and-forget visit beacon — every page load, once.
    try {
        const body = new URLSearchParams({ path: window.location.pathname });
        if (navigator.sendBeacon) {
            navigator.sendBeacon(CN_BASE + 'backend/api/track.php', body);
        } else {
            fetch(CN_BASE + 'backend/api/track.php', { method: 'POST', body: body, keepalive: true });
        }
    } catch (e) { /* tracking is best-effort, never block the page */ }

    document.addEventListener('DOMContentLoaded', function () {
        // ---------------------------------------------------------
        // FAQ accordion
        // ---------------------------------------------------------
        window.toggleFAQ = function (button) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            const answer = button.nextElementSibling;
            const icon = button.querySelector('.faq-icon');

            document.querySelectorAll('.faq-item button').forEach((otherBtn) => {
                if (otherBtn !== button) {
                    otherBtn.setAttribute('aria-expanded', 'false');
                    const otherAnswer = otherBtn.nextElementSibling;
                    if (otherAnswer) otherAnswer.style.maxHeight = '0';
                    const otherIcon = otherBtn.querySelector('.faq-icon');
                    if (otherIcon) otherIcon.style.transform = '';
                }
            });

            button.setAttribute('aria-expanded', String(!isExpanded));
            if (!isExpanded) {
                answer.style.maxHeight = answer.scrollHeight + 'px';
            } else {
                answer.style.maxHeight = '0';
            }
        };

        document.querySelectorAll('.faq-item button').forEach((btn) => {
            btn.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        });

        // ---------------------------------------------------------
        // Smooth scroll for in-page anchors
        // ---------------------------------------------------------
        document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
            anchor.addEventListener('click', function (e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                const target = document.querySelector(targetId);
                if (target) {
                    e.preventDefault();
                    const headerOffset = 24;
                    const elementPosition = target.getBoundingClientRect().top;
                    const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    window.scrollTo({ top: offsetPosition, behavior: 'smooth' });
                    if (history.pushState) history.pushState(null, null, targetId);
                }
            });
        });

        // ---------------------------------------------------------
        // Scroll-reveal for elements marked .reveal (used on the
        // Watch Page sections, degrades gracefully everywhere else)
        // ---------------------------------------------------------
        const revealTargets = document.querySelectorAll('.reveal');
        if (revealTargets.length && 'IntersectionObserver' in window) {
            const revealObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
            revealTargets.forEach((el) => revealObserver.observe(el));
        } else {
            revealTargets.forEach((el) => el.classList.add('is-visible'));
        }

        // ---------------------------------------------------------
        // Lazy-load safety net for browsers without native support
        // ---------------------------------------------------------
        if ('IntersectionObserver' in window) {
            const lazyImages = document.querySelectorAll('img[loading="lazy"]');
            const imageObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        const src = img.getAttribute('src');
                        if (src) img.src = src;
                        imageObserver.unobserve(img);
                    }
                });
            });
            lazyImages.forEach((img) => imageObserver.observe(img));
        }
    });
})();
