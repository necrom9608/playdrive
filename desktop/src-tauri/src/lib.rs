mod commands {
    pub mod config;
    pub mod rfid;
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
use commands::rfid::{cancel_rfid_scan, scan_rfid_once};
use tauri::Manager;

fn should_force_setup() -> bool {
    std::env::args().any(|arg| arg == "--setup")
}

#[cfg_attr(mobile, tauri::mobile_entry_point)]
pub fn run() {
    tauri::Builder::default()
        .setup(|app| {
            let app_handle = app.handle().clone();

            if should_force_setup() {
                if let Some(main_window) = app_handle.get_webview_window("main") {
                    let _ = main_window.show();
                    let _ = main_window.set_focus();
                }
                return Ok(());
            }

            if let Ok(Some(config)) = load_desktop_config_from_disk(&app_handle) {
                let normalized = normalize_desktop_config(config);

                // Auto-launch als toestelnaam ingesteld is — geen tenant of profiel meer nodig.
                if !normalized.device_name.trim().is_empty() {
                    if open_playdrive_window(&app_handle, &normalized).is_ok() {
                        return Ok(());
                    }
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
        ])
        .run(tauri::generate_context!())
        .expect("error while running Playdrive Desktop");
}
