# MajidaStarter : référence du kit de démarrage

Fakkroun repose sur le kit MajidaStarter. Ce fichier contient la référence au niveau du kit (fonctionnalités, configuration, comptes de démonstration, services, localisation, rôles, génération de ressources) vers laquelle renvoie le [`README.md`](README.md) de cette langue. Les fonctionnalités propres à Fakkroun sont décrites dans ce README et dans [`docs/fakkroun/`](../fakkroun).

> Autres langues : [English](../en/starter-kit.md) · [العربية](../ar/starter-kit.md)

## Points forts

- **Parcours d'authentification complet** : inscription, connexion, déconnexion, réinitialisation du mot de passe et vérification d'e-mail facultative, le tout en composants Livewire à fichier unique. Les joueurs s'inscrivent et se connectent avec une adresse e-mail **ou** un numéro de téléphone, et peuvent jouer immédiatement (sans étape de vérification). La connexion est limitée par e-mail/téléphone et par IP.
- **Double authentification** : applications TOTP (RFC 6238) avec codes de récupération à usage unique, gérée depuis **Paramètres → Double authentification** et exigée à la connexion web et API une fois confirmée. Aucune dépendance supplémentaire n'est nécessaire.
- **Panneau d'administration** : un back-office dédié, protégé par permissions, pour gérer les utilisateurs, les articles, les thèmes, consulter l'activité et modifier les paramètres du site.
- **Module de contenu** : CRUD complet pour les **articles** et les **thèmes** dans le panneau d'administration. Les articles publiés apparaissent sur `/blog`, avec recherche, pages de détail et images de couverture ; `/docs` est le guide intégré. Les suppressions vont dans une **corbeille** et peuvent être restaurées ; la publication et la suppression groupées, ainsi que les filtres et le tri, sont intégrés aux listes d'administration.
- **Contrôle d'accès par rôles et permissions** : basé sur [spatie/laravel-permission](https://spatie.be/docs/laravel-permission), avec un rôle `admin` prêt à l'emploi.
- **Journal d'activité** : chaque modification importante des utilisateurs, articles, thèmes et paramètres est enregistrée avec [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog) et consultable depuis le panneau d'administration.
- **Export et conservation du journal d'activité** : téléchargement du journal filtré au format CSV ou JSON ; `activitylog:clean` s'exécute chaque jour via le planificateur.
- **Notifications CRUD** : les administrateurs reçoivent une notification enregistrée lorsque des articles, des thèmes, des utilisateurs ou des paramètres sont créés, modifiés, supprimés ou restaurés, et peuvent les consulter sur une page de notifications liée à la cloche. L'auteur d'une modification n'est jamais notifié. Voir [Notes sur les fonctionnalités](#notes-sur-les-fonctionnalités).
- **Plan du site XML** : `php artisan sitemap:generate` écrit `/sitemap.xml` (via [spatie/laravel-sitemap](https://github.com/spatie/laravel-sitemap)) à partir des pages publiques et des articles publiés ; il est reconstruit chaque heure par le planificateur et après les modifications d'article, et déclaré dans un `/robots.txt` dynamique. Voir [Notes sur les fonctionnalités](#notes-sur-les-fonctionnalités).
- **Étiquettes** : étiquettes d'article traduisibles via [spatie/laravel-tags](https://spatie.be/docs/laravel-tags) : sélecteur réutilisable dans le formulaire d'article, écran d'administration des étiquettes et filtrage dans la liste d'administration, sur `/blog?tag=` et dans l'API.
- **Données structurées SEO et RSS** : JSON-LD schema.org (Organization, WebSite, BreadcrumbList, BlogPosting) avec [spatie/schema-org](https://github.com/spatie/schema-org), balises canoniques et Open Graph, et un flux RSS sur `/feed` avec [spatie/laravel-feed](https://github.com/spatie/laravel-feed). Les slugs sont générés et stabilisés par [spatie/laravel-sluggable](https://github.com/spatie/laravel-sluggable).
- **Protection anti-spam** : les formulaires d'inscription et de mot de passe oublié comportent un champ piège et une vérification de délai ([spatie/laravel-honeypot](https://github.com/spatie/laravel-honeypot)).
- **Sauvegardes et supervision** : sauvegardes planifiées et surveillées de la base et des fichiers envoyés ([spatie/laravel-backup](https://spatie.be/docs/laravel-backup)) et contrôles de santé de l'application ([spatie/laravel-health](https://spatie.be/docs/laravel-health)), chacun avec une page d'administration ; un `/health.json` protégé par jeton pour les outils de surveillance.
- **PDF et tableurs** : PDF des articles à partir d'une mise en page Blade commune ([spatie/laravel-pdf](https://spatie.be/docs/laravel-pdf), pilote DomPDF), exports CSV/XLSX en flux et import validé des thèmes ([spatie/simple-excel](https://github.com/spatie/simple-excel)). Voir **[docs/spatie-integrations.md](../spatie-integrations.md)** pour le détail.
- **Statistiques de visites** : les pages vues du site public sont comptées (les robots, l'administration, l'API et les ressources statiques sont exclus) et affichées sur les tableaux de bord.
- **Santé du système** : le tableau de bord d'administration vérifie la base, le cache et la file d'attente, et `/up` expose un point de contrôle de disponibilité pour les outils de surveillance.
- **API REST** : chaque service est exposé sous forme de points de terminaison JSON sous `/api`, protégés par les mêmes autorisations que l'interface web. Les sessions et les jetons personnels Sanctum sont tous deux acceptés (voir [API](README.md#api)).
- **Localisation (i18n) avec prise en charge du RTL** : anglais, arabe et français sont fournis par défaut, avec un sélecteur de langue, la mémorisation de la langue par session et la gestion automatique du sens du texte.
- **Paramètres traduisibles** : le nom et la description du site sont stockés par langue via [spatie/laravel-translatable](https://spatie.be/docs/laravel-translatable).
- **Bascule thème clair / sombre** : un sélecteur persistant, sans clignotement.
- **Mode maintenance** : activé dans les paramètres d'administration ; les visiteurs reçoivent une page 503 (ou du JSON sur `/api`), tandis que les administrateurs, la connexion, le changement de langue et `/up` restent accessibles.
- **Suppression réversible et corbeille** : les utilisateurs, articles et thèmes sont supprimés logiquement, peuvent être restaurés et peuvent être supprimés définitivement.
- **Politiques** : l'accès aux articles et aux thèmes est contrôlé par `PostPolicy` / `CategoryPolicy` (les éditeurs d'article ne peuvent modifier que leurs propres articles, sauf s'ils sont administrateurs).
- **Courriels en file d'attente** : les e-mails de vérification et de réinitialisation du mot de passe sont envoyés via la file d'attente.
- **Génération CRUD** : `php artisan starter:make-crud Article` génère le modèle, la migration, la fabrique, le service, la politique et un test pour une nouvelle ressource.
- **Architecture orientée services** : la logique métier se trouve dans des classes de service dédiées (`UserService`, `PostService`, `CategoryService`, `SettingService`, et d'autres), et non dans les contrôleurs ou les composants.
- **Front-end moderne** : Tailwind CSS 4 et Vite 8, configurés et prêts à l'emploi.
- **Testé et analysé** : une suite de tests PHPUnit couvrant l'authentification, la double authentification, les profils, les pages d'administration, la gestion des utilisateurs, des articles et des thèmes, l'API, les notifications, le mode maintenance et la localisation, plus Pint (style de code) et Larastan (analyse statique), exécutés en CI.

### Notes de production

- **Images de couverture et photos de profil** sont stockées sur le disque `public`. Lancez `php artisan storage:link` une fois par environnement.
- **Files d'attente** : les e-mails sont mis en file (`QUEUE_CONNECTION=database` par défaut). Lancez `php artisan queue:work` comme processus permanent ; `composer dev` en démarre un en local. Sans worker, les e-mails de vérification et de réinitialisation ne partent pas.
- **Planificateur** : lancez `php artisan schedule:work` en local, ou ajoutez `* * * * * php /chemin/vers/artisan schedule:run` au cron en production. Le planificateur nettoie le journal d'activité, reconstruit le sitemap, lance les contrôles de santé chaque minute et la sauvegarde quotidienne.
- **Sitemap** : définissez `APP_URL` avec le domaine de production canonique (par ex. `https://www.example.com`). Toutes les URL du sitemap et de robots.txt en sont issues, et la génération refuse les hôtes de type `localhost` ou `.test` en production. L'utilisateur PHP qui exécute le planificateur et le worker doit pouvoir écrire dans `public/`. Lancez `php artisan sitemap:generate` une fois après le déploiement.
- **Nouvelles permissions** : après une mise à jour, lancez `php artisan migrate --force` et `php artisan db:seed --class=RolesAndPermissionsSeeder --force` pour que le rôle admin reçoive `manage tags`, `view system health` et `manage backups`.
- **Sauvegardes** : le serveur a besoin de `mysqldump`/`pg_dump`/`sqlite3` selon votre pilote de base de données. Définissez `BACKUP_NOTIFICATION_EMAIL` et, pour des disques hors site, `BACKUP_DISKS` et `BACKUP_ARCHIVE_PASSWORD`. Les archives excluent `.env` et le code source. Étapes de restauration : [docs/spatie-integrations.md#restoring](../spatie-integrations.md#restoring).
- **Supervision** : le planificateur lance les contrôles chaque minute. Définissez `HEALTH_NOTIFICATION_EMAIL` pour les alertes et `HEALTH_SECRET_TOKEN` pour activer `/health.json` pour les outils externes.
- **Mode maintenance** : piloté depuis **Administration → Paramètres** ; il n'a pas besoin de `php artisan down`.
- **Contrôles de santé** : pointez les outils de surveillance vers `/up`. La carte de santé du tableau de bord affiche les mêmes sondes plus en détail.
- **Mots de passe** : chaque règle utilise `Password::defaults()`. En production, elle exige aussi des majuscules et minuscules, des chiffres et un mot de passe absent des listes de fuites connues. Hors production, le minimum est de 8 caractères.
- **Limites de débit** : les points `auth/login`, `auth/register` et `auth/token` de l'API autorisent 6 requêtes par minute et par adresse e-mail et IP.

### Variables d'environnement

Ce sont les clés qui modifient le comportement propre au kit de démarrage. Le reste relève de la configuration Laravel standard.

| Variable | Valeur par défaut | Rôle |
| --- | --- | --- |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` | Langue par défaut et de repli de l'interface |
| `APP_MAINTENANCE_DRIVER` | `file` | Pilote de maintenance Laravel (`php artisan down`) ; le bouton d'administration ne l'utilise pas |
| `GITHUB_URL` | le dépôt du projet | Liens vers GitHub dans la barre de navigation, la page d'accueil et la page de documentation |
| `QUEUE_CONNECTION` | `database` | Où s'exécutent les e-mails et notifications en file d'attente |
| `CACHE_STORE` | `database` | Stockage du cache, utilisé par les sondes de santé et la limitation de débit |
| `SESSION_DRIVER` | `database` | Stockage des sessions de connexion web |
| `SITEMAP_SCHEDULE` | `0 * * * *` | Expression cron de régénération du sitemap |
| `SITEMAP_REGENERATE_ON_CHANGE` | `true` | Met en file une reconstruction du sitemap quand un article publié change |
| `SITEMAP_MAX_URLS_PER_FILE` | `50000` | Découpe en index de sitemaps au-delà de ce nombre d'URL |
| `FEED_ENABLED` / `FEED_LIMIT` | `true` / `20` | Flux RSS sur `/feed` |
| `SEO_STRUCTURED_DATA` | `true` | JSON-LD sur les pages publiques (`SEO_ORGANIZATION_LOGO`, `SEO_SAME_AS` pour l'organisation) |
| `HONEYPOT_ENABLED` / `HONEYPOT_SECONDS` | `true` / `1` | Protection anti-spam pour l'inscription et le mot de passe oublié |
| `BACKUP_DISKS` | `backups` | Destinations des sauvegardes (disques séparés par des virgules) |
| `BACKUP_SCHEDULE_ENABLED` / `BACKUP_RUN_AT` | `true` / `01:30` | Sauvegarde quotidienne, puis nettoyage (+1 h) et supervision (+1 h 30) |
| `BACKUP_NOTIFICATION_EMAIL` | `ADMIN_EMAIL` | Destinataire des e-mails d'échec de sauvegarde |
| `BACKUP_ARCHIVE_PASSWORD` | vide | Chiffre les archives (recommandé hors site) |
| `HEALTH_NOTIFICATION_EMAIL` | vide | Destinataire des e-mails d'échec de contrôle (aucun envoi si vide) |
| `HEALTH_SECRET_TOKEN` | vide | Jeton porteur pour `/health.json` (le point renvoie 404 si vide) |
| `LARAVEL_PDF_DRIVER` | `dompdf` | Moteur PDF ; `browsershot` pour les documents en arabe/RTL |
| `PDF_FORMAT` / `PDF_ORIENTATION` | `a4` / `portrait` | Mise en page PDF par défaut |
| `IMPORT_MAX_KB` / `IMPORT_MAX_ROWS` | `5120` / `5000` | Limites d'envoi pour les imports CSV/XLSX |
| `MAIL_MAILER` | `log` dans `.env.example` | À définir sur un vrai expéditeur avant d'envoyer les e-mails de vérification ou de réinitialisation |
| `CRUD_NOTIFICATIONS_ENABLED` | `true` | Active ou désactive les observateurs de notifications CRUD |
| `ADMIN_EMAIL` / `ADMIN_NAME` / `ADMIN_PASSWORD` | `admin@fakkroun.test` / `Admin User` / `password` | Lus uniquement par `AdminUserSeeder` |

### Connexion administrateur

Le personnel (administrateurs, rédacteurs et relecteurs de questions) se connecte via le portail du back-office, sur `/admin/login` ; les joueurs utilisent `/login` côté public. Chaque portail refuse l'autre type de compte. Connectez-vous sur `/admin/login` avec le compte administrateur créé par le seeder :

| Champ | Valeur par défaut |
| --- | --- |
| E-mail | `admin@fakkroun.test` |
| Mot de passe | `password` |

L'administrateur est créé par `database/seeders/AdminUserSeeder.php` et est toujours semé, y compris par `php artisan db:seed --class=MinimalDatabaseSeeder` et `ConfigDatabaseSeeder`. Pour modifier les valeurs par défaut avant le remplissage, définissez-les dans `.env` :

```dotenv
ADMIN_NAME="Admin User"
ADMIN_EMAIL=admin@fakkroun.test
ADMIN_PASSWORD=change-me-now
```

> **Changez le mot de passe administrateur avant le déploiement.** `password` est une valeur par défaut bien connue. Relancer le seeder met à jour le mot de passe de l'administrateur existant avec la valeur actuelle de `ADMIN_PASSWORD` ; vous pouvez donc aussi en définir un nouveau avec `php artisan db:seed --class=AdminUserSeeder`.

### Autres comptes créés par le seeder

`php artisan db:seed` crée également les comptes de démonstration suivants (ils ne sont pas créés par les seeders minimaux) :

| Rôle | E-mail | Mot de passe | Remarques |
| ---- | ------ | ------------ | --------- |
| Utilisateur | `test@example.com` | `password` | Utilisateur vérifié standard |
| Annonceur | `ads@carthage-books.test`, `communication@univ-sousse.test`, `contact@ecole-ibnkhaldoun.test`, `hello@green-tunisia.test`, `club@jeunes-savants.test` | `password` | Annonceurs d'exemple (un par type), issus de `AdvertiserDemoSeeder` ; visibles sous Administration → Publicité → Annonceurs |

Dix utilisateurs aléatoires supplémentaires sont créés avec le même `password`. Leurs données proviennent de `UserFactory`.

`OwnerUserSeeder` crée un compte joueur vérifié pour `mohammad.trabelsi@gmail.com`. Il ne fait partie d'aucun niveau de seeder ; lancez-le seul. Définissez `OWNER_PASSWORD` pour choisir le mot de passe, ou laissez-le vide pour qu'un mot de passe aléatoire soit généré et affiché une seule fois. Relancer le seeder conserve le mot de passe existant, sauf si `OWNER_PASSWORD` est défini.

```bash
OWNER_PASSWORD='choose-a-strong-password' php artisan db:seed --class=OwnerUserSeeder --force
```

## Couche de services

Chaque modèle est lu et écrit **uniquement via son propre service**. Aucun composant, contrôleur, commande, tâche, seeder ou autre service n'appelle un modèle de manière statique (`Post::query()`, `Post::create()`, `Post::find()`, ...) : il demande plutôt le service du modèle.

- Chaque service déclare le modèle qu'il possède et utilise le trait `App\Services\Concerns\ManagesModel`, qui fournit `query()`, `find()`, `findOrFail()`, `findBy()`, `all()`, `count()`, `create()`, `firstOrCreate()`, `updateOrCreate()`, `update()`, `save()`, `delete()`, `withTrashed()` et `onlyTrashed()`. Un service peut remplacer l'une de ces méthodes par une version propre au domaine (par ex. `PostService::create()` synchronise aussi les étiquettes).
- Les modèles transverses vivent dans `app/Services` (`PostService`, `UserService`, ...) ; les modèles de domaine ont leur service à côté, par ex. `App\Domain\Gameplay\GameService` pour `Game`, ou `QuestionService` pour `Question`.
- Les classes de domaine reçoivent les services dont elles ont besoin par leur constructeur ; les composants Livewire les injectent dans leurs actions ou les résolvent avec `app()`.
- `tests/Feature/Architecture/ServiceLayerTest.php` échoue lorsqu'un modèle n'a pas de service, ou lorsque du code extérieur à ce service interroge directement le modèle.

## Notes sur les fonctionnalités

Ces fonctionnalités marchent telles quelles, mais sont faciles à manquer dans le code.

- **Statistiques de visites.** `TrackVisit` enregistre les requêtes `GET` de pages réussies après l'envoi de la réponse, pour que le suivi ne ralentisse jamais une page. Il ignore la zone d'administration, `/api`, les requêtes Livewire, les changements de langue, la déconnexion, `/up` et les robots courants. Les totaux s'affichent sur les tableaux de bord d'administration et utilisateur.
- **Notifications CRUD.** `CrudNotificationObserver` surveille les modèles listés dans `config/crud_notifications.php` (articles, thèmes, utilisateurs et paramètres par défaut). À la création, la modification, la suppression ou la restauration, il enregistre une `CrudActionNotification` dans la table `notifications` pour chaque utilisateur ayant le rôle `admin`, sauf la personne à l'origine de la modification. Les mises à jour qui ne touchent que `updated_at`, `updated_by`, `remember_token` ou `deleted_at` sont ignorées, de sorte qu'une connexion « se souvenir de moi » ne notifie personne. Les administrateurs voient le nombre de notifications non lues dans la cloche de la barre supérieure et gèrent la liste sur `/admin/notifications` (filtrer, marquer une ou toutes comme lues). Pour surveiller un autre modèle, ajoutez-le à ce fichier de configuration.
- **Santé du système.** `SystemHealthService` exécute des sondes indépendantes pour la base, le cache et la file d'attente, et les affiche sur la carte de santé du tableau de bord. Une sonde en échec ne bloque pas la page.
- **Mode maintenance.** Activé depuis **Administration → Paramètres**. `EnforceMaintenanceMode` renvoie une page 503 aux visiteurs et du JSON sur `/api`. Les administrateurs, la page de connexion et de double authentification, le changement de langue et `/up` restent accessibles.
- **Format de date.** Les paramètres d'administration choisissent un format unique pour les dates affichées dans le journal d'activité et la liste des utilisateurs. Les choix sont définis dans `Setting::DATE_FORMATS`.
- **Images de couverture.** Les articles peuvent avoir une image de couverture, envoyée dans le formulaire d'article et stockée sur le disque `public`. Elle s'affiche sur `/blog` et sur la page de l'article. Lancez d'abord `php artisan storage:link`.
- **Plan du site XML.** `php artisan sitemap:generate` (`SitemapService`) liste les routes nommées de `config/sitemap.php` (`home`, `about`, `docs`, `blog`) et chaque article servi par `/blog/{slug}` (publié, non supprimé). Les URL sont construites à partir de `APP_URL`, ne contiennent jamais de paramètres de requête et sont dédoublonnées. `<lastmod>` correspond à `updated_at` de l'article (et au plus récent pour `/blog`) ; les pages statiques n'en ont pas, et `changefreq`/`priority` sont omis. Les fichiers sont générés, validés et écrits dans un fichier temporaire, puis renommés ; une exécution échouée laisse donc le sitemap précédent intact et la commande se termine par un code d'erreur. Au-delà de 50 000 URL, il écrit `sitemap-1.xml`, `sitemap-2.xml`, … derrière un index de sitemaps. Il est régénéré chaque heure (`SITEMAP_SCHEDULE`, `withoutOverlapping`), et `PostSitemapObserver` met en file un `GenerateSitemapJob` dédoublonné dès qu'un article publié change. Les routes d'authentification, de compte, d'administration, d'API et de langue ne sont jamais listées ; les thèmes n'ont pas de page publique, donc ils ne sont pas non plus listés. `/robots.txt` est servi par une closure de route dans `routes/web.php` et ajoute la ligne `Sitemap:` dès que le fichier existe.
- **E-mail de support.** Défini dans les paramètres d'administration et stocké avec les paramètres du site.
- **Joueurs invités.** Jouer ne nécessite pas de compte ; créer un salon, si. Sur `/play/join` (et les liens d'invitation `/join/{code}`), les visiteurs sans session saisissent un pseudo et le code du salon. `GuestPlayers` crée alors un utilisateur éphémère connecté, marqué `is_guest`, avec le rôle `player`, pour que le moteur de jeu continue de fonctionner sur les identifiants d'utilisateur. Le middleware `registered` (`EnsureUserIsRegistered`) déconnecte les invités et les envoie vers `/login` depuis chaque page de compte, y compris `/play` (créer un salon). `RoomService::create` les refuse aussi. Les invités sont exclus du classement, n'obtiennent aucun succès et n'ont pas de page de profil. `model:prune` les supprime une semaine après leur arrivée (planifié quotidiennement).

## Localisation

Les langues disponibles sont configurées dans `config/app.php`, sous `available_locales` :

```php
'available_locales' => [
    'en' => ['label' => 'English', 'dir' => 'ltr'],
    'ar' => ['label' => 'العربية', 'dir' => 'rtl'],
    'fr' => ['label' => 'Français', 'dir' => 'ltr'],
],
```

Le middleware `SetLocale` applique la langue choisie pour la session à chaque requête, la page Livewire `pages::locale` (`/language/{locale}`) gère le changement de langue, et le sens du texte (`ltr`/`rtl`) est déduit automatiquement pour un rendu RTL correct. Pour ajouter une langue, ajoutez une entrée ici et un dossier `lang/{locale}` avec les mêmes fichiers que `lang/en`.

`php artisan translations:sync` (`app/Console/Commands/SyncTranslationKeys.php`) analyse `resources/` à la recherche de clés de traduction, crée les fichiers de langue manquants et complète les clés absentes des autres langues. Options :

- `--locales=en,fr,ar` : langues à synchroniser (par défaut : toutes)
- `--base=en` : la langue de référence
- `--dry-run` : liste les modifications sans rien écrire
- `--clean` : supprime aussi les clés qu'aucune vue n'utilise

Les lots sont des tableaux imbriqués : `home.hero.title` est lu et écrit comme `['hero']['title']` dans `lang/{locale}/home.php`. Les clés construites à l'exécution (`__('about.stat_'.$key)`) ne sont pas de vraies clés et sont ignorées ; `--clean` conserve les clés sous un tel préfixe. Les valeurs non traduites sont préfixées par `[TODO]` pour être faciles à repérer et à compléter.

## Rôles et permissions

Les rôles et permissions sont semés par `RolesAndPermissionsSeeder`. Le rôle `admin` reçoit les permissions suivantes :

- `view admin dashboard`
- `manage users`
- `manage posts`
- `manage categories`
- `view activity log`
- `manage settings`
- `manage tags`
- `view system health`
- `manage backups`

Vérifiez l'accès administrateur dans le code avec `$user->isAdmin()` ou avec l'alias de middleware de route `admin`.

Les actions sur les articles et les thèmes passent en plus par `App\Policies\PostPolicy` et `App\Policies\CategoryPolicy`. Voir et créer des articles exige `manage posts`. Modifier, mettre à la corbeille, restaurer ou supprimer définitivement un article exige aussi que l'utilisateur en soit l'auteur, sauf s'il est administrateur. Les thèmes sont partagés : chaque action sur un thème exige seulement `manage categories`.

## Génération d'une nouvelle ressource

```bash
php artisan starter:make-crud Article
```

Cette commande crée un modèle `Article`, une migration, une fabrique, `ArticleService`, `ArticlePolicy` et `ArticleModuleTest`, avec suppression logique et journal d'activité. Elle n'écrase jamais les fichiers existants. Ensuite, ajoutez la permission `manage articles` à `RolesAndPermissionsSeeder`, puis ajoutez les routes, le contrôleur d'API et les écrans dont vous avez besoin.
