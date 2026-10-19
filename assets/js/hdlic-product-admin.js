/**
 * "License Keys" product-data tab: show/hide the key controls with the checkbox, and submit
 * the paste/generate panels as their own forms.
 *
 * Those panels are never a real <form>: the product-data box sits inside wp-admin's outer
 * product-edit <form id="post">, which would absorb a nested one (see the comment in
 * HDLIC_Product::render_product_data_panel()). So a click builds a separate <form> attached
 * directly to <body>, carrying only that panel's own fields plus the one "action" it needs.
 */
(function () {
    'use strict';

    function init() {
        var cb = document.getElementById('hdlic_enabled');
        var box = document.getElementById('hdlic-key-management');
        if (cb && box) {
            cb.addEventListener('change', function () {
                box.style.display = cb.checked ? '' : 'none';
            });
        }

        document.querySelectorAll('[data-hdlic-panel] [data-hdlic-submit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var panel = btn.closest('[data-hdlic-panel]');
                var form = document.createElement('form');
                form.method = 'post';
                form.action = panel.getAttribute('data-hdlic-url') + '?action=' + encodeURIComponent(panel.getAttribute('data-hdlic-action'));
                form.style.display = 'none';
                panel.querySelectorAll('input, textarea, select').forEach(function (field) {
                    if (!field.name) {
                        return;
                    }
                    var clone = document.createElement('input');
                    clone.type = 'hidden';
                    clone.name = field.name;
                    clone.value = field.value;
                    form.appendChild(clone);
                });
                document.body.appendChild(form);
                form.submit();
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
