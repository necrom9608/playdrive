use serde::{Deserialize, Serialize};
use std::fs;
use std::path::PathBuf;
use tauri::{AppHandle, Manager};
use url::Url;

#[derive(Debug, Clone, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct DesktopConfig {
    // Legacy fields — kept for migration only, ignored in logic
    #[serde(default)]
    pub server_url: String,
    #[serde(default)]
    pub tenant_slug: String,
    #[serde(default)]
    pub profile: String,

    // Active fields
    #[serde(default = "default_environment")]
    pub environment: String,
    #[serde(default = "default_device_name")]
    pub device_name: String,
    #[serde(default = "default_device_type")]
    pub device_type: String,
    #[serde(default)]
    pub fullscreen: bool,
    #[serde(default)]
    pub display_enabled: bool,
    #[serde(default = "default_display_screen")]
    pub display_screen: u32,
    #[serde(default = "default_display_fullscreen")]
    pub display_fullscreen: bool,
    #[serde(default)]
    pub frontdesk_screen: u32,
}

fn default_environment() -> String { "test".into() }
fn default_device_name() -> String { "Frontdesk 1".into() }
fn default_device_type() -> String { "pos".into() }
fn default_display_screen() -> u32 { 1 }
fn default_display_fullscreen() -> bool { true }

fn config_path(app: &AppHandle) -> Result<PathBuf, String> {
    let base_dir = app
        .path()
        .app_config_dir()
        .map_err(|e| format!("App config directory niet beschikbaar: {e}"))?;
    Ok(base_dir.join("desktop-config.json"))
}

pub fn normalize_desktop_config(mut config: DesktopConfig) -> DesktopConfig {
    if config.environment != "live" && config.environment != "test" {
        config.environment = default_environment();
    }
    if config.device_name.trim().is_empty() {
        config.device_name = default_device_name();
    }
    if config.device_type.trim().is_empty() {
        config.device_type = default_device_type();
    }
    // Clear legacy fields — no longer used
    config.server_url = String::new();
    config.tenant_slug = String::new();
    config
}

fn validate_config(config: &DesktopConfig) -> Result<(), String> {
    if config.environment != "live" && config.environment != "test" {
        return Err("Omgeving moet live of test zijn.".into());
    }
    if config.device_name.trim().is_empty() {
        return Err("Toestelnaam is verplicht.".into());
    }
    Ok(())
}

/// Bouw de basis-URL zonder subdomein.
pub fn build_base_url(config: &DesktopConfig) -> Result<Url, String> {
    let base = if config.environment == "live" {
        "https://playdrive.be".to_string()
    } else {
        "http://playdrive.test".to_string()
    };
    Url::parse(&base).map_err(|e| format!("Basis URL ongeldig: {e}"))
}

pub fn build_launch_url(config: &DesktopConfig) -> Result<Url, String> {
    let base = build_base_url(config)?.to_string();
    let suffix = if config.display_enabled { "#local" } else { "" };
    let url = format!("{}/frontdesk{}", base.trim_end_matches('/'), suffix);
    Url::parse(&url).map_err(|e| format!("Launch URL ongeldig: {e}"))
}

fn build_display_url(config: &DesktopConfig) -> Result<Url, String> {
    let base = build_base_url(config)?.to_string();
    let url = format!("{}/display#local", base.trim_end_matches('/'));
    Url::parse(&url).map_err(|e| format!("Display URL ongeldig: {e}"))
}

/// Plaats een venster op de gewenste monitor.
/// Fullscreen = echte OS-fullscreen op die monitor.
/// Niet fullscreen = venster op volledige monitorgrootte, maar windowed.
/// Frontdesk standaard: 1920×1080. Display standaard: 1080×607.
fn position_window_on_monitor(
    window: &tauri::WebviewWindow,
    monitor_index: u32,
    fullscreen: bool,
    default_width: u32,
    default_height: u32,
) -> Result<(), String> {
    let monitors = window
        .available_monitors()
        .map_err(|e| format!("Monitoren ophalen mislukt: {e}"))?;

    let target = monitors
        .get(monitor_index as usize)
        .or_else(|| monitors.first());

    if fullscreen {
        // Fullscreen: positioneer op de gewenste monitor en activeer fullscreen
        if let Some(monitor) = target {
            let pos = monitor.position();
            window
                .set_position(tauri::Position::Physical(tauri::PhysicalPosition {
                    x: pos.x,
                    y: pos.y,
                }))
                .map_err(|e| format!("Positie instellen mislukt: {e}"))?;
        }
        window
            .set_fullscreen(true)
            .map_err(|e| format!("Fullscreen instellen mislukt: {e}"))?;
        window
            .set_resizable(false)
            .map_err(|e| format!("Resizable uitschakelen mislukt: {e}"))?;
    } else {
        // Windowed: vaste grootte (default_width × default_height), gecentreerd op de gewenste monitor
        window
            .set_fullscreen(false)
            .map_err(|e| format!("Fullscreen uitschakelen mislukt: {e}"))?;
        window
            .set_resizable(true)
            .map_err(|e| format!("Resizable inschakelen mislukt: {e}"))?;

        // LogicalSize: respecteert DPI-schaling op Windows.
        // Op een 1080p scherm met 100% scaling = exact 1920×1080 pixels.
        window
            .set_size(tauri::Size::Logical(tauri::LogicalSize {
                width: default_width as f64,
                height: default_height as f64,
            }))
            .map_err(|e| format!("Venstergrootte instellen mislukt: {e}"))?;

        // Centreren op de gekozen monitor
        if let Some(monitor) = target {
            let pos = monitor.position();
            let size = monitor.size();
            let scale = monitor.scale_factor();

            // size is in physical pixels, default_* in logical pixels → schaal de defaults
            let win_w_phys = (default_width as f64 * scale) as i32;
            let win_h_phys = (default_height as f64 * scale) as i32;

            let center_x = pos.x + (size.width as i32 - win_w_phys) / 2;
            let center_y = pos.y + (size.height as i32 - win_h_phys) / 2;

            window
                .set_position(tauri::Position::Physical(tauri::PhysicalPosition {
                    x: center_x,
                    y: center_y,
                }))
                .map_err(|e| format!("Positie instellen mislukt: {e}"))?;
        } else {
            window
                .center()
                .map_err(|e| format!("Venster centreren mislukt: {e}"))?;
        }
    }

    Ok(())
}

pub fn open_playdrive_window(app: &AppHandle, config: &DesktopConfig) -> Result<(), String> {

    let normalized = normalize_desktop_config(config.clone());
    validate_config(&normalized)?;

    let frontdesk_url = build_launch_url(&normalized)?;

    // ── Frontdesk venster ──
    let main_window = app
        .get_webview_window("main")
        .ok_or_else(|| "Main window niet gevonden.".to_string())?;

    main_window
        .set_title(&format!("Playdrive – {}", normalized.device_name))
        .map_err(|e| format!("Titel instellen mislukt: {e}"))?;

    main_window
        .show()
        .map_err(|e| format!("Main window tonen mislukt: {e}"))?;

    position_window_on_monitor(
        &main_window,
        normalized.frontdesk_screen,
        normalized.fullscreen,
        1920,
        1080,
    )?;

    main_window
        .navigate(frontdesk_url)
        .map_err(|e| format!("Navigeren naar Frontdesk mislukt: {e}"))?;

    main_window
        .set_focus()
        .map_err(|e| format!("Focus zetten op main window mislukt: {e}"))?;

    // ── Display venster (optioneel tweede scherm) ──
    // std::thread::spawn — niet de tauri async runtime — om absoluut geen blocking op
    // de invoke caller te krijgen. WebviewWindowBuilder::build() is sync en stuurt
    // intern een event naar de main loop; de main loop is vrij zodra wij returnen.
    if normalized.display_enabled {
        let app_handle = app.clone();
        let display_screen = normalized.display_screen;
        let display_fullscreen = normalized.display_fullscreen;
        let display_url = build_display_url(&normalized)?;

        std::thread::spawn(move || {
            // Geef de invoke-call tijd om te returnen en de event loop vrij te maken
            std::thread::sleep(std::time::Duration::from_millis(150));

            // Sluit bestaand display-venster indien aanwezig
            if let Some(existing) = app_handle.get_webview_window("display") {
                    let _ = existing.destroy();
                std::thread::sleep(std::time::Duration::from_millis(100));
            }

            let build_result = tauri::WebviewWindowBuilder::new(
                &app_handle,
                "display",
                tauri::WebviewUrl::External(display_url),
            )
            .title("Playdrive Display")
            .visible(false)
            .resizable(true)
            .fullscreen(false)
            .inner_size(607.0, 1080.0)
            .build();

            match build_result {
                Ok(display_window) => {
                    if let Err(e) = position_window_on_monitor(
                        &display_window,
                        display_screen,
                        display_fullscreen,
                        607,
                        1080,
                    ) {
                        eprintln!("[display] positioneren mislukt: {e}");
                    }

                    if let Err(e) = display_window.show() {
                        eprintln!("[display] tonen mislukt: {e}");
                    }

                }
                Err(e) => {
                    eprintln!("[display] window aanmaken mislukt: {e}");
                }
            }
        });
    }

    Ok(())
}

pub fn load_desktop_config_from_disk(app: &AppHandle) -> Result<Option<DesktopConfig>, String> {
    let path = config_path(app)?;
    if !path.exists() {
        return Ok(None);
    }
    let content = fs::read_to_string(&path)
        .map_err(|e| format!("Configuratie lezen mislukt: {e}"))?;
    let raw = serde_json::from_str::<DesktopConfig>(&content)
        .map_err(|e| format!("Configuratie parseren mislukt: {e}"))?;
    Ok(Some(raw))
}

#[tauri::command]
pub fn load_desktop_config(app: AppHandle) -> Result<Option<DesktopConfig>, String> {
    match load_desktop_config_from_disk(&app)? {
        Some(config) => Ok(Some(normalize_desktop_config(config))),
        None => Ok(None),
    }
}

#[tauri::command]
pub fn save_desktop_config(app: AppHandle, config: DesktopConfig) -> Result<DesktopConfig, String> {
    let normalized = normalize_desktop_config(config);
    validate_config(&normalized)?;

    let path = config_path(&app)?;
    if let Some(parent) = path.parent() {
        fs::create_dir_all(parent)
            .map_err(|e| format!("Config map aanmaken mislukt: {e}"))?;
    }

    let payload = serde_json::to_string_pretty(&normalized)
        .map_err(|e| format!("Configuratie serialiseren mislukt: {e}"))?;
    fs::write(&path, payload)
        .map_err(|e| format!("Configuratie bewaren mislukt: {e}"))?;

    Ok(normalized)
}

#[tauri::command]
pub fn reset_desktop_config(app: AppHandle) -> Result<bool, String> {
    let path = config_path(&app)?;
    if path.exists() {
        fs::remove_file(path)
            .map_err(|e| format!("Configuratie verwijderen mislukt: {e}"))?;
    }
    Ok(true)
}

#[tauri::command]
pub fn open_configured_profile(app: AppHandle, config: DesktopConfig) -> Result<(), String> {
    let normalized = normalize_desktop_config(config);
    open_playdrive_window(&app, &normalized)?;
    Ok(())
}

#[tauri::command]
pub fn get_monitor_count(app: AppHandle) -> Result<u32, String> {
    let main = app
        .get_webview_window("main")
        .ok_or_else(|| "Main window niet gevonden.".to_string())?;
    let monitors = main
        .available_monitors()
        .map_err(|e| format!("Monitoren ophalen mislukt: {e}"))?;
    Ok(monitors.len() as u32)
}
