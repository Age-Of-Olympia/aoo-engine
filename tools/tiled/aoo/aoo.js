/// <reference types="@mapeditor/tiled-api" />
/*
 * Extension Tiled ↔ Age of Olympia : pull et push des plans du jeu.
 *
 * Installation : lier ce dossier dans le répertoire d'extensions de Tiled
 * (Édition > Préférences > Plugins > Ouvrir le dossier d'extensions), puis
 * copier config.json.exemple en config.json et le remplir.
 *
 * Authentification : compte du jeu avec l'option isAdmin. L'extension
 * demande les identifiants au premier appel et met le jeton en cache dans
 * session.json (gitignoré, expire côté serveur).
 *
 * Conventions :
 *  - un (plan, z) du jeu = une carte infinie Tiled, tuiles 50x50 ;
 *  - le y du jeu monte vers le nord, celui de Tiled descend → tiledY = -gameY ;
 *  - couches de tuiles : tiles, routes, plants, walls, elements, foregrounds ;
 *  - couches d'objets : triggers, dialogs (params par instance) ;
 *  - map_items (état runtime) n'est jamais exporté ni importé ;
 *  - au push, les lignes posées par des joueurs (player_id) sont intouchables
 *    et l'état runtime (damages, endTime) des lignes inchangées survit.
 */

var AoO = {};

/* Ordre d'empilement bas → haut, aligné sur le rendu du jeu (Classes/View.php) */
AoO.TILE_LAYERS = ['tiles', 'routes', 'plants', 'walls', 'elements', 'foregrounds'];
AoO.OBJECT_LAYERS = ['triggers', 'dialogs'];

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
        throw new Error('API (' + xhr.status + ') : ' + data.error);
    }
    return data;
};

/* ------------------------------------------------------------------ */
/* Pull : jeu → carte Tiled                                            */
/* ------------------------------------------------------------------ */

/*
 * Construit un tileset « collection d'images » par couche : une tuile par
 * nom distinct, image individuelle du dépôt (pas d'atlas à générer).
 */
AoO.buildTilesets = function(map, data, config) {
    var tilesByLayerAndName = {};

    var allLayers = AoO.TILE_LAYERS.concat(AoO.OBJECT_LAYERS);

    for (var i = 0; i < allLayers.length; i++) {
        var layerName = allLayers[i];
        var rows = data.layers[layerName] || [];
        if (rows.length === 0) {
            continue;
        }

        var names = {};
        for (var j = 0; j < rows.length; j++) {
            names[rows[j].name] = true;
        }

        var tileset = new Tileset('aoo-' + layerName);
        tileset.setProperty('aooLayer', layerName);
        var byName = {};

        for (var name in names) {
            var tile = tileset.addTile();
            tile.setProperty('aooName', name);
            var imagePath = data.images[layerName + '/' + name];
            if (imagePath) {
                tile.imageFileName = config.gameDir + '/' + imagePath;
            }
            byName[name] = tile;
        }

        map.addTileset(tileset);
        tilesByLayerAndName[layerName] = byName;
    }

    return tilesByLayerAndName;
};

AoO.isPlayerRow = function(row) {
    return Boolean(row.player_id && parseInt(row.player_id, 10) !== 0);
};

AoO.buildTileLayer = function(map, layerName, rows, tilesByName) {
    var layer = new TileLayer(layerName);
    map.addLayer(layer);

    var edit = layer.edit();
    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        /* y inversé : le jeu monte, Tiled descend */
        edit.setTile(row.x, -row.y, tilesByName[row.name]);
    }
    edit.apply();

    return layer;
};

/*
 * Les constructions des joueurs (player_id) sont montrées mais pas
 * éditables : couche verrouillée, ignorée au push (nom inconnu du
 * serveur), jamais touchée par l'import côté serveur non plus.
 */
AoO.buildSplitTileLayers = function(map, layerName, rows, tilesByName) {
    var authored = [];
    var playerBuilt = [];

    for (var i = 0; i < rows.length; i++) {
        (AoO.isPlayerRow(rows[i]) ? playerBuilt : authored).push(rows[i]);
    }

    if (authored.length > 0 || playerBuilt.length === 0) {
        AoO.buildTileLayer(map, layerName, authored, tilesByName);
    }

    if (playerBuilt.length > 0) {
        var lockedLayer = AoO.buildTileLayer(map, layerName + ' (joueurs)', playerBuilt, tilesByName);
        lockedLayer.locked = true;
    }
};

AoO.buildObjectLayer = function(map, layerName, rows, tilesByName, tileSize) {
    var group = new ObjectGroup(layerName);
    map.addLayer(group);

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var object = new MapObject(row.name);
        var tile = tilesByName ? tilesByName[row.name] : null;

        object.x = row.x * tileSize;
        /* objet-tuile : ancré en bas à gauche ; rectangle nu : en haut à gauche */
        object.y = (tile ? (-row.y + 1) : -row.y) * tileSize;
        object.width = tileSize;
        object.height = tileSize;
        if (tile) {
            object.tile = tile;
        }
        if (row.params !== undefined && row.params !== null && row.params !== '') {
            object.setProperty('params', String(row.params));
        }
        group.addObject(object);
    }
};

/* Télécharge un (plan, z) du jeu et le reconstruit en TileMap Tiled */
AoO.pull = function(plan, z) {
    var config = AoO.loadConfig();
    var data = AoO.api(config, 'GET', '/api/admin/map/export.php?plan=' + plan + '&z=' + z);

    var map = new TileMap();
    map.orientation = TileMap.Orthogonal;
    map.setTileSize(data.tileSize, data.tileSize);
    map.infinite = true;
    map.setProperty('aooPlan', plan);
    map.setProperty('aooZ', z);
    map.setProperty('aooVersion', data.version);

    var tilesets = AoO.buildTilesets(map, data, config);

    for (var i = 0; i < AoO.TILE_LAYERS.length; i++) {
        var layerName = AoO.TILE_LAYERS[i];
        var rows = data.layers[layerName] || [];
        if (rows.length > 0) {
            AoO.buildSplitTileLayers(map, layerName, rows, tilesets[layerName]);
        }
    }

    for (var k = 0; k < AoO.OBJECT_LAYERS.length; k++) {
        var objectLayerName = AoO.OBJECT_LAYERS[k];
        var objectRows = data.layers[objectLayerName] || [];
        if (objectRows.length > 0) {
            AoO.buildObjectLayer(map, objectLayerName, objectRows,
                tilesets[objectLayerName], data.tileSize);
        }
    }

    return map;
};

/* Pull + sauvegarde en .tmj ; utilisable sans éditeur (tests headless) */
AoO.pullAndSave = function(plan, z) {
    var config = AoO.loadConfig();
    var map = AoO.pull(plan, z);

    var suffix = z ? ('_z' + z) : '';
    var fileName = config.gameDir + '/tools/tiled/maps/' + plan + suffix + '.tmj';
    File.makePath(config.gameDir + '/tools/tiled/maps');
    var format = tiled.mapFormat('json');
    format.write(map, fileName);

    return fileName;
};

AoO.pullAndOpen = function(plan, z) {
    var fileName = AoO.pullAndSave(plan, z);
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
            var name = tile.property('aooName');
            if (!name) {
                throw new Error('Tuile sans propriété aooName en ' + x + ',' + (-y) +
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

        var name = object.name;
        if (!name && object.tile) {
            name = object.tile.property('aooName');
        }
        if (!name) {
            throw new Error('Objet sans nom dans la couche ' + layer.name);
        }

        var tiledY = Math.round(object.y / tileSize);
        /* objet-tuile ancré en bas : sa case est une tuile plus haut */
        if (object.tile) {
            tiledY -= 1;
        }

        var row = {
            x: Math.round(object.x / tileSize),
            y: -tiledY,
            name: name
        };

        var params = object.property('params');
        if (params !== undefined && params !== null && String(params) !== '') {
            row.params = String(params);
        }

        rows.push(row);
    }

    return rows;
};

AoO.serializeMap = function(map) {
    var layers = {};

    for (var i = 0; i < map.layerCount; i++) {
        var layer = map.layerAt(i);

        if (layer.isTileLayer && AoO.TILE_LAYERS.indexOf(layer.name) !== -1) {
            layers[layer.name] = AoO.serializeTileLayer(layer);
        } else if (layer.isObjectLayer && AoO.OBJECT_LAYERS.indexOf(layer.name) !== -1) {
            layers[layer.name] = AoO.serializeObjectLayer(layer, map.tileWidth);
        } else {
            tiled.log('AoO : couche « ' + layer.name + ' » ignorée au push (nom inconnu)');
        }
    }

    return layers;
};

/* Pousse la carte vers le jeu ; retourne le rapport de l'API */
AoO.push = function(map) {
    if (!map || !map.property('aooPlan')) {
        throw new Error('Cette carte ne vient pas du jeu (propriété aooPlan absente) — faire un pull d\'abord');
    }

    var config = AoO.loadConfig();
    var payload = {
        plan: map.property('aooPlan'),
        z: map.property('aooZ') || 0,
        version: map.property('aooVersion'),
        layers: AoO.serializeMap(map)
    };

    var data = AoO.api(config, 'POST', '/api/admin/map/import.php', payload);
    map.setProperty('aooVersion', data.newVersion);
    return data;
};

AoO.formatReport = function(data) {
    var lines = [];
    for (var layer in data.layers) {
        var r = data.layers[layer];
        if (r.inserted || r.deleted || r.kept || r.protected) {
            lines.push(layer + ' : +' + r.inserted + ' / -' + r.deleted +
                ' / ' + r.kept + ' conservés' +
                (r.protected ? ' / ' + r.protected + ' protégés (joueurs)' : ''));
        }
    }
    return lines.length ? lines.join('\n') : 'Aucun changement.';
};

/* ------------------------------------------------------------------ */
/* Actions et menus                                                    */
/* ------------------------------------------------------------------ */

tiled.registerAction('AoOPull', function() {
    var spec = tiled.prompt('Plan à récupérer (« plan » ou « plan:z ») :', 'gaia', 'AoO — Pull');
    if (!spec) {
        return;
    }
    var parts = spec.split(':');
    var plan = parts[0].trim();
    var z = parts.length > 1 ? parseInt(parts[1], 10) || 0 : 0;

    try {
        var fileName = AoO.pullAndOpen(plan, z);
        tiled.log('AoO : plan « ' + plan + ' » (z=' + z + ') ouvert depuis ' + fileName);
    } catch (error) {
        tiled.alert(String(error), 'AoO — Pull');
    }
}).text = 'AoO : Pull un plan du jeu…';

tiled.registerAction('AoOPush', function() {
    var map = tiled.activeAsset;
    if (!map || !map.isTileMap) {
        tiled.alert('Ouvrir d\'abord une carte pullée du jeu.', 'AoO — Push');
        return;
    }

    try {
        var plan = map.property('aooPlan');
        if (!plan) {
            throw new Error('Cette carte ne vient pas du jeu (propriété aooPlan absente)');
        }

        var question = 'Pousser « ' + plan + ' » (z=' + (map.property('aooZ') || 0) +
            ') vers le jeu ?\n\nLes cases construites par des joueurs et les objets au sol ne seront pas touchés.';
        if (typeof tiled.confirm === 'function' && !tiled.confirm(question, 'AoO — Push')) {
            return;
        }

        var data = AoO.push(map);
        tiled.alert('Plan appliqué au jeu.\n\n' + AoO.formatReport(data), 'AoO — Push');
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
        { action: 'AoOPush' }
    ]);
    tiled.log('AoO : extension chargée, actions dans le menu Fichier');
} catch (error) {
    tiled.log('AoO : menus indisponibles (mode headless) — ' + error);
}
