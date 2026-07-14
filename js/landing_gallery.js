/* Carrousel plein écran de la galerie d'accueil (planches gravées).
 *
 * Un clic (ou Entrée) sur une planche ouvre l'image en grand sur un
 * voile d'encre, avec sa légende « Pl. N — … » ; flèches et clic sur
 * les chevrons pour naviguer, Échap / clic sur le fond / croix pour
 * fermer. Aucun asset : chevrons en bordures pivotées, comme le
 * reste de l'habillage. */
(function () {

    var $figures = $('.landing-gallery figure');
    if (!$figures.length) {
        return;
    }

    var slides = $figures.map(function () {
        return {
            /* data-full = version pleine ; la vignette est la planche
             * normalisée, plus petite */
            src: $(this).data('full') || $(this).find('img').attr('src'),
            caption: $(this).find('figcaption').text()
        };
    }).get();

    var current = 0;
    var $box = null;

    function build() {
        $box = $(
            '<div id="landing-lightbox" role="dialog" aria-modal="true" aria-label="Galerie">'
            + '<button class="lightbox-close" aria-label="Fermer">&times;</button>'
            + '<button class="lightbox-prev" aria-label="Planche précédente"></button>'
            + '<figure><img src="" alt="" /><figcaption></figcaption></figure>'
            + '<button class="lightbox-next" aria-label="Planche suivante"></button>'
            + '</div>'
        ).appendTo('body');

        $box.find('.lightbox-close').on('click', close);
        $box.find('.lightbox-prev').on('click', function () { show(current - 1); });
        $box.find('.lightbox-next').on('click', function () { show(current + 1); });

        /* Clic sur le fond = fermer (pas sur l'image ni les commandes) */
        $box.on('click', function (e) {
            if (e.target === $box[0]) {
                close();
            }
        });
    }

    function show(index) {
        current = (index + slides.length) % slides.length;
        $box.find('img').attr('src', slides[current].src).attr('alt', slides[current].caption);
        $box.find('figcaption').text(slides[current].caption);
        /* Une seule planche : pas de navigation */
        $box.find('.lightbox-prev, .lightbox-next').toggle(slides.length > 1);
    }

    function open(index) {
        if (!$box) {
            build();
        }
        show(index);
        $box.addClass('lightbox-open');
        $(document).on('keydown.landingLightbox', onKey);
        $box.find('.lightbox-close').trigger('focus');
    }

    function close() {
        $box.removeClass('lightbox-open');
        $(document).off('keydown.landingLightbox');
        $figures.eq(current).trigger('focus');
    }

    function onKey(e) {
        if (e.key === 'Escape') { close(); }
        else if (e.key === 'ArrowLeft') { show(current - 1); }
        else if (e.key === 'ArrowRight') { show(current + 1); }
    }

    $figures.each(function (i) {
        $(this).on('click', function () { open(i); });
        $(this).on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                open(i);
            }
        });
    });
})();
