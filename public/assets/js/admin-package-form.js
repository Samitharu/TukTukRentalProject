/*
 * Admin package form (simple version): the category's booking style
 * (data-kind on its option) decides which "How is it charged?" answers and
 * which vehicle types are offered; the chosen answer then shows only its own
 * fields (price label, hours, km). Inputs in hidden sections are disabled so
 * they aren't submitted. The server validates all of this again — this is
 * only convenience.
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
        var vehicleType = form.querySelector('[data-vehicle-type]');

        if (!category) {
            return;
        }

        function checkedCharge() {
            var checked = form.querySelector('input[name="charge"]:checked:not([disabled])');

            return checked ? checked.value : '';
        }

        function apply() {
            var option = category.options[category.selectedIndex];
            var kind = option ? option.getAttribute('data-kind') : 'vehicle';
            var line = option ? option.value : '';

            // Charging answers for this booking style; keep or pick one.
            var options = form.querySelectorAll('[data-charge-option]');
            options.forEach(function (label) {
                toggle(label, listHas(label.getAttribute('data-kinds'), kind));
            });
            if (checkedCharge() === '') {
                var first = Array.prototype.find.call(options, function (label) { return !label.hidden; });
                if (first) {
                    first.querySelector('input').checked = true;
                }
            }

            // Vehicle types of the chosen line only.
            if (vehicleType) {
                Array.prototype.forEach.call(vehicleType.options, function (type) {
                    var typeLine = type.getAttribute('data-line');
                    type.hidden = typeLine !== null && typeLine !== line;
                    type.disabled = type.hidden;
                });
                if (vehicleType.selectedOptions[0] && vehicleType.selectedOptions[0].disabled) {
                    vehicleType.value = '';
                }
            }

            var charge = checkedCharge();

            form.querySelectorAll('[data-package-kind]').forEach(function (section) {
                toggle(section, listHas(section.getAttribute('data-package-kind'), kind));
            });
            form.querySelectorAll('[data-charge]').forEach(function (section) {
                toggle(section, listHas(section.getAttribute('data-charge'), charge));
            });
        }

        category.addEventListener('change', apply);
        form.addEventListener('change', function (event) {
            if (event.target.name === 'charge') {
                apply();
            }
        });
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
