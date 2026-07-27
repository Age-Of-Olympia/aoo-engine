/**
 * Réglages d'interface : une étagère PAR PERSONNAGE.
 *
 * Le zoom du damier, le recentrage, les volets ouverts, la case
 * sélectionnée, le dernier évènement lu — tout cela appartient à un
 * personnage, pas à un navigateur. C'est la PERCEPTION qui fixe la taille
 * du plateau : le zoom qui cadre un nain à p=4 laisse un elfe à p=7 hors
 * champ. On changeait de personnage et on héritait des réglages du
 * précédent, sans comprendre pourquoi le damier était mal cadré.
 *
 * Les clés portent donc le personnage ACTIF — celui du tutoriel pendant un
 * tutoriel, ce qui isole aussi ses réglages de ceux du joueur réel.
 *
 * Ce qui reste hors de l'étagère : `tutorial_active`, qui dit dans quel
 * MODE on est et sert justement à savoir quel personnage est actif. Le
 * suffixer serait circulaire.
 *
 * API :
 *   aooStore.get(nom) / set(nom, valeur) / remove(nom)   — sessionStorage
 *   aooStore.getLocal(nom) / setLocal(nom, valeur)       — localStorage
 *
 * `window.aooCharacterId` est posé juste avant par le serveur (Classes\Ui).
 * Absent — page hors session, fragment isolé —, l'étagère retombe sur une
 * étagère anonyme plutôt que d'échouer : mieux vaut un réglage oublié
 * qu'une page cassée.
 */
window.aooStore = (function () {

    var id = window.aooCharacterId || 'anon';

    /* Les clés écrites avant la séparation, à adopter UNE fois pour le
     * personnage en cours — sinon chacun repartirait de zéro sans savoir
     * pourquoi. Celui qui se connecte le premier après la mise à jour les
     * récupère ; les autres démarrent propres, ce qui est le but. */
    var LEGACY_SESSION = [
        'hudDamierZoom', 'hudDamierPan', 'hudSelCoords',
        'hudTheater', 'hudTheaterChat',
        'hudPanels', 'hudPanelHistory', 'hudPanelWide',
        'hudFeedTab', 'pendingBuild'
    ];
    var LEGACY_LOCAL = ['hudEventsSeen'];

    function key(name) {
        return name + '@' + id;
    }

    function adopt(store, names) {
        names.forEach(function (name) {
            try {
                var legacy = store.getItem(name);
                if (legacy === null) {
                    return;
                }
                if (store.getItem(key(name)) === null) {
                    store.setItem(key(name), legacy);
                }
                store.removeItem(name);
            } catch (err) { /* stockage indisponible : on s'en passe */ }
        });
    }

    try {
        adopt(sessionStorage, LEGACY_SESSION);
        adopt(localStorage, LEGACY_LOCAL);
    } catch (err) { /* navigation privée stricte : pas de stockage du tout */ }

    return {
        id: id,
        key: key,

        get: function (name) {
            try { return sessionStorage.getItem(key(name)); } catch (err) { return null; }
        },
        set: function (name, value) {
            try { sessionStorage.setItem(key(name), value); } catch (err) { /* quota, mode privé */ }
        },
        remove: function (name) {
            try { sessionStorage.removeItem(key(name)); } catch (err) { /* idem */ }
        },

        getLocal: function (name) {
            try { return localStorage.getItem(key(name)); } catch (err) { return null; }
        },
        setLocal: function (name, value) {
            try { localStorage.setItem(key(name), value); } catch (err) { /* idem */ }
        }
    };
})();
