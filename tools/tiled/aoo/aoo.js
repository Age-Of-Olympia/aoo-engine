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
    z: 'aooZ',                  /* groupe : niveau z */
    version: 'aooVersion'       /* groupe : version d'édition du niveau */
};

AoO.hasParams = function(value) {
    return value !== undefined && value !== null && String(value) !== '';
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

AoO.loadConfig = function() {
    var config = AoO.readJsonFile(tiled.extensionsPath + '/aoo/config.json');
    if (!config.baseUrl || !config.gameDir) {
        throw new Error('config.json incomplet : baseUrl et gameDir sont requis');
    }
    return config;
};

AoO.sessionPath = function() {
    return tiled.extensionsPath + '/aoo/session.json';
};

AoO.cachedToken = function() {
    try {
        var session = AoO.readJsonFile(AoO.sessionPath());
        if (session.token && session.expiresAt * 1000 > Date.now()) {
            return session.token;
        }
    } catch (error) {
        /* pas de session en cache */
    }
    return null;
};

/* ------------------------------------------------------------------ */
/* HTTP                                                                */
/* ------------------------------------------------------------------ */

AoO.request = function(config, method, path, body, token) {
    var xhr = new XMLHttpRequest();
    xhr.open(method, config.baseUrl + path, false);
    if (token) {
        xhr.setRequestHeader('X-AoO-Tiled-Token', token);
    }
    if (body) {
        xhr.setRequestHeader('Content-Type', 'application/json');
    }
    xhr.send(body ? JSON.stringify(body) : undefined);
    return xhr;
};

AoO.login = function(config) {
    var name = tiled.prompt('Compte admin du jeu (nom ou matricule) :', '', 'AoO — Connexion');
    if (!name) {
        throw new Error('Connexion annulée');
    }
    /* tiled.prompt ne masque pas la saisie : outil admin local uniquement */
    var password = tiled.prompt('Mot de passe (saisie visible !) :', '', 'AoO — Connexion');
    if (password === null) {
        throw new Error('Connexion annulée');
    }

    var xhr = AoO.request(config, 'POST', '/api/admin/map/auth.php', { name: name, psw: password }, null);
    var data = JSON.parse(xhr.responseText);
    if (xhr.status !== 200 || !data.success) {
        throw new Error('Connexion refusée : ' + (data.error || 'HTTP ' + xhr.status));
    }

    AoO.writeJsonFile(AoO.sessionPath(), { token: data.token, expiresAt: data.expiresAt });
    return data.token;
};

/* Appel API authentifié ; redemande les identifiants une fois sur 401 */
AoO.api = function(config, method, path, body) {
    var token = AoO.cachedToken() || AoO.login(config);

    var xhr = AoO.request(config, method, path, body, token);
    if (xhr.status === 401) {
        token = AoO.login(config);
        xhr = AoO.request(config, method, path, body, token);
    }

    var data = JSON.parse(xhr.responseText);
    if (!data.success) {
        var error = new Error('API (' + xhr.status + ') : ' + data.error);
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
AoO.tileFor = function(map, registry, layerName, name, images, config) {
    var entry = registry[layerName];

    if (!entry) {
        var tileset = new Tileset('aoo-' + layerName);
        tileset.setProperty(AoO.PROP.layer, layerName);
        map.addTileset(tileset);
        entry = registry[layerName] = { tileset: tileset, byName: {} };
    }

    var tile = entry.byName[name];
    if (!tile) {
        tile = entry.tileset.addTile();
        tile.setProperty(AoO.PROP.name, name);
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

    if (authored.length > 0 || playerBuilt.length === 0) {
        AoO.buildTileLayer(container, layerName, authored, tiles);
    }

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
        var object = new MapObject(row.name);
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

    var exportLevel = function(z) {
        return AoO.api(config, 'GET', '/api/admin/map/export.php?plan=' + plan + '&z=' + z);
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

    var registry = {};

    for (var i = 0; i < zLevels.length; i++) {
        var z = zLevels[i];
        var data = (z === firstZ) ? firstData : exportLevel(z);

        var group = new GroupLayer('z=' + z);
        map.addLayer(group);
        group.setProperty(AoO.PROP.z, z);
        group.setProperty(AoO.PROP.version, data.version);
        group.visible = (z === topZ);

        AoO.buildLevel(map, group, data, registry, config);
    }

    AoO.addCatalogTiles(map, firstData, registry, config);
    AoO.applyTerrains(registry, config);

    return map;
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
    for (var layerName in data.catalog) {
        var names = data.catalog[layerName];
        for (var i = 0; i < names.length; i++) {
            AoO.tileFor(map, registry, layerName, names[i], data.images, config);
        }
    }
};

/* Pull + sauvegarde en .tmj ; utilisable sans éditeur (tests headless) */
AoO.pullAndSave = function(plan, zSpec) {
    var config = AoO.loadConfig();
    var map = AoO.pull(plan, zSpec, config);

    var suffix = (zSpec !== undefined && zSpec !== null) ? ('_z' + zSpec) : '';
    var fileName = config.gameDir + '/tools/tiled/maps/' + plan + suffix + '.tmj';
    File.makePath(config.gameDir + '/tools/tiled/maps');
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
            layers: AoO.serializeContainer(group, map.tileWidth)
        };

        try {
            var data = AoO.api(config, 'POST', '/api/admin/map/import.php', payload);
        } catch (error) {
            error.message = 'z=' + z + ' : ' + error.message +
                (reports.length ? '\n(niveaux déjà appliqués : ' +
                    reports.map(function(r) { return 'z=' + r.z; }).join(', ') + ')' : '');
            throw error;
        }

        group.setProperty(AoO.PROP.version, data.newVersion);
        reports.push({ z: z, layers: data.layers });
    }

    if (reports.length === 0) {
        throw new Error('Aucun groupe « z=N » trouvé dans cette carte — re-puller le plan (ancien format ?)');
    }

    return reports;
};

AoO.formatReport = function(reports) {
    var lines = [];

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
    }

    return lines.length ? lines.join('\n') : 'Aucun changement.';
};

/* ------------------------------------------------------------------ */
/* Actions et menus                                                    */
/* ------------------------------------------------------------------ */

/* Liste « plan (z...) » des plans existants, pour guider le pull */
AoO.describePlans = function(config) {
    var data = AoO.api(config, 'GET', '/api/admin/map/plans.php');

    var lines = [];
    for (var plan in data.plans) {
        lines.push(plan + ' (z ' + data.plans[plan].zLevels.join(', ') + ')');
    }
    return lines;
};

tiled.registerAction('AoOPull', function() {
    try {
        var config = AoO.loadConfig();
        var plans = AoO.describePlans(config);

        var spec = tiled.prompt(
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
    } catch (error) {
        tiled.alert(String(error), 'AoO — Pull');
    }
}).text = 'AoO : Pull un plan du jeu…';

tiled.registerAction('AoONew', function() {
    try {
        var config = AoO.loadConfig();
        var plan = tiled.prompt('Nom du nouveau plan (minuscules, chiffres, _ et -) :', '', 'AoO — Nouveau plan');
        if (!plan) {
            return;
        }
        plan = plan.trim();

        AoO.api(config, 'POST', '/api/admin/map/create.php', { plan: plan });

        /* le plan vierge existe : le pull ramène une carte vide avec la
           palette complète (catalogue) prête à peindre */
        var fileName = AoO.pullAndOpen(plan);
        tiled.log('AoO : plan « ' + plan + ' » créé et ouvert depuis ' + fileName);
    } catch (error) {
        tiled.alert(String(error), 'AoO — Nouveau plan');
    }
}).text = 'AoO : Nouveau plan dans le jeu…';

tiled.registerAction('AoOPush', function() {
    var map = tiled.activeAsset;
    if (!map || !map.isTileMap) {
        tiled.alert('Ouvrir d\'abord une carte pullée du jeu.', 'AoO — Push');
        return;
    }

    try {
        /* carte hors jeu : AoO.push lèvera l'erreur explicite, pas de confirm */
        var plan = map.property(AoO.PROP.plan);

        var question = 'Pousser « ' + plan + ' » vers le jeu (tous les niveaux z de la carte) ?\n\n' +
            'Les cases construites par des joueurs et les objets au sol ne seront pas touchés.';
        if (plan && typeof tiled.confirm === 'function' && !tiled.confirm(question, 'AoO — Push')) {
            return;
        }

        var reports = AoO.push(map);
        tiled.alert('Plan appliqué au jeu.\n\n' + AoO.formatReport(reports), 'AoO — Push');
    } catch (error) {
        tiled.alert(String(error), 'AoO — Push');
    }
}).text = 'AoO : Push la carte vers le jeu…';

/* Menu Fichier : toujours visible, contrairement au menu Carte qui
   n'existe que lorsqu'une carte est ouverte. En mode --evaluate
   (headless), les menus n'existent pas du tout. */
try {
    tiled.extendMenu('File', [
        { separator: true },
        { action: 'AoOPull' },
        { action: 'AoOPush' },
        { action: 'AoONew' }
    ]);
    tiled.log('AoO : extension chargée, actions dans le menu Fichier');
} catch (error) {
    tiled.log('AoO : menus indisponibles (mode headless) — ' + error);
}
