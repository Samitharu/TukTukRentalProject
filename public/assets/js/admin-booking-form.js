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

    // Only the offers of the selected package, and only the fields the
    // package being added needs. Without this script every field shows, and
    // the server keeps the ones that apply.
    function initOffers(form) {
        var card = form.querySelector('[data-offer-card]');

        if (!card) {
            return;
        }

        var packageSelect = form.querySelector('[data-package-select]');
        var offerSelect = card.querySelector('[data-offer-select]');
        var fields = card.querySelector('[data-offer-fields]');

        function apply() {
            var packageId = packageSelect.value;
            var shown = 0;

            Array.prototype.forEach.call(offerSelect.options, function (option) {
                if (!option.dataset.package) {
                    return;
                }

                var offered = option.dataset.package === packageId;

                option.hidden = !offered;
                option.disabled = !offered;
                shown += offered ? 1 : 0;

                if (!offered && option.selected) {
                    offerSelect.value = '';
                }
            });

            card.hidden = shown === 0;

            var selected = offerSelect.options[offerSelect.selectedIndex];
            var kind = selected ? selected.dataset.fields : '';

            fields.hidden = !kind;
            fields.querySelectorAll('[data-offer-for]').forEach(function (section) {
                section.hidden = section.dataset.offerFor !== kind;
            });
        }

        packageSelect.addEventListener('change', apply);
        offerSelect.addEventListener('change', apply);
        apply();
    }

    function initPricePreview(form) {
        var preview = form.querySelector('[data-price-preview]');

        if (!preview) {
            return;
        }

        var labels = JSON.parse(preview.dataset.labels || '{}');
        var latest = 0;

        function line(label, amount) {
            return '<dt>' + label + '</dt><dd>' + escape(amount) + '</dd>';
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
            var shown = price.display;
            var html = '<dl class="admin-price-preview__lines">';

            html += line(labels.rental, shown.base);

            if (Number(price.seasonal_adjustment) !== 0) {
                html += line(labels.seasonal, shown.seasonal);
            }

            if (Number(price.transfer_fee) > 0) {
                html += line(labels.transfer, shown.transfer);
            }

            if (Number(price.addons_total) > 0) {
                html += line(labels.addons, shown.addons);
            }

            if (Number(price.licence_fee) > 0) {
                html += line(labels.licence, shown.licence);
            }

            if (Number(price.delivery_fee) > 0) {
                html += line(labels.delivery, shown.delivery);
            }

            if (shown.offer_discount) {
                html += line(labels.offer_saving, shown.offer_discount);
            }

            html += '<dt><strong>' + labels.total + '</strong></dt><dd><strong>' + escape(shown.total) + '</strong></dd>';

            if (Number(price.deposit_amount) > 0) {
                html += line(labels.deposit, shown.deposit);
            }

            if (shown.offered_name) {
                html += line(escape(shown.offered_name), shown.offered_total);
                html += '<dt><strong>' + labels.both_total + '</strong></dt><dd><strong>' + escape(shown.both_total) + '</strong></dd>';
            }

            html += '</dl>';

            // Tuk tuks come from partners: there is no availability to report.
            if (!price.unlimited_units) {
                html += price.units_free > 0
                    ? '<p class="admin-hint">' + labels.free.replace(':free', price.units_free).replace(':total', price.units_total) + '</p>'
                    : '<p class="admin-price-preview__warn">' + labels.none + '</p>';
            }

            show(html);
        }

        var refresh = debounce(function () {
            // An airport pickup has no return date (the server sets it to
            // the pickup day), so only package and start date are needed.
            if (form.querySelector('[data-package-select]').value === ''
                || form.querySelector('#start_date').value === '') {
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
            if (event.target.matches('[name^="addons"], #hours, #transfer_time, #offer_hours')) {
                refresh();
            }
        });
        refresh();
    }

    function boot() {
        document.querySelectorAll('[data-manual-booking-form]').forEach(function (form) {
            initCustomerPicker(form);
            initAddons(form);
            initOffers(form);
            initPricePreview(form);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
