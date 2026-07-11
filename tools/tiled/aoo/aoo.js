/// <reference types="@mapeditor/tiled-api" />
/*
 * Extension Tiled ↔ Age of Olympia : pull et push des plans du jeu.
 *
 * Installation : lier ce dossier dans le répertoire d'extensions de Tiled
 * (Édition > Préférences > Plugins > Ouvrir le dossier d'extensions), puis
 * copier config.json.exemple en config.json et le remplir.
 *
 * Authentification : compte du jeu avec l'option isAdmin (lui-même ou l'un
 * de ses PNJ). L'extension demande les identifiants au premier appel et met
 * le jeton en cache dans session.json (gitignoré, expire côté serveur).
 *
 * Conventions :
 *  - un plan du jeu = une carte infinie Tiled, tuiles 50x50, avec un calque
 *    de groupe « z=N » par niveau (version d'édition portée par le groupe) ;
 *    « plan » au pull ramène tous les niveaux, « plan:z » un seul ;
 *  - le y du jeu monte vers le nord, celui de Tiled descend → tiledY = -gameY ;
 *  - couches de tuiles : tiles, routes, plants, walls, elements, foregrounds ;
 *  - couches d'objets : triggers, dialogs (params par instance) ;
 *  - map_items (état runtime) n'est jamais exporté ni importé ;
 *  - au push, les lignes posées par des joueurs (player_id, couches
 *    verrouillées « xxx (joueurs) ») sont intouchables et l'état runtime
 *    (damages, endTime) des lignes inchangées survit.
 */

var AoO = {};

/* Ordre d'empilement bas → haut, aligné sur le rendu du jeu
   (Classes/View.php) — doit rester le miroir de
   TiledMapService::AUTHORABLE_LAYERS côté serveur */
AoO.TILE_LAYERS = ['tiles', 'routes', 'plants', 'walls', 'elements', 'foregrounds'];
AoO.OBJECT_LAYERS = ['triggers', 'dialogs'];

/* Propriétés custom posées sur les cartes/couches/tuiles générées —
   source unique, une faute de frappe échouerait silencieusement */
AoO.PROP = {
    name: 'aooName',            /* tuile : nom côté jeu */
    layer: 'aooLayer',          /* tileset/couche : table map_* correspondante */
    playerBuilt: 'aooPlayerBuilt', /* couche : constructions des joueurs, hors push */
    plan: 'aooPlan',            /* carte : nom du plan */
    instance: 'aooInstance',    /* carte : instance d'origine — le push y est verrouillé */
    planPrefix: 'aooPlan_',     /* carte : propriétés du JSON de plan (aooPlan_name, aooPlan_bg…) */
    zPrefix: 'aooZ_',           /* groupe z : config du niveau (aooZ_name, aooZ_mapUnavailable, aooZ_bounds) */
    imageChoices: 'aooImageChoices', /* carte : images candidates bg/mask (info, alimenté au pull) */
    z: 'aooZ',                  /* groupe : niveau z */
    version: 'aooVersion'       /* groupe : version d'édition du niveau */
};

AoO.hasParams = function(value) {
    return value !== undefined && value !== null && String(value) !== '';
};

/* Biomes ↔ texte « wall:ressource:exhaust:regrow » (un par ligne), pour
   éditer les ressources sans écrire de JSON. */
AoO.formatBiomes = function(json) {
    var biomes;
    try {
        biomes = JSON.parse(json || '[]');
    } catch (error) {
        return String(json || ''); /* JSON cassé : montrer tel quel pour correction */
    }
    if (!Array.isArray(biomes)) {
        return '';
    }
    return biomes.map(function(b) {
        return [b.wall || '', b.ressource || '', b.exhaust || '', b.regrow || ''].join(':');
    }).join('\n');
};

AoO.parseBiomes = function(text) {
    var biomes = [];
    /* une ligne par biome ; virgules aussi acceptées (repli prompt) */
    var lines = String(text || '').split(/[\r\n,]+/);

    for (var i = 0; i < lines.length; i++) {
        var line = lines[i].trim();
        if (!line) {
            continue;
        }
        var parts = line.split(':');
        if (parts.length !== 4 || !parts[0].trim() || !parts[1].trim()
            || !/^\d+$/.test(parts[2].trim()) || !/^\d+$/.test(parts[3].trim())) {
            throw new Error('Biome mal formé (attendu « wall:ressource:exhaust:regrow ») : ' + line);
        }
        biomes.push({
            wall: parts[0].trim(),
            ressource: parts[1].trim(),
            exhaust: parseInt(parts[2].trim(), 10),
            regrow: parseInt(parts[3].trim(), 10)
        });
    }

    return biomes;
};

/*
 * Codecs params ↔ propriétés typées des objets à cas particuliers.
 * Alignés sur les consommateurs du jeu (scripts/map/triggers/*.php) et sur
 * les classes du projet aoo.tiled-project (mêmes noms de champs) :
 *  - tp : params positionnel « x,y,z,plan », un segment non numérique
 *    (ou « plan ») = conserver la valeur courante du joueur ;
 *  - need : liste libre « item:nom:n,spell:nom » — un seul champ texte ;
 *  - question (dialogs) : identifiant du dialogue.
 * Les objets sans codec gardent leur propriété « params » brute.
 */
AoO.PARAM_CODECS = {
    tp: { fields: ['x', 'y', 'z', 'plan'] },
    need: { fields: ['conditions'] },
    question: { fields: ['dialog'] }
};

/* params (chaîne) → { champ: valeur } */
AoO.decodeParams = function(codec, params) {
    var raw = AoO.hasParams(params) ? String(params) : '';
    var values = {};

    if (codec.fields.length === 1) {
        values[codec.fields[0]] = raw;
        return values;
    }

    var parts = raw.split(',');
    for (var i = 0; i < codec.fields.length; i++) {
        values[codec.fields[i]] = parts[i] !== undefined ? parts[i] : '';
    }
    return values;
};

/* objet Tiled → params (chaîne), null si aucun champ renseigné.
   resolvedProperty intègre les défauts de la classe du projet
   (un tp fraîchement créé hérite de « x », « y », « z ») */
AoO.encodeParams = function(codec, object) {
    var values = [];
    var any = false;

    for (var i = 0; i < codec.fields.length; i++) {
        var value = object.resolvedProperty
            ? object.resolvedProperty(codec.fields[i])
            : object.property(codec.fields[i]);
        if (AoO.hasParams(value)) {
            any = true;
        }
        values.push(AoO.hasParams(value) ? String(value) : '');
    }

    if (!any) {
        return null;
    }
    return codec.fields.length === 1 ? values[0] : values.join(',');
};

/*
 * Ancrage vertical des objets — convention Tiled : objet-tuile ancré en
 * bas à gauche, rectangle nu en haut à gauche. Ces deux fonctions sont
 * inverses l'une de l'autre ; toute modification doit toucher les deux.
 */
AoO.objectYFromCell = function(gameY, hasTile, tileSize) {
    return ((hasTile ? 1 : 0) - gameY) * tileSize;
};

AoO.cellYFromObject = function(object, tileSize) {
    var tiledY = Math.round(object.y / tileSize) - (object.tile ? 1 : 0);
    return -tiledY;
};

/* ------------------------------------------------------------------ */
/* Configuration et session                                            */
/* ------------------------------------------------------------------ */

AoO.readJsonFile = function(path) {
    var file = new TextFile(path, TextFile.ReadOnly);
    var data = JSON.parse(file.readAll());
    file.close();
    return data;
};

AoO.writeJsonFile = function(path, data) {
    var file = new TextFile(path, TextFile.WriteOnly);
    file.write(JSON.stringify(data, null, 2));
    file.commit();
};

AoO.configPath = function() {
    return tiled.extensionsPath + '/aoo/config.json';
};

/* Config brute ou null si le fichier n'existe pas encore (premier lancement) */
AoO.loadConfigOrNull = function() {
    try {
        return AoO.readJsonFile(AoO.configPath());
    } catch (error) {
        return null;
    }
};

AoO.loadConfig = function() {
    var config = AoO.loadConfigOrNull();
    if (!config || !config.gameDir || !config.instances) {
        throw new Error('Configuration manquante ou incomplète — Fichier → « AoO : Configuration… » ' +
            'pour renseigner le dossier du dépôt et les instances.');
    }
    return config;
};

/* Instances { nom: {baseUrl} } ↔ texte « nom=url, nom=url » du formulaire */
AoO.formatInstances = function(instances) {
    var lines = [];
    for (var name in (instances || {})) {
        lines.push(name + '=' + instances[name].baseUrl);
    }
    return lines.join(', ');
};

AoO.parseInstances = function(text) {
    var instances = {};
    var pairs = String(text || '').split(',');
    for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].trim();
        if (!pair) {
            continue;
        }
        var eq = pair.indexOf('=');
        if (eq === -1) {
            throw new Error('Instance mal formée (attendu « nom=url ») : ' + pair);
        }
        var name = pair.substring(0, eq).trim();
        var url = pair.substring(eq + 1).trim().replace(/\/+$/, '');
        if (!name || !AoO.isUrl(url)) {
            throw new Error('Instance mal formée (nom + URL http(s)) : ' + pair);
        }
        instances[name] = { baseUrl: url };
    }
    if (Object.keys(instances).length === 0) {
        throw new Error('Au moins une instance est requise (ex. local=http://localhost:9000)');
    }
    return instances;
};

AoO.sessionPath = function() {
    return tiled.extensionsPath + '/aoo/session.json';
};

/* session.json : { current: "local", instances: { local: {token, expiresAt}, ... } } */
AoO.loadSession = function() {
    try {
        var session = AoO.readJsonFile(AoO.sessionPath());
        if (session.instances) {
            return session;
        }
    } catch (error) {
        /* pas de session en cache */
    }
    return { current: null, instances: {} };
};

/* Instance active : la dernière connectée (nom déclaré ou URL ad hoc),
   sinon la première configurée */
AoO.currentInstance = function(config) {
    var session = AoO.loadSession();
    if (session.current && (config.instances[session.current] || AoO.isUrl(session.current))) {
        return session.current;
    }
    return Object.keys(config.instances)[0];
};

/* Une « instance » est soit un nom déclaré dans config.json, soit une URL
   saisie à la volée (elle est alors sa propre identité et son propre
   baseUrl) — pour viser un serveur ponctuel sans éditer config.json. */
AoO.isUrl = function(instance) {
    return /^https?:\/\//i.test(instance);
};

AoO.instanceBaseUrl = function(config, instance) {
    if (AoO.isUrl(instance)) {
        return instance.replace(/\/+$/, '');
    }
    var entry = config.instances[instance];
    if (!entry || !entry.baseUrl) {
        throw new Error('Instance inconnue dans config.json : ' + instance);
    }
    return entry.baseUrl;
};

AoO.cachedToken = function(instance) {
    var entry = AoO.loadSession().instances[instance];
    if (entry && entry.token && entry.expiresAt * 1000 > Date.now()) {
        return entry.token;
    }
    return null;
};

/* ------------------------------------------------------------------ */
/* HTTP                                                                */
/* ------------------------------------------------------------------ */

AoO.request = function(config, instance, method, path, body, token) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, AoO.instanceBaseUrl(config, instance) + path, false);
    if (token) {
        xhr.setRequestHeader('X-AoO-Tiled-Token', token);
    }
    if (body) {
        xhr.setRequestHeader('Content-Type', 'application/json');
    }
    xhr.send(body ? JSON.stringify(body) : undefined);
    return xhr;
};

/*
 * Formulaire générique : Dialog Tiled si disponible, sinon dégradation en
 * prompts successifs — champ par champ, rien n'est perdu au passage.
 * fields : [{ key, label, type: 'text'|'combo', options?, value? }]
 * Retourne { clé: valeur } ou null si annulé.
 */
AoO.showFormDialog = function(title, fields) {
    var i;

    if (typeof Dialog !== 'undefined') {
        try {
            var dialog = new Dialog(title);
            var widgets = [];

            for (i = 0; i < fields.length; i++) {
                if (i > 0) {
                    dialog.addNewRow();
                }
                if (fields[i].type === 'combo') {
                    var combo = dialog.addComboBox(fields[i].label, fields[i].options);
                    combo.currentIndex = Math.max(0, fields[i].options.indexOf(fields[i].value || ''));
                    widgets.push(combo);
                } else if (fields[i].type === 'textarea') {
                    dialog.addLabel(fields[i].label);
                    dialog.addNewRow();
                    var textEdit = dialog.addTextEdit();
                    textEdit.plainText = fields[i].value || '';
                    widgets.push(textEdit);
                } else {
                    var input = dialog.addTextInput(fields[i].label);
                    if (fields[i].value) {
                        input.text = fields[i].value;
                    }
                    widgets.push(input);
                }
            }

            dialog.addNewRow();
            var result = null;
            dialog.addButton('OK').clicked.connect(function() {
                result = {};
                for (var j = 0; j < fields.length; j++) {
                    result[fields[j].key] = fields[j].type === 'combo'
                        ? fields[j].options[widgets[j].currentIndex]
                        : (fields[j].type === 'textarea' ? widgets[j].plainText : widgets[j].text);
                }
                dialog.accept();
            });
            dialog.addButton('Annuler').clicked.connect(function() {
                dialog.reject();
            });

            dialog.exec();
            return result;
        } catch (error) {
            tiled.log('AoO : Dialog indisponible (' + error + '), repli sur les prompts');
        }
    }

    /* repli : un prompt par champ (saisie visible) */
    var values = {};
    for (i = 0; i < fields.length; i++) {
        var hint = fields[i].type === 'combo' ? '\n  ' + fields[i].options.join('\n  ') : '';
        var answer = tiled.prompt(fields[i].label + hint, fields[i].value || '', title);
        if (answer === null) {
            return null;
        }
        values[fields[i].key] = answer.trim();
    }
    return values;
};

/*
 * Formulaire de connexion. fixedInstance impose la cible (reconnexion pour
 * une carte déjà liée à son instance) ; sinon liste déroulante.
 * Retourne { instance, name, psw } ou null si annulé.
 */
AoO.CUSTOM_INSTANCE = '(adresse personnalisée…)';

AoO.showLoginDialog = function(config, fixedInstance) {
    var fields = [];
    if (!fixedInstance) {
        fields.push({
            key: 'instance', label: 'Instance :', type: 'combo',
            options: Object.keys(config.instances).concat(AoO.CUSTOM_INSTANCE),
            value: AoO.currentInstance(config)
        });
    }
    fields.push({ key: 'name', label: 'Compte admin (nom ou matricule) :', type: 'text' });
    fields.push({ key: 'psw', label: 'Mot de passe (saisie visible !) :', type: 'text' });

    var title = 'AoO — Connexion' + (fixedInstance ? ' (' + fixedInstance + ')' : '');
    var values = AoO.showFormDialog(title, fields);
    if (!values || !values.name) {
        return null;
    }

    values.instance = fixedInstance || values.instance;

    /* adresse saisie directement : demander l'URL du serveur */
    if (values.instance === AoO.CUSTOM_INSTANCE) {
        var url = tiled.prompt('Adresse du serveur (ex. https://mon-serveur.net) :', 'https://', title);
        if (!url || !AoO.isUrl(url.trim())) {
            return null;
        }
        values.instance = url.trim().replace(/\/+$/, '');
    }

    AoO.instanceBaseUrl(config, values.instance); /* rejette une instance inconnue saisie au prompt */
    return values;
};

/* Connexion à une instance ; mémorise le jeton et l'instance courante */
AoO.login = function(config, fixedInstance) {
    var credentials = AoO.showLoginDialog(config, fixedInstance);
    if (!credentials) {
        throw new Error('Connexion annulée');
    }

    var xhr = AoO.request(config, credentials.instance, 'POST', '/api/admin/map/auth.php',
        { name: credentials.name, psw: credentials.psw }, null);
    var data = JSON.parse(xhr.responseText);
    if (xhr.status !== 200 || !data.success) {
        throw new Error('Connexion refusée (' + credentials.instance + ') : ' + (data.error || 'HTTP ' + xhr.status));
    }

    var session = AoO.loadSession();
    session.current = credentials.instance;
    session.instances[credentials.instance] = { token: data.token, expiresAt: data.expiresAt };
    AoO.writeJsonFile(AoO.sessionPath(), session);

    return data.token;
};

/* Appel API authentifié sur une instance ; redemande les identifiants une fois sur 401 */
AoO.api = function(config, instance, method, path, body) {
    var token = AoO.cachedToken(instance) || AoO.login(config, instance);

    var xhr = AoO.request(config, instance, method, path, body, token);
    if (xhr.status === 401) {
        token = AoO.login(config, instance);
        xhr = AoO.request(config, instance, method, path, body, token);
    }

    var data = JSON.parse(xhr.responseText);
    if (!data.success) {
        var error = new Error('API ' + instance + ' (' + xhr.status + ') : ' + data.error);
        error.httpStatus = xhr.status;
        throw error;
    }
    return data;
};

/* ------------------------------------------------------------------ */
/* Pull : jeu → carte Tiled                                            */
/* ------------------------------------------------------------------ */

/*
 * Tilesets « collection d'images », un par couche, partagés entre les
 * niveaux z : une tuile par nom distinct, image individuelle du dépôt.
 */
AoO.layerTileset = function(map, registry, layerName) {
    var entry = registry[layerName];

    if (!entry) {
        var tileset = new Tileset('aoo-' + layerName);
        tileset.setProperty(AoO.PROP.layer, layerName);
        map.addTileset(tileset);
        entry = registry[layerName] = { tileset: tileset, byName: {} };
    }

    return entry;
};

AoO.tileFor = function(map, registry, layerName, name, images, config) {
    var entry = AoO.layerTileset(map, registry, layerName);

    var tile = entry.byName[name];
    if (!tile) {
        tile = entry.tileset.addTile();
        tile.setProperty(AoO.PROP.name, name);
        /* couches d'objets : la classe portée par la tuile de palette est
           héritée par les objets insérés depuis elle — un tp fraîchement
           posé a directement sa classe et ses champs typés */
        if (AoO.OBJECT_LAYERS.indexOf(layerName) !== -1) {
            tile.className = name;
        }
        var imagePath = images[layerName + '/' + name];
        if (imagePath) {
            tile.imageFileName = config.gameDir + '/' + imagePath;
        }
        entry.byName[name] = tile;
    }

    return tile;
};

/* Enregistre les tuiles d'une liste de lignes et retourne le dictionnaire
   nom → tuile de la couche */
AoO.registerTiles = function(map, registry, layerName, rows, images, config) {
    for (var i = 0; i < rows.length; i++) {
        AoO.tileFor(map, registry, layerName, rows[i].name, images, config);
    }
    return registry[layerName] ? registry[layerName].byName : {};
};

AoO.isPlayerRow = function(row) {
    return Boolean(row.player_id && parseInt(row.player_id, 10) !== 0);
};

AoO.buildTileLayer = function(container, layerName, rows, tiles) {
    var layer = new TileLayer(layerName);
    /* la table map_* cible vit dans une propriété : le push s'y fie, pas au
       nom affiché (renommable par l'admin) */
    layer.setProperty(AoO.PROP.layer, layerName);
    container.addLayer(layer);

    var edit = layer.edit();
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        /* y inversé : le jeu monte, Tiled descend */
        edit.setTile(row.x, -row.y, tiles[row.name]);
    }
    edit.apply();

    return layer;
};

/*
 * Les constructions des joueurs (player_id) sont montrées mais pas
 * éditables : couche verrouillée, ignorée au push (nom inconnu du
 * serveur), jamais touchée par l'import côté serveur non plus.
 */
AoO.buildSplitTileLayers = function(container, layerName, rows, tiles) {
    var authored = [];
    var playerBuilt = [];

    for (var i = 0; i < rows.length; i++) {
        (AoO.isPlayerRow(rows[i]) ? playerBuilt : authored).push(rows[i]);
    }

    AoO.buildTileLayer(container, layerName, authored, tiles);

    if (playerBuilt.length > 0) {
        var lockedLayer = AoO.buildTileLayer(container, layerName + ' (joueurs)', playerBuilt, tiles);
        lockedLayer.setProperty(AoO.PROP.layer, layerName);
        /* la propriété — pas le nom ni le verrou — protège ces lignes au
           push : déverrouiller ou renommer la couche ne les expose pas */
        lockedLayer.setProperty(AoO.PROP.playerBuilt, true);
        lockedLayer.locked = true;
    }
};

AoO.buildObjectLayer = function(container, layerName, rows, tiles, tileSize) {
    var group = new ObjectGroup(layerName);
    group.setProperty(AoO.PROP.layer, layerName);
    container.addLayer(group);

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        /* pas de nom : Tiled l'afficherait en étiquette au-dessus de chaque
           objet, redondant avec l'icône et la classe. Le push retrouve
           l'identité via la classe (serializeObjectLayer). */
        var object = new MapObject();
        var tile = tiles ? tiles[row.name] : null;

        /* la classe relie l'objet à son type du projet aoo.tiled-project
           (couleur + champs typés dans le panneau Propriétés) */
        object.className = row.name;

        object.x = row.x * tileSize;
        object.y = AoO.objectYFromCell(row.y, Boolean(tile), tileSize);
        object.width = tileSize;
        object.height = tileSize;
        if (tile) {
            object.tile = tile;
        }

        var codec = AoO.PARAM_CODECS[row.name];
        if (codec) {
            var values = AoO.decodeParams(codec, row.params);
            for (var field in values) {
                object.setProperty(field, values[field]);
            }
        } else if (AoO.hasParams(row.params)) {
            object.setProperty('params', String(row.params));
        }

        group.addObject(object);
    }
};

/*
 * Construit les couches d'un niveau z dans un conteneur (groupe).
 * Les couches vides sont créées aussi : structure identique partout,
 * et un plan neuf est peignable immédiatement.
 */
AoO.buildLevel = function(map, container, data, registry, config) {
    var i, layerName, rows, tiles;

    for (i = 0; i < AoO.TILE_LAYERS.length; i++) {
        layerName = AoO.TILE_LAYERS[i];
        rows = data.layers[layerName] || [];
        tiles = AoO.registerTiles(map, registry, layerName, rows, data.images, config);
        AoO.buildSplitTileLayers(container, layerName, rows, tiles);
    }

    for (i = 0; i < AoO.OBJECT_LAYERS.length; i++) {
        layerName = AoO.OBJECT_LAYERS[i];
        rows = data.layers[layerName] || [];
        tiles = AoO.registerTiles(map, registry, layerName, rows, data.images, config);
        AoO.buildObjectLayer(container, layerName, rows, tiles, data.tileSize);
    }
};

/*
 * Sets de terrain (autotiling) déclarés dans terrains.json : construits
 * sur les tilesets générés, ils alimentent l'onglet « Terrain Sets » et le
 * pinceau Terrain. Métadonnées d'éditeur uniquement — le jeu ne voit que
 * des noms de tuiles, le push est inchangé. Fichier optionnel.
 */
AoO.applyTerrains = function(registry, config) {
    var terrains;
    try {
        terrains = AoO.readJsonFile(config.gameDir + '/tools/tiled/aoo/terrains.json');
    } catch (error) {
        return;
    }

    var types = { corner: WangSet.Corner, edge: WangSet.Edge, mixed: WangSet.Mixed };

    for (var layerName in terrains) {
        if (layerName.charAt(0) === '_' || !registry[layerName]) {
            continue;
        }

        var cfg = terrains[layerName];
        var entry = registry[layerName];

        var wangSet = entry.tileset.addWangSet(cfg.name || 'Terrains',
            types[cfg.type] !== undefined ? types[cfg.type] : WangSet.Corner);
        wangSet.colorCount = cfg.colors.length;

        if (typeof wangSet.setColorName === 'function') {
            for (var c = 0; c < cfg.colors.length; c++) {
                wangSet.setColorName(c + 1, cfg.colors[c]);
            }
        }

        for (var name in cfg.tiles) {
            var tile = entry.byName[name];
            if (!tile) {
                continue; /* tuile absente de ce plan */
            }

            var spec = cfg.tiles[name];
            var wangId;

            if (typeof spec === 'string') {
                var color = cfg.colors.indexOf(spec) + 1;
                if (color === 0) {
                    tiled.log('AoO : couleur inconnue « ' + spec + ' » pour la tuile ' + name);
                    continue;
                }
                /* tuile pleine : bords pour un set edge, coins sinon */
                wangId = (cfg.type === 'edge')
                    ? [color, 0, color, 0, color, 0, color, 0]
                    : [0, color, 0, color, 0, color, 0, color];
            } else {
                wangId = spec;
            }

            wangSet.setWangId(tile, wangId);
        }
    }
};

/*
 * Télécharge un plan et le reconstruit : un groupe « z=N » par niveau
 * (z croissant de bas en haut de la pile → les niveaux hauts se rendent
 * au-dessus), seul le niveau le plus haut visible au départ. zSpec
 * restreint à un seul niveau.
 */
AoO.pull = function(plan, zSpec, config) {
    config = config || AoO.loadConfig();
    var instance = AoO.currentInstance(config);

    var exportLevel = function(z) {
        return AoO.api(config, instance, 'GET', '/api/admin/map/export.php?plan=' + plan + '&z=' + z);
    };

    var singleZ = (zSpec !== undefined && zSpec !== null);
    var firstZ = singleZ ? zSpec : 0;
    var firstData = exportLevel(firstZ);

    var zLevels = singleZ ? [zSpec] : (firstData.zLevels || [0]);
    zLevels.sort(function(a, b) { return a - b; });
    var topZ = zLevels[zLevels.length - 1];

    var map = new TileMap();
    map.orientation = TileMap.Orthogonal;
    map.setTileSize(firstData.tileSize, firstData.tileSize);
    map.infinite = true;
    map.setProperty(AoO.PROP.plan, plan);
    map.setProperty(AoO.PROP.instance, instance);

    /* propriétés du JSON de plan, éditables directement dans le panneau
       Propriétés de la carte ('' = clé absente), réécrites au push */
    if (firstData.planConfig) {
        for (var configKey in firstData.planConfig.values) {
            map.setProperty(AoO.PROP.planPrefix + configKey, firstData.planConfig.values[configKey]);
        }
        map.setProperty(AoO.PROP.imageChoices, JSON.stringify(firstData.planConfig.bgChoices || []));
    }

    var registry = {};

    for (var i = 0; i < zLevels.length; i++) {
        var z = zLevels[i];
        var data = (z === firstZ) ? firstData : exportLevel(z);

        var group = new GroupLayer('z=' + z);
        map.addLayer(group);
        group.setProperty(AoO.PROP.z, z);
        group.setProperty(AoO.PROP.version, data.version);
        group.visible = (z === topZ);

        /* config du niveau (nom, MapUnavailable, bornes) éditable dans les
           propriétés du groupe ; « bounds » = auto (recalculé) ou
           « minX,maxX,minY,maxY » */
        if (data.zConfig) {
            for (var zKey in data.zConfig) {
                group.setProperty(AoO.PROP.zPrefix + zKey, data.zConfig[zKey]);
            }
        }

        AoO.buildLevel(map, group, data, registry, config);
    }

    AoO.addCatalogTiles(map, firstData, registry, config);
    AoO.addCompositeTiles(map, firstData, registry, config);
    AoO.applyTerrains(registry, config);
    AoO.applyBackgroundPreview(map, config);

    return map;
};

/*
 * Aperçu du fond et du masque dans l'éditeur : calques image verrouillés,
 * purement visuels (aucune couche AoO, donc ignorés au push). Le fond en
 * bas de la pile, le masque en haut (semi-transparent), tous deux répétés
 * comme le fait le jeu. Reconstruit à chaque appel — utilisé au pull et
 * après un changement via l'action « Fond / ambiance ».
 */
AoO.applyBackgroundPreview = function(map, config) {
    /* retire les aperçus existants (property aooPreview) */
    for (var i = map.layerCount - 1; i >= 0; i--) {
        if (map.layerAt(i).property('aooPreview')) {
            map.removeLayerAt(i);
        }
    }

    var addPreview = function(image, top, opacity) {
        if (!image) {
            return;
        }
        var layer = new ImageLayer(top ? 'masque (aperçu)' : 'fond (aperçu)');
        layer.imageFileName = config.gameDir + '/' + image;
        layer.repeatX = true;
        layer.repeatY = true;
        layer.opacity = opacity;
        layer.locked = true;
        layer.setProperty('aooPreview', true);
        if (top) {
            map.addLayer(layer);              /* au-dessus de tout */
        } else {
            map.insertLayerAt(0, layer);      /* sous tout */
        }
    };

    addPreview(String(map.property(AoO.PROP.planPrefix + 'bg') || ''), false, 1);
    addPreview(String(map.property(AoO.PROP.planPrefix + 'mask') || ''), true, 0.5);
};

/*
 * Complète les tilesets avec tout le catalogue d'images du jeu (pas
 * seulement les tuiles déjà posées sur ce plan) : indispensable pour
 * poser de nouveaux types de tuiles ou remplir un plan neuf.
 */
AoO.addCatalogTiles = function(map, data, registry, config) {
    if (!data.catalog) {
        return;
    }

    /* les morceaux des structures (olympia-00…) n'encombrent pas la
       palette : on pose la structure entière (addCompositeTiles) ; ils ne
       réapparaissent que si le plan pullé en contient déjà (buildLevel) */
    var pieceNames = {};
    for (var compositeLayer in (data.composites || {})) {
        var entries = data.composites[compositeLayer];
        for (var e = 0; e < entries.length; e++) {
            for (var p = 0; p < entries[e].pieces.length; p++) {
                pieceNames[compositeLayer + '/' + entries[e].pieces[p].name] = true;
            }
        }
    }

    for (var layerName in data.catalog) {
        var names = data.catalog[layerName];
        for (var i = 0; i < names.length; i++) {
            if (!pieceNames[layerName + '/' + names[i]]) {
                AoO.tileFor(map, registry, layerName, names[i], data.images, config);
            }
        }
    }
};

/*
 * Structures multi-tuiles : une grande tuile de palette par structure
 * (image entière, ancrée en bas à gauche de sa case), que le push
 * ré-éclate en morceaux « base-NN » individuels — voir
 * AoO.expandComposite. Un pull ultérieur ré-affiche les morceaux, ce qui
 * revient au même visuellement.
 */
AoO.addCompositeTiles = function(map, data, registry, config) {
    if (!data.composites) {
        return;
    }
    for (var layerName in data.composites) {
        var entries = data.composites[layerName];
        for (var i = 0; i < entries.length; i++) {
            var composite = entries[i];
            var entry = AoO.layerTileset(map, registry, layerName);

            var tile = entry.tileset.addTile();
            tile.imageFileName = config.gameDir + '/' + composite.image;
            tile.setProperty('aooComposite', JSON.stringify({
                width: composite.width,
                height: composite.height,
                pieces: composite.pieces
            }));
        }
    }
};

/*
 * Éclate une grande tuile de structure posée en (x, y) Tiled (= sa case
 * d'ancrage, en bas à gauche) en morceaux individuels. Les offsets
 * (dx, dy, relatifs à l'ancre, en coordonnées jeu) sont fournis par le
 * serveur : aucune convention de découpe à connaître ici.
 */
AoO.expandComposite = function(composite, tiledX, tiledY, rows) {
    var anchorGameY = -tiledY;

    for (var i = 0; i < composite.pieces.length; i++) {
        var piece = composite.pieces[i];
        rows.push({
            x: tiledX + piece.dx,
            y: anchorGameY + piece.dy,
            name: piece.name
        });
    }
};

/* Pull + sauvegarde en .tmj ; utilisable sans éditeur (tests headless) */
AoO.pullAndSave = function(plan, zSpec) {
    var config = AoO.loadConfig();
    var map = AoO.pull(plan, zSpec, config);

    /* un dossier par instance : gaia de test n'écrase pas gaia locale */
    var directory = config.gameDir + '/tools/tiled/maps/' + map.property(AoO.PROP.instance);
    var suffix = (zSpec !== undefined && zSpec !== null) ? ('_z' + zSpec) : '';
    var fileName = directory + '/' + plan + suffix + '.tmj';
    File.makePath(directory);
    var format = tiled.mapFormat('json');
    format.write(map, fileName);

    return fileName;
};

AoO.pullAndOpen = function(plan, zSpec) {
    var fileName = AoO.pullAndSave(plan, zSpec);
    tiled.open(fileName);
    return fileName;
};

/* ------------------------------------------------------------------ */
/* Push : carte Tiled → jeu                                            */
/* ------------------------------------------------------------------ */

AoO.serializeTileLayer = function(layer) {
    var rows = [];
    var rect = layer.region().boundingRect;

    for (var y = rect.y; y < rect.y + rect.height; y++) {
        for (var x = rect.x; x < rect.x + rect.width; x++) {
            var tile = layer.tileAt(x, y);
            if (!tile) {
                continue;
            }

            var compositeSpec = tile.property('aooComposite');
            if (compositeSpec) {
                AoO.expandComposite(JSON.parse(compositeSpec), x, y, rows);
                continue;
            }

            var name = tile.property(AoO.PROP.name);
            if (!name) {
                throw new Error('Tuile sans propriété ' + AoO.PROP.name + ' en ' + x + ',' + (-y) +
                    ' (couche ' + layer.name + ') — utiliser les tuiles des tilesets aoo-*');
            }
            rows.push({ x: x, y: -y, name: name });
        }
    }

    return rows;
};

AoO.serializeObjectLayer = function(layer, tileSize) {
    var rows = [];

    for (var i = 0; i < layer.objectCount; i++) {
        var object = layer.objectAt(i);

        /* nom : explicite, sinon tuile, sinon classe (objet créé à la main
           en choisissant juste la classe du projet) */
        var name = object.name;
        if (!name && object.tile) {
            name = object.tile.property(AoO.PROP.name);
        }
        if (!name) {
            name = object.className;
        }
        if (!name) {
            throw new Error('Objet sans nom ni classe dans la couche ' + layer.name);
        }

        var row = {
            x: Math.round(object.x / tileSize),
            y: AoO.cellYFromObject(object, tileSize),
            name: name
        };

        var codec = AoO.PARAM_CODECS[name];
        var params = codec ? AoO.encodeParams(codec, object) : object.property('params');
        if (AoO.hasParams(params)) {
            row.params = String(params);
        }

        rows.push(row);
    }

    return rows;
};

/*
 * Sérialise les couches AoO d'un conteneur (groupe de niveau). La table
 * cible vient de la propriété aooLayer (posée au pull), avec repli sur le
 * nom pour les couches créées à la main ; les couches marquées
 * aooPlayerBuilt (constructions des joueurs) ne sont jamais poussées.
 */
AoO.serializeContainer = function(container, tileSize) {
    var layers = {};

    for (var i = 0; i < container.layerCount; i++) {
        var layer = container.layerAt(i);
        var target = layer.property(AoO.PROP.layer) || layer.name;

        if (layer.property(AoO.PROP.playerBuilt)) {
            continue;
        }

        if (layer.isTileLayer && AoO.TILE_LAYERS.indexOf(target) !== -1) {
            layers[target] = AoO.serializeTileLayer(layer);
        } else if (layer.isObjectLayer && AoO.OBJECT_LAYERS.indexOf(target) !== -1) {
            layers[target] = AoO.serializeObjectLayer(layer, tileSize);
        } else {
            tiled.log('AoO : couche « ' + layer.name + ' » ignorée au push');
        }
    }

    return layers;
};

/* Propriétés préfixées d'un objet (carte ou groupe) → { clé: valeur } sans
   le préfixe, ou null si aucune */
AoO.collectPrefixed = function(holder, prefix) {
    var collected = {};
    var found = false;
    var properties = holder.properties();

    for (var name in properties) {
        if (name.indexOf(prefix) === 0) {
            collected[name.substring(prefix.length)] = String(properties[name]);
            found = true;
        }
    }

    return found ? collected : null;
};

/* Instance cible d'un push : celle d'origine de la carte, sinon la
   courante — et rejetée si elle a disparu de config.json. Utilisée à la
   fois par le push et par sa confirmation, pour qu'ils ne divergent pas. */
AoO.pushInstance = function(map, config) {
    var instance = map.property(AoO.PROP.instance) || AoO.currentInstance(config);
    AoO.instanceBaseUrl(config, instance);
    return instance;
};

/*
 * Pousse la carte vers le jeu, un import transactionnel par niveau z.
 * Retourne [{z, layers}] ; en cas d'échec sur un niveau, les niveaux
 * déjà appliqués le restent (leurs versions locales sont à jour).
 */
AoO.push = function(map) {
    if (!map || !map.property(AoO.PROP.plan)) {
        throw new Error('Cette carte ne vient pas du jeu (propriété ' + AoO.PROP.plan + ' absente) — faire un pull d\'abord');
    }

    var config = AoO.loadConfig();
    var plan = map.property(AoO.PROP.plan);
    /* le push est verrouillé sur l'instance d'origine de la carte : une
       carte pullée de test ne peut pas partir vers prod par accident */
    var instance = AoO.pushInstance(map, config);
    var planConfigSent = false;
    var reports = [];

    for (var i = 0; i < map.layerCount; i++) {
        var group = map.layerAt(i);
        if (!group.isGroupLayer || group.property(AoO.PROP.z) === undefined) {
            continue;
        }

        var z = group.property(AoO.PROP.z);
        var payload = {
            plan: plan,
            z: z,
            version: group.property(AoO.PROP.version),
            layers: AoO.serializeContainer(group, map.tileWidth),
            zConfig: AoO.collectPrefixed(group, AoO.PROP.zPrefix) /* config du niveau */
        };

        /* les propriétés du plan sont globales : envoyées une seule fois */
        if (!planConfigSent) {
            var planConfig = AoO.collectPrefixed(map, AoO.PROP.planPrefix);
            if (planConfig) {
                payload.planConfig = planConfig;
            }
            planConfigSent = true;
        }

        try {
            var data = AoO.api(config, instance, 'POST', '/api/admin/map/import.php', payload);
        } catch (error) {
            error.message = 'z=' + z + ' : ' + error.message +
                (reports.length ? '\n(niveaux déjà appliqués : ' +
                    reports.map(function(r) { return 'z=' + r.z; }).join(', ') + ')' : '');
            throw error;
        }

        group.setProperty(AoO.PROP.version, data.newVersion);
        reports.push({ z: z, layers: data.layers, planHealth: data.planHealth });
    }

    if (reports.length === 0) {
        throw new Error('Aucun groupe « z=N » trouvé dans cette carte — re-puller le plan (ancien format ?)');
    }

    return reports;
};

AoO.formatReport = function(reports) {
    var lines = [];
    var health = null;

    for (var i = 0; i < reports.length; i++) {
        var report = reports[i];
        for (var layer in report.layers) {
            var r = report.layers[layer];
            if (r.inserted || r.deleted || r.kept || r.protected) {
                lines.push('z=' + report.z + ' ' + layer + ' : +' + r.inserted + ' / -' + r.deleted +
                    ' / ' + r.kept + ' conservés' +
                    (r.protected ? ' / ' + r.protected + ' protégés (joueurs)' : ''));
            }
        }
        if (report.planHealth) {
            health = report.planHealth; /* même bilan à chaque niveau : garder le dernier */
        }
    }

    var text = lines.length ? lines.join('\n') : 'Aucun changement.';

    if (health) {
        var issues = (health.errors || []).concat(health.warnings || []);
        if (issues.length) {
            text += '\n\n⚠ Santé du JSON de plan :\n' + issues.join('\n');
        }
    }

    return text;
};

/* ------------------------------------------------------------------ */
/* Actions et menus                                                    */
/* ------------------------------------------------------------------ */

/*
 * Génère un monde Tiled (<instance>.world) : chaque plan pullé, positionné
 * par sa case (x, y) sur la carte du monde ; les plans hors grille (donjons
 * atteints seulement par un tp) sont placés sous leur plan d'entrée, ou dans
 * une rangée « orphelins » à défaut. Purement éditeur — la disposition est
 * une vue d'ensemble, chaque plan gardant son origine locale.
 */
AoO.generateWorld = function(config, instance) {
    var data = AoO.api(config, instance, 'GET', '/api/admin/map/world.php');
    var plans = data.plans;
    var tileSize = data.tileSize;
    var names = Object.keys(plans);

    /* étendue (en tuiles) d'un plan sur son niveau de référence */
    var extentOf = function(plan) {
        var zs = plans[plan].zLevels || [];
        var b = plans[plan].bounds ? (plans[plan].bounds[0] || plans[plan].bounds[zs[0]]) : null;
        return b ? { w: b.maxX - b.minX + 1, h: b.maxY - b.minY + 1, minX: b.minX, maxY: b.maxY }
                 : { w: 1, h: 1, minX: 0, maxY: 0 };
    };

    /* pas d'une case : plus grand plan + marge, pour ne pas chevaucher */
    var maxSpan = 1;
    for (var n = 0; n < names.length; n++) {
        var e = extentOf(names[n]);
        maxSpan = Math.max(maxSpan, e.w, e.h);
    }
    var cell = (maxSpan + 6) * tileSize;

    var occupied = {};       /* "col,row" → true */
    var placement = {};      /* plan → {col, row} */

    var place = function(plan, col, row) {
        while (occupied[col + ',' + row]) {
            row++;           /* case prise : descendre */
        }
        occupied[col + ',' + row] = true;
        placement[plan] = { col: col, row: row };
    };

    /* 1. plans sur la grille olympia (x/y présents) — y inversé (nord en haut) */
    var maxRow = 0;
    for (var i = 0; i < names.length; i++) {
        var p = plans[names[i]];
        if (p.x !== null && p.y !== null) {
            place(names[i], p.x, -p.y);
            maxRow = Math.max(maxRow, -p.y);
        }
    }

    /* 2. plans hors grille : sous un plan d'entrée qui les vise par tp */
    var orphanCol = 0;
    for (var j = 0; j < names.length; j++) {
        var name = names[j];
        if (placement[name]) {
            continue;
        }
        var source = null;
        for (var s = 0; s < names.length; s++) {
            if (placement[names[s]] && (plans[names[s]].links || []).indexOf(name) !== -1) {
                source = names[s];
                break;
            }
        }
        if (source) {
            place(name, placement[source].col, placement[source].row + 1);
        } else {
            place(name, orphanCol++, maxRow + 2); /* rangée orphelins */
        }
    }

    /* 3. pull de chaque plan + entrée .world (offset pixel = case − contenu local) */
    var maps = [];
    for (var k = 0; k < names.length; k++) {
        var plan = names[k];
        var ext = extentOf(plan);
        var cellX = placement[plan].col * cell;
        var cellY = placement[plan].row * cell;

        AoO.pullAndSave(plan); /* garantit maps/<instance>/<plan>.tmj */

        maps.push({
            fileName: plan + '.tmj',
            /* aligne le coin haut-gauche du contenu sur la case */
            x: Math.round(cellX - ext.minX * tileSize),
            y: Math.round(cellY + ext.maxY * tileSize)
        });
    }

    var world = { maps: maps, onlyShowAdjacentMaps: false, type: 'world' };
    var fileName = config.gameDir + '/tools/tiled/maps/' + instance + '/' + instance + '.world';
    AoO.writeJsonFile(fileName, world);

    return { fileName: fileName, count: maps.length };
};

/* Liste « plan (z...) » des plans existants, pour guider le pull */
AoO.describePlans = function(config, instance) {
    var data = AoO.api(config, instance, 'GET', '/api/admin/map/plans.php');

    var lines = [];
    for (var plan in data.plans) {
        lines.push(plan + ' (z ' + data.plans[plan].zLevels.join(', ') + ')');
    }
    return lines;
};

/* registerAction + garde d'erreur uniforme : toute exception du handler
   devient une alerte au lieu d'un échec silencieux */
AoO.registerSafeAction = function(id, text, title, handler) {
    tiled.registerAction(id, function() {
        try {
            handler();
        } catch (error) {
            tiled.alert(String(error), title);
        }
    }).text = text;
};

AoO.registerSafeAction('AoOConfig', 'AoO : Configuration…', 'AoO — Configuration', function() {
    var config = AoO.loadConfigOrNull() || {};

    var values = AoO.showFormDialog('AoO — Configuration', [
        {
            key: 'gameDir', type: 'text',
            label: 'Dossier du dépôt (chemin absolu) :',
            value: config.gameDir || ''
        },
        {
            key: 'instances', type: 'text',
            label: 'Instances (nom=url, séparées par des virgules) :',
            value: AoO.formatInstances(config.instances) ||
                'local=http://localhost:9000, test=https://test.age-of-olympia.net'
        }
    ]);
    if (!values) {
        return;
    }

    var gameDir = String(values.gameDir).trim().replace(/\/+$/, '');
    if (!gameDir) {
        throw new Error('Le dossier du dépôt est requis.');
    }

    config.gameDir = gameDir;
    config.instances = AoO.parseInstances(values.instances); /* valide le format */
    AoO.writeJsonFile(AoO.configPath(), config);

    tiled.alert('Configuration enregistrée.\n\nInstances : ' +
        Object.keys(config.instances).join(', '), 'AoO — Configuration');
});

AoO.registerSafeAction('AoOConnect', 'AoO : Connexion / changer d\'instance…', 'AoO — Connexion', function() {
    var config = AoO.loadConfig();
    AoO.login(config, null);
    var instance = AoO.currentInstance(config);
    tiled.alert('Connecté à « ' + instance + ' » (' + AoO.instanceBaseUrl(config, instance) + ').\n\n' +
        'Les pulls et créations de plan visent désormais cette instance.', 'AoO — Connexion');
});

AoO.registerSafeAction('AoOPull', 'AoO : Pull un plan du jeu…', 'AoO — Pull', function() {
    var config = AoO.loadConfig();
    var instance = AoO.currentInstance(config);
    var plans = AoO.describePlans(config, instance);

    var spec = tiled.prompt(
        'Instance : ' + instance + ' (' + AoO.instanceBaseUrl(config, instance) + ')\n\n' +
        'Plans existants :\n  ' + plans.join('\n  ') +
        '\n\nPlan à récupérer (« plan » = tous les niveaux, « plan:z » = un seul) :',
        'gaia', 'AoO — Pull');
    if (!spec) {
        return;
    }
    var parts = spec.split(':');
    var plan = parts[0].trim();
    var z = parts.length > 1 ? (parseInt(parts[1], 10) || 0) : null;

    var fileName = AoO.pullAndOpen(plan, z);
    tiled.log('AoO : plan « ' + plan + ' » ouvert depuis ' + fileName);
});

AoO.registerSafeAction('AoOBg', 'AoO : Fond / ambiance du plan…', 'AoO — Fond du plan', function() {
    var map = tiled.activeAsset;
    if (!map || !map.isTileMap || !map.property(AoO.PROP.plan)) {
        throw new Error('Ouvrir d\'abord une carte pullée du jeu.');
    }

    var choices = JSON.parse(String(map.property(AoO.PROP.imageChoices) || '[]'));
    var NONE = '(aucun / défaut)';
    var options = [NONE].concat(choices);
    var propBg = AoO.PROP.planPrefix + 'bg';
    var propMask = AoO.PROP.planPrefix + 'mask';

    var values = AoO.showFormDialog('AoO — Fond / ambiance du plan', [
        { key: 'bg', label: 'Fond (bg) :', type: 'combo', options: options, value: String(map.property(propBg) || '') || NONE },
        { key: 'mask', label: 'Masque animé (mask) :', type: 'combo', options: options, value: String(map.property(propMask) || '') || NONE }
    ]);
    if (!values) {
        return;
    }

    map.setProperty(propBg, values.bg === NONE ? '' : values.bg);
    map.setProperty(propMask, values.mask === NONE ? '' : values.mask);
    AoO.applyBackgroundPreview(map, AoO.loadConfig()); /* rafraîchit l'aperçu */
    tiled.log('AoO : fond/masque du plan mis à jour — appliqué au prochain push ' +
        '(vitesse du masque : propriété ' + AoO.PROP.planPrefix + 'scrollingMask)');
});

AoO.registerSafeAction('AoOWorld', 'AoO : Générer le monde (tous les plans)…', 'AoO — Monde', function() {
    var config = AoO.loadConfig();
    var instance = AoO.currentInstance(config);

    if (typeof tiled.confirm === 'function' &&
        !tiled.confirm('Pull de tous les plans de « ' + instance + ' » et génération du monde ?\n\n' +
            'Peut prendre un moment selon le nombre de plans.', 'AoO — Monde')) {
        return;
    }

    var result = AoO.generateWorld(config, instance);
    tiled.alert(result.count + ' plans positionnés.\n\nMonde écrit : ' + result.fileName +
        '\n\nOuvrir via le menu Carte → « Charger le monde… » (Load World).', 'AoO — Monde');
});

AoO.registerSafeAction('AoOBiomes', 'AoO : Biomes (ressources) du plan…', 'AoO — Biomes', function() {
    var map = tiled.activeAsset;
    if (!map || !map.isTileMap || !map.property(AoO.PROP.plan)) {
        throw new Error('Ouvrir d\'abord une carte pullée du jeu.');
    }

    var propBiomes = AoO.PROP.planPrefix + 'biomes';
    var current = AoO.formatBiomes(String(map.property(propBiomes) || ''));

    var values = AoO.showFormDialog('AoO — Biomes (ressources) du plan', [
        {
            key: 'biomes', type: 'textarea',
            label: 'Un biome par ligne : wall:ressource:exhaust:regrow\n' +
                '(ex. arbre1:bois:75:20 — mur récoltable, item obtenu, % épuisement, tours de repousse)',
            value: current
        }
    ]);
    if (!values) {
        return;
    }

    var biomes = AoO.parseBiomes(values.biomes); /* valide le format */
    map.setProperty(propBiomes, biomes.length ? JSON.stringify(biomes) : '');
    tiled.log('AoO : ' + biomes.length + ' biome(s) — appliqué au prochain push');
});

AoO.registerSafeAction('AoONew', 'AoO : Nouveau plan dans le jeu…', 'AoO — Nouveau plan', function() {
    var config = AoO.loadConfig();
    var instance = AoO.currentInstance(config);
    var plan = tiled.prompt('Nom du nouveau plan sur « ' + instance + ' » (minuscules, chiffres, _ et -) :',
        '', 'AoO — Nouveau plan');
    if (!plan) {
        return;
    }
    plan = plan.trim();

    AoO.api(config, instance, 'POST', '/api/admin/map/create.php', { plan: plan });

    /* le plan vierge existe : le pull ramène une carte vide avec la
       palette complète (catalogue) prête à peindre */
    var fileName = AoO.pullAndOpen(plan);
    tiled.log('AoO : plan « ' + plan + ' » créé et ouvert depuis ' + fileName);
});

AoO.registerSafeAction('AoOPush', 'AoO : Push la carte vers le jeu…', 'AoO — Push', function() {
    var map = tiled.activeAsset;
    if (!map || !map.isTileMap) {
        throw new Error('Ouvrir d\'abord une carte pullée du jeu.');
    }

    /* carte hors jeu : AoO.push lèvera l'erreur explicite, pas de confirm */
    var plan = map.property(AoO.PROP.plan);

    if (plan) {
        var instance = AoO.pushInstance(map, AoO.loadConfig());
        var question = 'Pousser « ' + plan + ' » vers l\'instance « ' + instance + ' » ' +
            '(tous les niveaux z de la carte) ?\n\n' +
            'Les cases construites par des joueurs et les objets au sol ne seront pas touchés.';
        if (typeof tiled.confirm === 'function' && !tiled.confirm(question, 'AoO — Push')) {
            return;
        }
    }

    var reports = AoO.push(map);
    tiled.alert('Plan appliqué au jeu.\n\n' + AoO.formatReport(reports), 'AoO — Push');
});

/* Menu Fichier : toujours visible, contrairement au menu Carte qui
   n'existe que lorsqu'une carte est ouverte. En mode --evaluate
   (headless), les menus n'existent pas du tout. */
try {
    tiled.extendMenu('File', [
        { separator: true },
        { action: 'AoOConfig' },
        { action: 'AoOConnect' },
        { action: 'AoOPull' },
        { action: 'AoOPush' },
        { action: 'AoONew' },
        { action: 'AoOBg' },
        { action: 'AoOBiomes' },
        { action: 'AoOWorld' }
    ]);
    tiled.log('AoO : extension chargée, actions dans le menu Fichier');
} catch (error) {
    tiled.log('AoO : menus indisponibles (mode headless) — ' + error);
}
