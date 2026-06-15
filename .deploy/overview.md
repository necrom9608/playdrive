## Over PlayDrive
PlayDrive is een multi-tenant venue-managementplatform voor leisure-venues,
ontstaan bij Game-INN (de eerste tenant). Eén Laravel-backend bedient meerdere
surfaces, elk met een eigen Vue 3-app:
  admin · backoffice · client · display · frontdesk · kiosk · member ·
  portal · staff · website
Daarnaast een member-app (PWA + Capacitor voor Android/iOS) en een desktop-app
(Tauri) onder `desktop/`. De domeinlaag zit in `app/Domain/`
(Catalog · Orders · Pricing · Tenancy). Visie op termijn: een consumentgericht
netwerk waarbij gebruikers één centraal profiel houden over meerdere venues.