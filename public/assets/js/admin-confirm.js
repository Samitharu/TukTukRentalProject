/*
 * "Are you sure?" before a control panel form that deletes or cancels
 * something: <form data-confirm="Remove this package?">. The site's
 * Content-Security-Policy blocks inline onsubmit="" handlers, so the
 * question lives in the attribute and this one file asks it.
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (form instanceof HTMLFormElement && form.hasAttribute('data-confirm')
            && !window.confirm(form.getAttribute('data-confirm'))) {
            event.preventDefault();
        }
    });
})();
