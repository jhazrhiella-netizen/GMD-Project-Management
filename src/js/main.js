// Simple starter JS for later enhancements
document.addEventListener('DOMContentLoaded', function(){
    // Attach CSRF token automatically to fetch() calls for non-GET requests
    var meta = document.querySelector('meta[name="csrf-token"]');
    var csrf = meta ? meta.getAttribute('content') : null;
    if (csrf && window.fetch) {
        var _fetch = window.fetch;
        window.fetch = function(input, init) {
            init = init || {};
            var method = (init.method || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD') {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) init.headers.set('X-CSRF-Token', csrf);
                else if (Array.isArray(init.headers)) init.headers.push(['X-CSRF-Token', csrf]);
                else init.headers['X-CSRF-Token'] = csrf;
            }
            return _fetch(input, init);
        };
    }
});
