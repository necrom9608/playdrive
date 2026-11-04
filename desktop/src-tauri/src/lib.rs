mod commands {
    pub mod config;
    pub mod dirigera;
    pub mod rfid;
    pub mod updater;
}

use commands::config::{
    get_monitor_count,
    load_desktop_config,
    load_desktop_config_from_disk,
    normalize_desktop_config,
    open_configured_profile,
    open_playdrive_window,
    reset_desktop_config,
    save_desktop_config,
};
use commands::dirigera::{
    dirigera_list_outlets,
    dirigera_pair_finish,
    dirigera_pair_start,
    dirigera_set_outlet,
};
use commands::rfid::{cancel_rfid_scan, scan_rfid_once};
use commands::updater::{self, desktop_update_install, desktop_update_status, UpdateState};
use tauri::Manager;

fn should_force_setup() -> bool {
    std::env::args().any(|arg| arg == "--setup")
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .plugin(tauri_plugin_updater::Builder::new().build())
        .manage(UpdateState::default())
        .setup(|app| {
            let app_handle = app.handle().clone();

            if should_force_setup() {
                if let Some(main_window) = app_handle.get_webview_window("main") {
                    let _ = main_window.show();
                    let _ = main_window.set_focus();
                }
                return Ok(());
            }

            // Tijdens de werking af en toe kijken (eerste keer na 10 min) en op de achtergrond downloaden.
            updater::spawn_background_checks(app_handle.clone());

            if let Ok(Some(config)) = load_desktop_config_from_disk(&app_handle) {
                let normalized = normalize_desktop_config(config);

                // Auto-launch als toestelnaam ingesteld is — geen tenant of profiel meer nodig.
                // Eerst kijken of er een nieuwe versie is (dan meteen bijwerken), daarna de frontdesk.
                if !normalized.device_name.trim().is_empty() {
                    let handle = app_handle.clone();
                    tauri::async_runtime::spawn(async move {
                        if updater::install_on_startup(&handle).await {
                            return;
                        }
                        if open_playdrive_window(&handle, &normalized).is_err() {
                            if let Some(main_window) = handle.get_webview_window("main") {
                                let _ = main_window.show();
                                let _ = main_window.set_focus();
                            }
                        }
                    });
                    return Ok(());
                }
            }

            if let Some(main_window) = app_handle.get_webview_window("main") {
                let _ = main_window.show();
                let _ = main_window.set_focus();
            }

            Ok(())
        })
        .invoke_handler(tauri::generate_handler![
            load_desktop_config,
            save_desktop_config,
            reset_desktop_config,
            open_configured_profile,
            get_monitor_count,
            scan_rfid_once,
            cancel_rfid_scan,
            dirigera_pair_start,
            dirigera_pair_finish,
            dirigera_list_outlets,
            dirigera_set_outlet,
            desktop_update_status,
            desktop_update_install,
        ])
        .run(tauri::generate_context!())
        .expect("error while running Playdrive Desktop");
}
