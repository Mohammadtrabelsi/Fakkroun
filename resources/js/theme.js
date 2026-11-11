/*
 * Applies the saved (or system) theme before first paint, to avoid a flash.
 * Loaded as a classic, render-blocking script from the <head> (its own Vite
 * entry): keep it free of imports and exports.
 */
(function () {
    try {
        var saved = localStorage.getItem('theme');

        if (saved === 'dark' || (!saved && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        }
    } catch (e) {
        // Storage can be blocked (private mode): keep the default theme.
    }

    document.documentElement.classList.add('theme-ready');
})();
