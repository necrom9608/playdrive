# booking-form v2 — Backoffice configurator

Voegt de backoffice configurator toe voor het reservatieformulier.
Bouwt verder op de datastructuur uit booking-form v1.

## Nieuwe bestanden

```
app/Http/Controllers/Api/Backoffice/
  BookingFormConfigController.php

resources/js/apps/backoffice/
  App.vue                                          ← aangepast (nav item toegevoegd)
  router/index.js                                  ← aangepast (route toegevoegd)
  components/ToggleSwitch.vue                      ← nieuw
  modules/booking-form/
    pages/BookingFormConfigPage.vue                ← nieuw
    services/bookingFormApi.js                     ← nieuw

routes/
  backoffice-api.patch.php                         ← geen echt bestand, zie instructies
```

## Routes toevoegen aan backoffice-api.php

Bovenaan het bestand, bij de andere `use`-statements:
```php
use App\Http\Controllers\Api\Backoffice\BookingFormConfigController;
```

Binnen het `Route::middleware('backoffice.auth')->group(...)` blok,
na de openingsuren-routes:
```php
// Reservatieformulier configuratie
Route::get('/booking-form-config', [BookingFormConfigController::class, 'index']);
Route::post('/booking-form-config', [BookingFormConfigController::class, 'save']);
```

## Wat de configurator doet

De pagina `/backoffice/booking-form` biedt vier secties:

**Algemeen**
- Formulier aan/uit zetten
- Buiten-uren waarschuwing aan/uit

**Persoonsgroepen**
- Kinderen / Volwassenen / Begeleiders aan/uit

**Event-types**
Per event-type:
- Zichtbaar in formulier of niet
- Doelgroepmodus: Geen / Kinderen+Volwassenen / Altijd volwassenen
- Per doelgroep: catering automatisch koppelen, gebruiker laten kiezen, of geen catering

**Verblijfsopties**
Per stay-option:
- Zichtbaar in formulier of niet
- Minimumomzet bij buiten-uren reservatie (in €, opgeslagen als eurocent)

## Volgende stap
Het reservatieformulier zelf (Vue component voor de website).
