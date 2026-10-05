/*
 * Admin package form: the category's booking style (data-kind on its
 * option) decides which pricing types are offered and which sections show;
 * the pricing type then shows/hides its own sections (hourly length, day
 * range). Inputs in hidden sections are disabled so they aren't submitted.
 * The server validates all of this again — this is only convenience.
 */
(function () {
    'use strict';

    function listHas(list, value) {
        return (list || '').split(' ').indexOf(value) !== -1;
    }

    function toggle(section, visible) {
        section.hidden = !visible;
        section.querySelectorAll('input, select, textarea').forEach(function (input) {
            input.disabled = !visible;
        });
    }

    function init(form) {
        var category = form.querySelector('[data-package-category]');
        var pricing = form.querySelector('[data-pricing-model]');

        if (!category || !pricing) {
            return;
        }

        function apply() {
            var option = category.options[category.selectedIndex];
            var kind = option ? option.getAttribute('data-kind') : 'vehicle';

            Array.prototype.forEach.call(pricing.options, function (model) {
                model.disabled = !listHas(model.getAttribute('data-kinds'), kind);
            });

            if (pricing.options[pricing.selectedIndex] && pricing.options[pricing.selectedIndex].disabled) {
                var firstAllowed = Array.prototype.find.call(pricing.options, function (model) { return !model.disabled; });
                if (firstAllowed) {
                    pricing.value = firstAllowed.value;
                }
            }

            var model = pricing.value;

            form.querySelectorAll('[data-package-kind]').forEach(function (section) {
                toggle(section, listHas(section.getAttribute('data-package-kind'), kind));
            });
            form.querySelectorAll('[data-pricing-shown-for]').forEach(function (section) {
                toggle(section, listHas(section.getAttribute('data-pricing-shown-for'), model));
            });
            form.querySelectorAll('[data-pricing-hidden-for]').forEach(function (section) {
                toggle(section, !listHas(section.getAttribute('data-pricing-hidden-for'), model));
            });
        }

        category.addEventListener('change', apply);
        pricing.addEventListener('change', apply);
        apply();
    }

    function boot() {
        document.querySelectorAll('[data-package-form]').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
