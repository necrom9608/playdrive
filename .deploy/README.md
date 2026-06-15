# .deploy/

Deze map bevat metadata van het deploy systeem en wordt automatisch onderhouden.
Niet handmatig wijzigen.

- `version` - huidige patch versie (bijv. V015) - WAARHEID, gecommit in git
- `changelog.md` - historiek van patches met omschrijving
- `context.txt` - prompt voor nieuwe Claude chat sessies
- `overview.md` - vast projectoverzicht, wordt in context.txt geinjecteerd

De .env van elke machine heeft `PATCH_VERSION` als spiegel van `version`.
Bij een nieuwe machine: na `git pull` is .deploy/version meteen correct,
en de watcher zet .env bij eerste start vanzelf gelijk.
