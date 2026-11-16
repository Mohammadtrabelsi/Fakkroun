# Fakkroun (فكّرون)

**Think, Answer, Learn, Win!** Fakkroun est une plateforme de quiz multilingue (arabe, français, anglais) en temps réel, consacrée à l'économie verte, à l'économie bleue et à la sensibilisation à l'environnement. Les joueurs rejoignent les parties en direct avec un code de salon ou un lien d'invitation, sans compte.

Fakkroun repose sur le kit **MajidaStarter** (Laravel 13, Livewire 4). La référence du kit se trouve dans [`starter-kit.md`](starter-kit.md).

> Autres langues : [English](../en/README.md) · [العربية](../ar/README.md)

## Statut

- **MVP jouable** : banque de questions, modération, soumissions communautaires, salons multijoueurs en direct, score, résultats, publicité et statistiques sont implémentés.
- **Tests** : la suite a réussi en local le 11/11/2026 (664 tests). PHPStan signale encore des erreurs.
- **CI** : GitHub Actions n'avait lancé aucune tâche au moment de ce bilan. Vérifiez le dernier run avant de vous y fier. Voir [`../fakkroun/03-next-step-run-ci.md`](../fakkroun/03-next-step-run-ci.md).
- **Feuille de route et rapports de phase** : [`../fakkroun/`](../fakkroun), en commençant par [`02-mvp.md`](../fakkroun/02-mvp.md) et [`15-roadmap.md`](../fakkroun/15-roadmap.md).

## Fonctionnalités

| Domaine | Ce qu'il fait |
| --- | --- |
| **Parties en direct** | L'hôte crée un salon (thèmes, nombre de questions, secondes par question, difficulté, langue, nombre maximal de joueurs). Chaque salon reçoit un code à 6 caractères, un lien d'invitation et un QR code. Le lobby propose copie, Web Share et WhatsApp. Le serveur fixe toutes les échéances et calcule les scores ; le client n'envoie qu'un choix. |
| **Jouer en invité** | Toute personne peut rejoindre un salon avec un code ou un lien d'invitation et un pseudo. Créer un salon nécessite un compte. |
| **Règle des réponses identiques** | Quand suffisamment de joueurs donnent la même réponse, aucune de ces réponses ne rapporte de points. Le seuil se règle dans les paramètres d'administration. |
| **Score et résultats** | Points selon la difficulté, plus un bonus de rapidité. Classement en cours, classement final et statistiques par joueur. Historique des parties, succès et classement public (semaine et total). |
| **Banque de questions** | Questions à quatre choix, avec une traduction par langue, approuvée séparément. Cycle de vie : brouillon, en attente, en relecture, approuvée, publiée. Historique des révisions, détection des doublons exacts et proches, import et export (CSV, XLSX, JSON). |
| **Modération** | File d'attente pour les questions, les traductions et les signalements des joueurs. Les modérateurs sont limités à leurs thèmes. Chaque décision est journalisée. |
| **Communauté** | Les joueurs proposent des questions et suivent leur statut. Les auteurs sont notifiés de la décision. |
| **Rédaction assistée par IA** | Le personnel peut générer des brouillons de questions avec l'API Anthropic. Les brouillons commencent toujours comme brouillons en relecture. La fonctionnalité est masquée sans clé d'API. |
| **Publicité et facturation** | Tableau de bord annonceur, campagnes, partenaires, offres et suivi des clics publicitaires. Les paiements passent par un fournisseur interchangeable ; le fournisseur par défaut est `cash`. |
| **Statistiques** | Tableaux de bord d'administration : parties, joueurs, réponses, taux de bonnes réponses, temps de réponse, thèmes et langues, santé de la banque de questions et publicité. |
| **Socle du kit** | Connexion avec double authentification, rôles et permissions, journal d'activité, notifications, sauvegardes, contrôles de santé, blog, RSS, sitemap, API REST, thèmes clair et sombre, et français, anglais et arabe avec mise en page de droite à gauche. Voir [`starter-kit.md`](starter-kit.md). |

Un enregistrement d'une partie en direct est disponible dans [`../fakkroun/demo/fakkroun-demo.mp4`](../fakkroun/demo/fakkroun-demo.mp4) (1 min 40 s, hôte sur un ordinateur portable, deux invités sur téléphone).

## Prérequis

- PHP 8.4 ou plus récent, avec `mbstring`, `pdo`, `intl` et `bcmath` (la CI utilise aussi `pdo_sqlite` et `sqlite3`)
- Composer
- Node.js 20 ou plus récent, et npm

## Démarrage rapide

```bash
# 1. Installer les dépendances, créer .env, générer la clé de l'application, lancer les migrations et compiler les assets.
#    Configurez d'abord la base de données dans .env, car les migrations sont lancées ici.
composer setup

# 2. Remplir la base de données. Choisissez une option :
php artisan db:seed --class=ConfigDatabaseSeeder  # rôles, permissions, administrateur, 14 thèmes, lots annonceurs
php artisan db:seed                               # ce qui précède, plus des utilisateurs de démonstration, des questions d'exemple publiées et une partie de démonstration

# 3. Rendre les fichiers envoyés (images de couverture, logos) publics.
php artisan storage:link

# 4. Lancer l'environnement de développement (serveur PHP, file d'attente, suivi des journaux et Vite).
composer dev
```

Pour essayer avec SQLite, définissez ceci dans `.env` avant `composer setup`, puis créez le fichier :

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

```bash
touch database/database.sqlite
```

### Connexion

| Qui | Où | Compte |
| --- | --- | --- |
| Personnel (administrateurs, modérateurs, rédacteurs de questions) | `/admin/login` | `admin@fakkroun.test` / `password` |
| Joueurs | `/login` | Inscription sur `/register`, ou `test@example.com` / `password` après le remplissage complet |

Changez le mot de passe administrateur avant le déploiement. Définissez `ADMIN_EMAIL`, `ADMIN_NAME` et `ADMIN_PASSWORD` dans `.env`, puis relancez `AdminUserSeeder`. Les détails sont dans [`starter-kit.md`](starter-kit.md#connexion-administrateur).

### Essayer une partie

1. Connectez-vous sur `/admin/login`, puis ouvrez **Modération** pour vérifier les questions d'exemple.
2. Dans un second navigateur, créez un compte joueur ou ouvrez le lien d'invitation du salon.
3. Créez un salon depuis `/play/host` et lancez la partie depuis le lobby.

## Configuration

Les réglages courants se trouvent dans `.env.example`. Les clés ci-dessous sont propres à Fakkroun.

| Variable | Valeur par défaut | Rôle |
| --- | --- | --- |
| `ANTHROPIC_API_KEY` | vide | Active la rédaction de questions par IA. Sans elle, les écrans IA restent masqués. |
| `FAKKROUN_AI_MODEL` | `claude-opus-5-5` | Modèle utilisé pour la rédaction. |
| `FAKKROUN_AI_EFFORT` | `medium` | Effort de raisonnement pour la rédaction. |
| `FAKKROUN_AI_BATCH_COST_CEILING` | `20.0` | Coût estimé maximal d'un lot. |
| `FAKKROUN_PAYMENT_DRIVER` | `cash` | Fournisseur de paiement derrière le contrat de facturation. |
| `FAKKROUN_TERMS_DRAFT` | `true` | Marque la page des conditions comme brouillon. |
| `OWNER_PASSWORD` | aléatoire | Mot de passe de `OwnerUserSeeder` (à lancer seul). |

Les réglages du kit (files d'attente, sauvegardes, contrôles de santé, sitemap, flux, PDF, protection anti-spam et autres) sont listés dans [`starter-kit.md`](starter-kit.md#variables-denvironnement).

Pour la production, voir [`../fakkroun/13-deployment.md`](../fakkroun/13-deployment.md) et les notes de [`starter-kit.md`](starter-kit.md#notes-de-production). La configuration Docker est décrite dans [`../../README.docker.md`](../../README.docker.md).

## Architecture

```
app/
├── Domain/                 # Code métier de Fakkroun, un dossier par domaine
│   ├── Advertising/        # Campagnes, diffusion et clics publicitaires
│   ├── Ai/                 # Rédaction de questions (API Anthropic)
│   ├── Analytics/          # Statistiques d'administration
│   ├── Billing/            # Offres, grand livre et contrat de fournisseur de paiement
│   ├── Gameplay/           # Salons, moteur de jeu, score, règle des doublons
│   └── Questions/          # Banque de questions, modération, soumissions, imports
├── Services/               # Services du kit, un par modèle
├── Http/Controllers/Api/   # Les seuls contrôleurs : l'API JSON
└── Models/, Policies/, Observers/, Notifications/, ...

resources/views/pages/      # Composants Livewire à fichier unique : chaque page web
database/                   # Migrations, fabriques et seeders
routes/web.php              # Routes web, regroupées par niveau d'accès
routes/api.php              # API JSON
lang/{en,fr,ar}/            # Traductions
docs/                       # Documentation du projet
```

- **Les pages sont des composants Livewire** dans `resources/views/pages/`. Ils valident les saisies, vérifient les autorisations et délèguent aux services.
- **La logique métier vit dans les services** (`app/Services` et `app/Domain`). Chaque modèle est lu et écrit uniquement par son propre service. `tests/Feature/Architecture/ServiceLayerTest.php` le vérifie.
- **Le contrôle d'accès** utilise des politiques par modèle et les permissions de `spatie/laravel-permission`. Les routes d'administration sont protégées par le middleware `backoffice`.
- **Les traductions** sont organisées par fonctionnalité, avec `__()` pour chaque texte visible. `php artisan translations:sync --dry-run` liste les clés manquantes.

## API

Les points de terminaison JSON se trouvent sous `/api` et sont définis dans [`routes/api.php`](../../routes/api.php). Les points d'administration utilisent les mêmes portes de permission que les écrans web. Les soumissions de questions exigent la permission `submit questions`.

- **Authentification** : `POST /api/auth/token` renvoie un jeton porteur (`Authorization: Bearer <token>`), et `DELETE /api/auth/token` le révoque. La connexion par cookie de session est disponible sur `POST /api/auth/login`. Les codes de double authentification sont exigés quand elle est activée.
- **Limitation de débit** : les demandes d'inscription, de connexion et de jeton sont limitées à 6 par minute et par adresse e-mail et IP.
- **Ressources** : profil, soumissions et signalements de questions, thèmes, articles, utilisateurs (y compris suspension et réactivation) et paramètres.
- **Erreurs** : toujours au format JSON. En mode maintenance, chaque point de terminaison renvoie `503`, sauf pour les administrateurs.

## Tests et qualité

```bash
composer test          # ou : php artisan test
vendor/bin/pint --dirty   # style de code, fichiers modifiés uniquement
composer analyse          # Larastan (PHPStan)
```

Les tests utilisent une base SQLite en mémoire (voir `phpunit.xml`).

## Contribuer

- Créez vos branches à partir de `develop`. Ouvrez les pull requests vers `develop`. Ne ciblez pas `main` directement.
- Les messages de commit suivent le format `type(scope): subject`, par exemple `feat(questions): add bulk import`.
- Les règles internes de code et de relecture sont dans [`../../CLAUDE.md`](../../CLAUDE.md).

## Intégration continue

`.github/workflows/ci.yml` s'exécute à chaque push sur `develop` et `main`, et sur chaque pull request. Il lance la suite de tests sur PHP 8.4 (après la compilation des assets front-end avec Node 20), puis Pint et Larastan. Consultez le dernier run sur GitHub, car le statut ci-dessus peut être dépassé.

## Documentation

| Document | Contenu |
| --- | --- |
| [`../fakkroun/02-mvp.md`](../fakkroun/02-mvp.md) | Le MVP jouable : fonctionnalités, mode d'emploi, plan du code, tests |
| [`../fakkroun/14-final-report.md`](../fakkroun/14-final-report.md) | Rapport des phases 1 à 6 |
| [`../fakkroun/13-deployment.md`](../fakkroun/13-deployment.md) | Guide de déploiement |
| [`../fakkroun/15-roadmap.md`](../fakkroun/15-roadmap.md) | Travaux ouverts et prochaines étapes |
| [`starter-kit.md`](starter-kit.md) | Référence du kit MajidaStarter : fonctionnalités, configuration, rôles, localisation, génération de ressources |
| [`../spatie-integrations.md`](../spatie-integrations.md) | Slugs, SEO, tags, RSS, anti-spam, sauvegardes, santé, PDF, tableurs |
| [`../feature-audit-and-improvements.md`](../feature-audit-and-improvements.md) | Audit des fonctionnalités et liste d'améliorations |
| [`../questions/`](../questions) | Guides de rédaction de questions |

## Licence

Fakkroun repose sur MajidaStarter, distribué en open source sous [licence MIT](https://opensource.org/licenses/MIT). Il est construit sur le [framework Laravel](https://laravel.com), également sous licence MIT.
