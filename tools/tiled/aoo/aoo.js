/// <reference types="@mapeditor/tiled-api" />
/*
 * Extension Tiled ↔ Age of Olympia (spike : pull uniquement).
 *
 * Installation : lier ce dossier dans le répertoire d'extensions de Tiled
 * (Édition > Préférences > Plugins > Ouvrir le dossier d'extensions), puis
 * copier config.json.exemple en config.json et le remplir.
 *
 * Conventions :
 *  - un plan du jeu = une carte infinie Tiled, tuiles 50x50 ;
 *  - le y du jeu monte vers le nord, celui de Tiled descend → tiledY = -gameY ;
 *  - couches de tuiles : tiles, routes, plants, walls, elements, foregrounds ;
 *  - couches d'objets : triggers, dialogs (params par instance) ;
 *  - map_items (état runtime) n'est jamais exporté ni importé.
 */

var AoO = {};

/* Ordre d'empilement bas → haut, aligné sur le rendu du jeu (Classes/View.php) */
AoO.TILE_LAYERS = ['tiles', 'routes', 'plants', 'walls', 'elements', 'foregrounds'];
AoO.OBJECT_LAYERS = ['triggers', 'dialogs'];

AoO.configPath = function() {
    return tiled.extensionsPath + '/aoo/config.json';
};

AoO.loadConfig = function() {
    var file = new TextFile(AoO.configPath(), TextFile.ReadOnly);
    var config = JSON.parse(file.readAll());
    file.close();
    if (!config.baseUrl || !config.token || !config.gameDir) {
        throw new Error('config.json incomplet : baseUrl, token et gameDir sont requis');
    }
    return config;
};

AoO.fetchJson = function(config, path) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', config.baseUrl + path, false);
    xhr.setRequestHeader('X-AoO-Tiled-Token', config.token);
    xhr.send();
    if (xhr.status !== 200) {
        throw new Error('HTTP ' + xhr.status + ' sur ' + path + ' : ' + xhr.responseText);
    }
    var data = JSON.parse(xhr.responseText);
    if (!data.success) {
        throw new Error('API : ' + data.error);
    }
    return data;
};

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
};

AoO.buildObjectLayer = function(map, layerName, rows, tilesByName, tileSize) {
    var group = new ObjectGroup(layerName);
    map.addLayer(group);

    for (var i = 0; i < rows.length; i++) {
        var row = rows[i];
        var object = new MapObject(row.name);
        object.x = row.x * tileSize;
        /* objet-tuile : ancré en bas à gauche, d'où le +1 */
        object.y = (-row.y + 1) * tileSize;
        object.width = tileSize;
        object.height = tileSize;
        if (tilesByName && tilesByName[row.name]) {
            object.tile = tilesByName[row.name];
        }
        if (row.params !== undefined && row.params !== null) {
            object.setProperty('params', String(row.params));
        }
        group.addObject(object);
    }
};

/* Télécharge un plan du jeu et le reconstruit en TileMap Tiled */
AoO.pull = function(plan) {
    var config = AoO.loadConfig();
    var data = AoO.fetchJson(config, '/api/admin/map/export.php?plan=' + plan);

    var map = new TileMap();
    map.orientation = TileMap.Orthogonal;
    map.setTileSize(data.tileSize, data.tileSize);
    map.infinite = true;
    map.setProperty('aooPlan', plan);

    var tilesets = AoO.buildTilesets(map, data, config);

    for (var i = 0; i < AoO.TILE_LAYERS.length; i++) {
        var layerName = AoO.TILE_LAYERS[i];
        var rows = data.layers[layerName] || [];
        if (rows.length > 0) {
            AoO.buildTileLayer(map, layerName, rows, tilesets[layerName]);
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
AoO.pullAndSave = function(plan) {
    var config = AoO.loadConfig();
    var map = AoO.pull(plan);

    var fileName = config.gameDir + '/tools/tiled/maps/' + plan + '.tmj';
    File.makePath(config.gameDir + '/tools/tiled/maps');
    var format = tiled.mapFormat('json');
    format.write(map, fileName);

    return fileName;
};

AoO.pullAndOpen = function(plan) {
    var fileName = AoO.pullAndSave(plan);
    tiled.open(fileName);
    return fileName;
};

tiled.registerAction('AoOPull', function() {
    var plan = tiled.prompt('Nom du plan à récupérer :', 'gaia', 'AoO — Pull');
    if (!plan) {
        return;
    }
    try {
        var fileName = AoO.pullAndOpen(plan);
        tiled.log('AoO : plan « ' + plan + ' » ouvert depuis ' + fileName);
    } catch (error) {
        tiled.alert(String(error), 'AoO — Pull');
    }
}).text = 'AoO : Pull un plan du jeu…';

/* Menu Fichier : toujours visible, contrairement au menu Carte qui
   n'existe que lorsqu'une carte est ouverte. En mode --evaluate
   (headless), les menus n'existent pas du tout. */
try {
    tiled.extendMenu('File', [
        { separator: true },
        { action: 'AoOPull' }
    ]);
    tiled.log('AoO : extension chargée, action disponible dans le menu Fichier');
} catch (error) {
    tiled.log('AoO : menus indisponibles (mode headless) — ' + error);
}
