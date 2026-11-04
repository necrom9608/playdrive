// App-commands met eigen rechten (ACL): de frontdesk draait vanaf playdrive.be (remote) en mag
// enkel de commands gebruiken die in capabilities/remote.json staan. Zonder deze lijst weigert
// Tauri elk app-command vanaf een remote pagina ("not allowed by ACL").
// Nieuw command? Hier toevoegen én in capabilities/*.json (allow-<naam-met-streepjes>).
const COMMANDS: &[&str] = &[
    "load_desktop_config",
    "save_desktop_config",
    "reset_desktop_config",
    "open_configured_profile",
    "get_monitor_count",
    "scan_rfid_once",
    "cancel_rfid_scan",
    "dirigera_pair_start",
    "dirigera_pair_finish",
    "dirigera_list_outlets",
    "dirigera_set_outlet",
    "desktop_update_status",
    "desktop_update_install",
];

fn main() {
    tauri_build::try_build(
        tauri_build::Attributes::new().app_manifest(tauri_build::AppManifest::new().commands(COMMANDS)),
    )
    .expect("tauri-build mislukt");
}
