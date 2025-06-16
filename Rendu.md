# AOO Engine MDS - Groupe Jérôme - Ovsep

Projet réalisé dans le cadre du module de développement encadré à **MyDigitalSchool**.

Ce projet avait pour objectif de mettre en place une chaîne de développement complète incluant automatisation, intégration continue, tests et documentation.

---

## Liste des tâches réalisées

| Éléments                                                      | Statut   |
|---------------------------------------------------------------|----------|
| Fork du projet                                                | DONE     |
| Dépôt GitLab avec CI/CD                                       | DONE     |
| Automatisation de l'entrypoint via Makefile                   | DONE     |
| Runner CI local + Registry d’image                            | DONE     |
| Tests Unitaires (dont `calculateXp`, test critique)           | DONE     |
| Tests de Qualité (PHPStan sur le dossier `tests/`)            | DONE     |
| Tests de Sécurité (notamment contre l’injection SQL)          | DONE     |
| Tests End-to-End (accès formulaire notamment)                 | DONE     |
| Documentation accessible et lisible                           | DONE     |

---

## Automatisation & CI

Afin de centraliser les commandes et de faciliter le développement, nous avons mis en place un **Makefile** qui est utilisé à la fois en **local** et dans la **CI**.

### Makefile

- Permet de lancer les commandes (tests, build, etc.) très facilement.
- Utilisé **aussi dans la CI** pour éviter la duplication des scripts.
- Exemples de commandes :

```bash
make test
make start
```

---

## CI

Nous avons mis en place une chaîne CI complète avec les éléments suivants :

- **Runner local** pour exécuter les jobs en local.
- **Registry Docker** avec système de cache pour optimiser les builds.
- **Templates** dans `.gitlab-ci.yml` afin d’éviter la duplication des commandes.
- **Arrêt automatique** de la CI en cas d’échec d’un test.
- **Schéma** du fonctionnement de la registry ajouté dans la documentation.

---

## Tests

| Type de test   | Détail                                                                 |
|----------------|------------------------------------------------------------------------|
| **Unitaire**   | Tests pertinents sur les fonctions critiques, notamment `calculateXp`. |
| **Qualité**    | PHPStan appliqué uniquement sur `tests/`. |
| **Sécurité**   | Vérification des failles de type injection SQL.                        |
| **End-to-End** | Vérification de l'accès au formulaire, un point clé du projet.         |

---

## Documentation

- CI: https://gitlab.com/ovsmar/aoo-engine-mds/-/tree/dev/gitlab-ci
- E2E: https://gitlab.com/ovsmar/aoo-engine-mds/-/tree/dev/selenium_tests

---

## Ressources

- Project: https://gitlab.com/ovsmar/aoo-engine-mds/-/tree/dev
- CI: https://gitlab.com/ovsmar/aoo-engine-mds/-/tree/dev/gitlab-ci
- E2E: https://gitlab.com/ovsmar/aoo-engine-mds/-/tree/dev/selenium_tests
- Issue board: https://gitlab.com/ovsmar/aoo-engine-mds/-/boards
- Container registry: https://gitlab.com/ovsmar/aoo-engine-mds/container_registry
- Artifacts: https://gitlab.com/ovsmar/aoo-engine-mds/-/artifacts
- Selenium ci : https://gitlab.com/ovsmar/aoo-engine-mds/-/merge_requests/19
- Diaporama : https://docs.google.com/presentation/d/1uBAjgHCjYcnm25-jpN645qStnQC20N8Fyy20oHUm5Ro/edit?usp=sharing