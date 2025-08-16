# Guide Simple - Tests Unitaires des Logs

## 🎯 Qu'est-ce que c'est ?

Les tests unitaires des logs vérifient automatiquement que le système de logs fonctionne correctement. Ils testent toutes les règles complexes qui déterminent **qui voit quoi** dans le jeu.

## 🚀 Comment lancer les tests

### Tous les tests logs
```bash
vendor/bin/phpunit tests/Logs/
```

### Tests par catégorie
```bash
# Tests de visibilité des logs
vendor/bin/phpunit --group log-get

# Tests de création de logs
vendor/bin/phpunit --group log-put

# Tests de suppression des doublons
vendor/bin/phpunit --group filter-rows
```

## 🧪 Que testent-ils ?

### 1. **Qui voit les logs** (`log-get`)

#### ✅ Le joueur voit toujours :
- Ses propres actions
- Les actions dirigées contre lui
- Les événements proches de lui (selon sa perception)

#### ❌ Le joueur ne voit jamais :
- Les logs trop anciens (+ de 3 jours)
- Les logs du plan "birdland"
- Les `hidden_action` quand il est la cible
- Les événements trop loin de lui

#### 🎯 Cas spécial "destroy" :
- Les témoins peuvent voir les destructions d'objets
- Même s'ils ne sont ni l'acteur ni la cible

### 2. **Création des logs** (`log-put`)

#### ✅ Vérifie que :
- Les logs sont créés avec les bonnes informations
- Le mode incognito redirige vers "birdland"
- Les actions cachées n'ont pas de coordonnées
- Les timestamps personnalisés fonctionnent

### 3. **Suppression des doublons** (`filter-rows`)

#### ✅ Supprime les paires :
- `action` + `action_other_player` → garde seulement celui du joueur
- `kill` + `kill` → garde seulement celui du joueur
- `hidden_action` + `hidden_action_other_player` → garde celui du joueur

## 📁 Structure des fichiers

```
tests/Logs/
├── LogTest.php              # Tests principaux
├── FilterRowsTest.php       # Tests de déduplication
└── Mock/                    # Outils de test (ne pas modifier)
    ├── TestDatabase.php     # Base de données de test
    ├── PlayerMock.php       # Joueur factice
    ├── ViewMock.php         # Calculs de vision
    └── JsonMock.php         # Configuration du jeu
```

## 🔍 Comment comprendre un test

Exemple typique :

```php
public function testPlayerSeesOwnActions(): void
{
    // 1. PREPARATION : On crée des logs de test
    $this->testDb->insertLog([
        'text' => 'Player action',
        'player_id' => $this->player->id,  // Action DU joueur
        'target_id' => 2
    ]);
    $this->testDb->insertLog([
        'text' => 'Other action', 
        'player_id' => 3,                  // Action d'un autre
        'target_id' => 4
    ]);

    // 2. ACTION : On récupère les logs visibles
    $result = Log::get($this->player);
    
    // 3. VERIFICATION : Le joueur ne voit que sa propre action
    $this->assertCount(1, $result);                    // 1 seul log visible
    $this->assertEquals('Player action', $result[0]->text); // C'est le bon
}
```

## 🛠️ Comment modifier les tests

### Ajouter un test simple

```php
public function testMonNouveauComportement(): void
{
    // Créer des données de test
    $this->testDb->insertLog([
        'type' => 'mon_nouveau_type',
        'text' => 'Action de test',
        'player_id' => $this->player->id
    ]);

    // Tester le comportement
    $result = Log::get($this->player);
    
    // Vérifier le résultat
    $this->assertCount(1, $result);
    $this->assertEquals('mon_nouveau_type', $result[0]->type);
}
```

### Tester la perception spatiale

```php
public function testVisibiliteDansZone(): void
{
    // Créer un événement à une position donnée
    $this->testDb->insertLog([
        'coords_computed' => '10_15_0_forest'  // Position x=10, y=15
    ]);

    // Dire que le joueur peut voir cette position
    ViewMock::setCoordsAroundResult(['10_15_0_forest']);

    // Le joueur devrait voir l'événement
    $result = Log::get($this->player);
    $this->assertCount(1, $result);
}
```

## 🚨 Si un test échoue

### 1. Lire le message d'erreur
```
Failed asserting that actual size 0 matches expected size 1.
```
→ Le test attendait 1 log mais n'en a trouvé 0

### 2. Vérifier la logique
- Le joueur devrait-il vraiment voir ce log ?
- Les coordonnées sont-elles dans son champ de vision ?
- Le type d'événement est-il bien géré ?

### 3. Débugger
```php
// Ajouter temporairement dans le test
var_dump(Log::get($this->player)); // Voir ce qui est retourné
```

### 4. Erreurs communes
- **Aucun résultat** : Vérifier la perception (`ViewMock::setCoordsAroundResult`)
- **Trop de résultats** : Vérifier les filtres (plan, type, âge)
- **Mauvais résultat** : Vérifier les données insérées

## 🎲 Données de test pratiques

### Types d'événements courants
```php
'action'                    // Action normale
'hidden_action'            // Action cachée
'action_other_player'      // Vue depuis l'autre joueur
'destroy'                  // Destruction (visible aux témoins)
'kill'                     // Combat
'move'                     // Mouvement
'mdj'                      // Message maître du jeu
```

### Positions typiques
```php
'0_0_0_test_plan'          // Position de base du joueur test
'5_5_0_test_plan'          // Position proche
'100_100_0_test_plan'      // Position lointaine (non visible)
```

### Plans spéciaux
```php
'test_plan'                // Plan normal de test
'birdland'                 // Plan caché (logs filtrés)
```

## ⚡ Conseils rapides

### ✅ À faire
- Lancer les tests avant de commiter
- Ajouter un test quand on modifie la logique des logs
- Utiliser des noms de test descriptifs
- Garder les tests simples et lisibles

### ❌ À éviter
- Modifier les fichiers dans `Mock/` sans comprendre
- Tests trop complexes avec trop de données
- Oublier de nettoyer entre les tests
- Tests qui dépendent d'autres tests

## 🔄 Workflow typique

1. **Modifier** le code des logs
2. **Lancer** les tests : `vendor/bin/phpunit tests/Logs/`
3. **Si échec** : corriger le code ou adapter les tests
4. **Si succès** : commiter
5. **Si nouveau comportement** : ajouter un test

## 📞 Besoin d'aide ?

- **Tests qui passent** = Votre code ne casse rien ✅
- **Tests qui échouent** = Il faut corriger quelque chose ❌
- **Nouveau comportement** = Ajouter un test 📝

---

*Ce guide couvre l'essentiel pour comprendre et utiliser les tests des logs. Pour des modifications avancées, consultez la documentation complète.*