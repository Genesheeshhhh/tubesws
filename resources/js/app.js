/**
 * Progressive enhancement only: every page works without JavaScript, and these
 * listeners just remove a click for the interactions that get tedious.
 */

// Listing gallery: clicking a thumbnail swaps the large photo.
document.addEventListener('click', (event) => {
    const thumbnail = event.target.closest('[data-gallery-thumb]');

    if (!thumbnail) {
        return;
    }

    event.preventDefault();

    const gallery = thumbnail.closest('[data-gallery]');
    const target = thumbnail.dataset.galleryTarget;

    if (!gallery || !target) {
        return;
    }

    gallery.querySelectorAll('[data-gallery-image]').forEach((image) => {
        image.hidden = image.id !== target;
    });

    gallery.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
        button.setAttribute('aria-current', button === thumbnail ? 'true' : 'false');
        button.classList.toggle('ring-brand-600', button === thumbnail);
        button.classList.toggle('ring-transparent', button !== thumbnail);
    });
});

// Sidebar filters are a GET form, so submitting applies them; this keeps the
// facet list from jumping when a checkbox is toggled and the user would rather
// pick several before applying.
document.querySelectorAll('[data-autosubmit]').forEach((input) => {
    input.addEventListener('change', () => input.form?.requestSubmit());
});

// SPARQL editor: Ctrl/Cmd + Enter runs the query without reaching for the mouse.
document.querySelectorAll('textarea[name="query"]').forEach((editor) => {
    editor.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            editor.form?.requestSubmit();
        }
    });
});
