/*
 * Full-screen package photos. Any button with data-gallery (a JSON list of
 * image URLs) opens them, starting at data-gallery-start. The <dialog> is in
 * the public layout, so its labels are translated; being a real dialog it
 * also keeps keyboard focus inside and closes on Escape.
 */
(function () {
    var dialog = document.querySelector('[data-gallery-dialog]');

    if (!dialog) return;

    var image = dialog.querySelector('[data-gallery-image]');
    var caption = dialog.querySelector('[data-gallery-caption]');
    var previous = dialog.querySelector('[data-gallery-previous]');
    var next = dialog.querySelector('[data-gallery-next]');
    var photos = [];
    var title = '';
    var current = 0;
    var swipeStart = null;

    function show(index) {
        current = (index + photos.length) % photos.length;
        image.src = photos[current];
        image.alt = title;
        caption.textContent = photos.length > 1 ? title + ' · ' + (current + 1) + ' / ' + photos.length : title;

        // Ready before the customer moves on.
        if (photos.length > 1) {
            new Image().src = photos[(current + 1) % photos.length];
        }
    }

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-gallery]');

        if (!trigger) return;

        // Inside a package card's <label>, this must not also pick the package.
        event.preventDefault();

        photos = JSON.parse(trigger.getAttribute('data-gallery'));
        title = trigger.getAttribute('data-gallery-title') || '';
        previous.hidden = next.hidden = photos.length < 2;

        show(parseInt(trigger.getAttribute('data-gallery-start'), 10) || 0);
        dialog.showModal();
    });

    dialog.addEventListener('click', function (event) {
        if (event.target === previous) show(current - 1);
        else if (event.target === next) show(current + 1);
        else if (event.target !== image) dialog.close();
    });

    dialog.addEventListener('keydown', function (event) {
        if (photos.length < 2) return;
        if (event.key === 'ArrowLeft') show(current - 1);
        if (event.key === 'ArrowRight') show(current + 1);
    });

    dialog.addEventListener('touchstart', function (event) {
        swipeStart = event.changedTouches[0].clientX;
    }, { passive: true });

    dialog.addEventListener('touchend', function (event) {
        var distance = event.changedTouches[0].clientX - swipeStart;

        if (photos.length > 1 && Math.abs(distance) > 50) {
            show(current + (distance < 0 ? 1 : -1));
        }
    }, { passive: true });

    dialog.addEventListener('close', function () {
        image.removeAttribute('src');
    });
})();
