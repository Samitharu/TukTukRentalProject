/*
 * Admin unit form: shows only the fields that apply to the chosen
 * category's kind (tuk tuk vs stay). Inputs in the hidden section are
 * disabled too, so they aren't submitted.
 */
(function () {
    'use strict';

    function init(form) {
        var select = form.querySelector('[data-unit-category]');

        if (!select) {
            return;
        }

        function apply() {
            var option = select.options[select.selectedIndex];
            var kind = option ? option.getAttribute('data-kind') : 'vehicle';

            form.querySelectorAll('[data-unit-kind]').forEach(function (section) {
                var visible = section.getAttribute('data-unit-kind') === kind;

                section.hidden = !visible;
                section.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = !visible;
                });
            });
        }

        select.addEventListener('change', apply);
        apply();
    }

    function boot() {
        document.querySelectorAll('[data-unit-form]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
