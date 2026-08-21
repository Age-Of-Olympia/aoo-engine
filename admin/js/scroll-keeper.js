/**
 * Keeps the scroll position across a page's PRG round-trips (submit →
 * redirect → reload): every submit stores the offset for this exact URL,
 * the next load restores and clears it.
 *
 * Motivated by the character sheet: chaining +1/−1 clicks on the
 * progression card would land back at the top of the page each time.
 */
(() => {
    'use strict';

    const key = `adminScroll:${location.pathname}${location.search}`;

    document.addEventListener('submit', () => {
        sessionStorage.setItem(key, String(window.scrollY));
    });

    document.addEventListener('DOMContentLoaded', () => {
        const saved = sessionStorage.getItem(key);
        if (saved !== null) {
            sessionStorage.removeItem(key);
            window.scrollTo(0, parseInt(saved, 10));
        }
    });
})();
