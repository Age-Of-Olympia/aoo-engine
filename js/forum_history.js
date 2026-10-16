/* Previous posts under a reply or edit form: each "older" button swaps
 * itself for the next chunk, which brings its own button while posts remain.
 * Fragment scripts re-run on every panel load, hence off() before on(). */
$(document).off('click.forumHistory').on('click.forumHistory', '.forum-history-more', function () {
    const $button = $(this).prop('disabled', true);

    $.get($button.data('url'))
        .done(function (html) { $button.replaceWith(html); })
        .fail(function () { $button.prop('disabled', false); });
});
