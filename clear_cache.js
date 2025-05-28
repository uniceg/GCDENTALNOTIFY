// Clear browser cache for JS files
document.addEventListener('DOMContentLoaded', function() {
    // Add a timestamp query parameter to force browser to load fresh JS
    const scriptTag = document.querySelector('script[src="script.js"]');
    if (scriptTag) {
        const timestamp = new Date().getTime();
        scriptTag.src = `script.js?v=${timestamp}`;
    }
    console.log('Cache buster applied to script.js');
});
