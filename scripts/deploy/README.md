# scripts/deploy/

In-project deploy systeem voor playdrive. Detecteert nieuwe patch-zips
en deployt ze automatisch.

## Snel beginnen

In PhpStorm: open de **npm-tab** (rechtsboven) en dubbelklik op
`deploy:watch`.

Of vanuit terminal:

```powershell
npm run deploy:watch
```

De watcher draait nu en kijkt naar:

1. `deploy/incoming/` (in dit project)
2. Je Windows Downloads-folder

Zodra een zip met naam `playdrive-update-V###.zip` verschijnt op een van
beide locaties, wordt die automatisch gedeployed.

## Folder layout

```
playdrive/
├── scripts/deploy/          (in git)
│   ├── watcher.ps1
│   ├── deploy.ps1
│   └── git-helpers.ps1
├── deploy/                  (lokaal, in .gitignore)
│   ├── incoming/            <- zips landen hier
│   ├── deployed/            <- na succes (.deployed.zip)
│   ├── failed/              <- na fout
│   ├── backups/             <- voor rollback
│   └── watcher.log
└── .deploy/                 (in git)
    ├── version              <- waarheid: V015
    ├── changelog.md
    ├── context.txt
    └── overview.md          <- vast projectoverzicht voor context.txt
```

## Versie-bron

- **`.deploy/version`** is de waarheid. Wordt mee-gecommit in git en
  syncroniseert dus tussen PCs.
- **`.env PATCH_VERSION`** is een spiegel. Bij watcher-start wordt deze
  stilletjes gelijkgezet met `.deploy/version` zodat je 'm in `.env`
  ziet zonder dat hij ooit uit de pas loopt.

Concreet: deploy je V016 op PC1, push je naar GitHub. Op PC2 doe je
`git pull`, start je `npm run deploy:watch`. De watcher merkt dat
`.deploy/version=V016` maar `.env` nog op V015 staat → werkt `.env` bij.
Geen handmatige actie nodig.

## Wat de deploy doet (9 stappen)

1. **Git pull** (`origin master`)
2. **Zip uitpakken** in `%TEMP%`
3. **update-info.md inlezen**
4. **Backup** van bestaande files naar `deploy/backups/V###-pre-V###/`
5. **Pre-deploy hook** (optioneel: `_hooks/pre-deploy.ps1` in zip)
6. **Bestanden kopiëren** naar projectroot
7. **Migrations + seeders** (interactief, tenzij auto)
8. **Build** (`npm run build`)
9. **Git commit** (lokaal, push doe je zelf via PhpStorm)

Na een geslaagde deploy worden `.deploy/version`, `.deploy/changelog.md`
en `.deploy/context.txt` automatisch bijgewerkt. Bij elke fout: rollback
naar de backup en zip naar `deploy/failed/`.

## .env opties

Optioneel in `.env` te zetten — defaults staan tussen haakjes:

```dotenv
PATCH_VERSION=V015                          # spiegel, automatisch beheerd
APP_VERSION=0.1.0                           # toont in context.txt (anders "onbekend")

DEPLOY_GIT_BRANCH=master                    # (master)
DEPLOY_GIT_AUTOPULL_ON_START=true           # (true)
DEPLOY_GIT_AUTOPULL_BEFORE_DEPLOY=true      # (true)
DEPLOY_GIT_AUTOCOMMIT=true                  # (true)

DEPLOY_AUTO_MIGRATE=false                   # (false) interactief vragen
DEPLOY_AUTO_SEED=false                      # (false) interactief vragen

DEPLOY_BUILD_COMMAND="npm run build"        # (npm run build)
DEPLOY_WATCH_DOWNLOADS=true                 # (true) auto-move uit Downloads
DEPLOY_DOWNLOADS_PATH=C:\custom\path        # (gebruikt USERPROFILE\Downloads)
```

## Eerste keer op een nieuwe PC

```powershell
# In de projectfolder
git pull
npm install
npm run deploy:watch
```

De watcher maakt zelf de `deploy/` werk-folder aan en synchroniseert
`.env` met `.deploy/version`. Klaar.

## Standalone deploy (zonder watcher)

```powershell
pwsh scripts/deploy/deploy.ps1 -ZipPath C:\path\to\playdrive-update-V016.zip
```

## Logs

Alle activiteit staat in `deploy/watcher.log`.
