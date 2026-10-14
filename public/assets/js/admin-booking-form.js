/*
 * Admin manual booking form (booking on a customer's behalf):
 *  - find and pick an existing customer (prefills their details),
 *  - show only the add-ons the selected package offers,
 *  - live price + availability preview from the server (never computed here).
 * The form still works without this script: all add-ons show, and the
 * server prices the booking on submit as always.
 */
(function () {
    'use strict';

    function debounce(fn, ms) {
        var timer;

        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, ms);
        };
    }

    function money(value) {
        return Number(value || 0).toFixed(2);
    }

    function initCustomerPicker(form) {
        var search = form.querySelector('[data-customer-search]');
        var results = form.querySelector('[data-customer-results]');
        var searchField = form.querySelector('[data-customer-search-field]');
        var picked = form.querySelector('[data-customer-picked]');
        var pickedLabel = form.querySelector('[data-customer-picked-label]');
        var idInput = form.querySelector('[data-customer-id]');
        var email = form.querySelector('#email');

        if (!search || !results || !idInput) {
            return;
        }

        function setField(selector, value) {
            var input = form.querySelector(selector);

            if (input) {
                input.value = value || '';
            }
        }

        function pick(customer) {
            idInput.value = customer.id;
            setField('#full_name', customer.full_name);
            setField('#email', customer.email);
            setField('#phone', customer.phone);
            setField('#nationality', customer.nationality);
            email.readOnly = true;
            pickedLabel.textContent = customer.full_name + ' (' + customer.email + ')';
            picked.hidden = false;
            searchField.hidden = true;
            results.hidden = true;
        }

        function clear() {
            idInput.value = '';
            ['#full_name', '#email', '#phone', '#nationality'].forEach(function (selector) {
                setField(selector, '');
            });
            email.readOnly = false;
            picked.hidden = true;
            searchField.hidden = false;
            search.value = '';
            search.focus();
        }

        function render(customers) {
            results.innerHTML = '';
            results.hidden = customers.length === 0;

            customers.forEach(function (customer) {
                var item = document.createElement('li');
                var button = document.createElement('button');

                button.type = 'button';
                button.textContent = customer.full_name + ' — ' + customer.email + (customer.phone ? ' · ' + customer.phone : '');
                button.addEventListener('click', function () {
                    pick(customer);
                });
                item.appendChild(button);
                results.appendChild(item);
            });
        }

        var lookup = debounce(function () {
            var term = search.value.trim();

            if (term.length < 2) {
                render([]);

                return;
            }

            fetch(form.dataset.lookupUrl + '?q=' + encodeURIComponent(term), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then(function (response) {
                    return response.ok ? response.json() : [];
                })
                .then(render)
                .catch(function () {
                    render([]);
                });
        }, 250);

        search.addEventListener('input', lookup);
        form.querySelector('[data-customer-clear]').addEventListener('click', clear);
    }

    function initAddons(form) {
        var packageSelect = form.querySelector('[data-package-select]');
        var rows = form.querySelectorAll('[data-addon-packages]');
        var empty = form.querySelector('[data-addons-empty]');

        if (!packageSelect || rows.length === 0) {
            return;
        }

        function apply() {
            var packageId = packageSelect.value;
            var shown = 0;

            rows.forEach(function (row) {
                var offered = packageId !== '' && row.dataset.addonPackages.split(',').indexOf(packageId) !== -1;
                var input = row.querySelector('input');

                row.hidden = !offered;
                // Not submitted for another package's add-ons.
                input.disabled = !offered;
                shown += offered ? 1 : 0;
            });

            if (empty) {
                empty.hidden = shown > 0 || packageId === '';
            }
        }

        packageSelect.addEventListener('change', apply);
        apply();
    }

    function initPricePreview(form) {
        var preview = form.querySelector('[data-price-preview]');

        if (!preview) {
            return;
        }

        var labels = JSON.parse(preview.dataset.labels || '{}');
        var latest = 0;

        function line(label, amount, currency) {
            return '<dt>' + label + '</dt><dd>' + money(amount) + ' ' + currency + '</dd>';
        }

        function show(html) {
            preview.innerHTML = html;
        }

        function escape(text) {
            var div = document.createElement('div');

            div.textContent = text;

            return div.innerHTML;
        }

        function render(price) {
            var currency = escape(price.currency_code);
            var html = '<dl class="admin-price-preview__lines">';

            html += line(labels.rental, price.base_amount, currency);

            if (Number(price.seasonal_adjustment) !== 0) {
                html += line(labels.seasonal, price.seasonal_adjustment, currency);
            }

            if (Number(price.addons_total) > 0) {
                html += line(labels.addons, price.addons_total, currency);
            }

            if (Number(price.delivery_fee) > 0) {
                html += line(labels.delivery, price.delivery_fee, currency);
            }

            if (Number(price.coupon_discount) > 0) {
                html += line(labels.coupon, -price.coupon_discount, currency);
            }

            html += '<dt><strong>' + labels.total + '</strong></dt><dd><strong>' + money(price.total) + ' ' + currency + '</strong></dd>';

            if (Number(price.deposit_amount) > 0) {
                html += line(labels.deposit, price.deposit_amount, currency);
            }

            html += '</dl>';

            if (form.querySelector('#coupon_code').value.trim() !== '' && !price.coupon_applied) {
                html += '<p class="admin-hint">' + labels.couponIgnored + '</p>';
            }

            html += price.units_free > 0
                ? '<p class="admin-hint">' + labels.free.replace(':free', price.units_free).replace(':total', price.units_total) + '</p>'
                : '<p class="admin-price-preview__warn">' + labels.none + '</p>';

            show(html);
        }

        var refresh = debounce(function () {
            if (form.querySelector('[data-package-select]').value === ''
                || form.querySelector('#start_date').value === ''
                || form.querySelector('#end_date').value === '') {
                return;
            }

            var request = ++latest;

            show('<p class="admin-hint" style="margin:0;">' + labels.loading + '</p>');

            fetch(form.dataset.quoteUrl, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                body: new FormData(form),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    // An older, slower response must not overwrite a newer one.
                    if (request !== latest) {
                        return;
                    }

                    if (result.ok) {
                        render(result.body);

                        return;
                    }

                    var errors = result.body && result.body.errors
                        ? Object.keys(result.body.errors).map(function (key) { return result.body.errors[key][0]; })
                        : [labels.error];

                    show('<p class="admin-price-preview__warn">' + errors.map(escape).join('<br>') + '</p>');
                })
                .catch(function () {
                    if (request === latest) {
                        show('<p class="admin-price-preview__warn">' + labels.error + '</p>');
                    }
                });
        }, 400);

        form.addEventListener('change', function (event) {
            if (!event.target.closest('[data-customer-search-field]')) {
                refresh();
            }
        });
        form.addEventListener('input', function (event) {
            if (event.target.matches('[name^="addons"], #coupon_code, #hours')) {
                refresh();
            }
        });
        refresh();
    }

    function boot() {
        document.querySelectorAll('[data-manual-booking-form]').forEach(function (form) {
            initCustomerPicker(form);
            initAddons(form);
            initPricePreview(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
