/* Progressive loading images.
 *
 * IIFE obligatoire : ce script est ré-injecté à chaque chargement de
 * panneau HUD (inventaire, banque, artisanat…) — des const au niveau
 * global casseraient à la seconde évaluation (« already declared »)
 * et les vignettes resteraient sur leur image de remplissage. */
(function () {
  const imagesToLoad = document.querySelectorAll('img[data-src]');
  const loadImages = (image) => {
    image.setAttribute('src', image.getAttribute('data-src'));
    image.onload = () => {
      image.removeAttribute('data-src');
    };
  };
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((items) => {
      items.forEach((item) => {
        if (item.isIntersecting) {
          loadImages(item.target);
          observer.unobserve(item.target);
        }
      });
    });
    imagesToLoad.forEach((img) => {
      observer.observe(img);
    });
  } else {
    imagesToLoad.forEach((img) => {
      loadImages(img);
    });
  }
})();
