# Playdrive Desktop

Deze map bevat de eerste versie van de gedeelde Tauri desktop-shell voor Playdrive.

## Doel van fase 2

Deze desktop-app is **niet** exclusief voor frontdesk bedoeld. De structuur is opgezet als één gedeelde runtime met meerdere profielen:

- frontdesk
- kiosk
- staff
- client
- display

In deze fase doet de desktop-shell drie dingen:

1. lokale toestelconfig bewaren
2. een profiel kiezen
3. de juiste Playdrive-route openen in een apart Tauri-venster

## Wat er nog niet in zit

Deze basis bevat bewust nog geen:

- NFC-integratie
- printerintegratie
- offline queue of sync
- updater
- autostart

Die komen later bovenop dezelfde desktop-core.

## Installatie

Open een terminal in `desktop/` en voer uit:

```bash
npm install
npm run tauri:dev
```

## Eerste opstart

Bij eerste start kies je:

- server URL
- profiel
- toestelnaam
- device type
- fullscreen ja/nee

Daarna wordt dit lokaal bewaard in de Tauri app-configmap.

## Architectuurkeuze

Deze map is opgezet als aparte laag naast het Laravel/Vue-project. Daardoor blijft Playdrive Web onafhankelijk bestaan, terwijl Playdrive Desktop dezelfde webapps kan openen met native desktopfunctionaliteit erbovenop.
