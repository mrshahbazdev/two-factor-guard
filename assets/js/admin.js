(function() {
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.tfg-copy');
        if (!btn) {
            return;
        }
        var selector = btn.getAttribute('data-target');
        if (!selector) {
            return;
        }
        var el = document.querySelector(selector);
        if (!el) {
            return;
        }
        var text = el.textContent.trim();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                btn.textContent = (window.tfg_i18n && tfg_i18n.copied) || 'Copied!';
                btn.classList.add('copied');
                setTimeout(function() {
                    btn.textContent = (window.tfg_i18n && tfg_i18n.copy) || 'Copy';
                    btn.classList.remove('copied');
                }, 1500);
            });
        } else {
            var range = document.createRange();
            range.selectNode(el);
            window.getSelection().removeAllRanges();
            window.getSelection().addRange(range);
            try {
                document.execCommand('copy');
                btn.textContent = (window.tfg_i18n && tfg_i18n.copied) || 'Copied!';
                setTimeout(function() {
                    btn.textContent = (window.tfg_i18n && tfg_i18n.copy) || 'Copy';
                }, 1500);
            } catch (err) {}
            window.getSelection().removeAllRanges();
        }
    });
})();
