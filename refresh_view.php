<?php
require_once(__DIR__.'/config.php');

\App\Service\Map\BoardChanges::viewer((int) $_SESSION['playerId']);

/* Deux appelants, deux usages : la page Profil poste en AJAX et affiche la
 * réponse, le bouton du HUD veut revenir sur un damier refait. Le retour est
 * donc demandé explicitement, pour ne rien changer à l'existant. */
if (!empty($_GET['retour'])) {
    header('Location: index.php');
    exit();
}

exit('Vue rafraichie!');
