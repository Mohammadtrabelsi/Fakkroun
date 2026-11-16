# Fakkroun (فكّرون)

**Think, Answer, Learn, Win! · Penser, répondre, apprendre, gagner ! · فكّر، أجب، تعلّم، اربح!**

Fakkroun is a real-time multilingual quiz platform for the green economy, the blue economy and environmental awareness. The project documentation is available in three languages:

| Language | Documentation |
| --- | --- |
| English | [`docs/en/README.md`](docs/en/README.md) |
| Français | [`docs/fr/README.md`](docs/fr/README.md) |
| العربية | [`docs/ar/README.md`](docs/ar/README.md) |

Each language folder holds the same two documents: the project guide (`README.md`) and the MajidaStarter kit reference (`starter-kit.md`).

## Quick start

```bash
composer setup
php artisan db:seed --class=ConfigDatabaseSeeder
php artisan storage:link
composer dev
```

The full instructions, including the admin sign-in and configuration, are in the guide of your language.

## Other documentation

Technical reports, the roadmap and the deployment guide are in English under [`docs/fakkroun/`](docs/fakkroun). Integration notes are in [`docs/spatie-integrations.md`](docs/spatie-integrations.md). The Docker setup is in [`README.docker.md`](README.docker.md).
