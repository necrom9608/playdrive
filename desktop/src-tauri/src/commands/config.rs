use serde::{Deserialize, Serialize};
use std::fs;
use std::path::PathBuf;
use tauri::{AppHandle, Manager};
use url::Url;

#[derive(Debug, Clone, Serialize, Deserialize)]
#[serde(rename_all = "camelCase")]
pub struct DesktopConfig {
    #[serde(default)]
    pub server_url: String,
    #[serde(default)]
    pub tenant_slug: String,
    #[serde(default = "default_environment")]
    pub environment: String,
    #[serde(default = "default_profile")]
    pub profile: String,
    #[serde(default = "default_device_name")]
    pub device_name: String,
    #[serde(default = "default_device_type")]
    pub device_type: String,
    #[serde(default)]
    pub fullscreen: bool,
}

fn default_environment() -> String {
    "test".into()
}

fn default_profile() -> String {
    "frontdesk".into()
}

fn default_device_name() -> String {
    "Frontdesk 1".into()
}

fn default_device_type() -> String {
    "pos".into()
}

fn config_path(app: &AppHandle) -> Result<PathBuf, String> {
    let base_dir = app
        .path()
        .app_config_dir()
        .map_err(|error| format!("App config directory niet beschikbaar: {error}"))?;

    Ok(base_dir.join("desktop-config.json"))
}

fn derive_from_server_url(config: &mut DesktopConfig) {
    if !config.tenant_slug.trim().is_empty() {
        return;
    }

    let trimmed = config.server_url.trim().trim_end_matches('/');

    if trimmed.is_empty() {
        return;
    }

    if let Ok(parsed) = Url::parse(trimmed) {
        if let Some(host) = parsed.host_str() {
            if let Some(tenant) = host.strip_suffix(".playdrive.be") {
                config.tenant_slug = tenant.to_string();
                config.environment = "live".into();
                return;
            }

            if let Some(tenant) = host.strip_suffix(".playdrive.test") {
                config.tenant_slug = tenant.to_string();
                config.environment = "test".into();
            }
        }
    }
}

pub fn normalize_desktop_config(mut config: DesktopConfig) -> DesktopConfig {
    derive_from_server_url(&mut config);

    config.tenant_slug = config
        .tenant_slug
        .trim()
        .to_lowercase()
        .chars()
        .filter(|char| char.is_ascii_lowercase() || char.is_ascii_digit() || *char == '-')
        .collect::<String>();

    if config.environment != "live" && config.environment != "test" {
        config.environment = default_environment();
    }

    if config.profile.trim().is_empty() {
        config.profile = default_profile();
    }

    if config.device_name.trim().is_empty() {
        config.device_name = default_device_name();
    }

    if config.device_type.trim().is_empty() {
        config.device_type = default_device_type();
    }

    config.server_url = if config.tenant_slug.is_empty() {
        String::new()
    } else if config.environment == "live" {
        format!("https://{}.playdrive.be", config.tenant_slug)
    } else {
        format!("http://{}.playdrive.test", config.tenant_slug)
    };

    config
}

fn validate_config(config: &DesktopConfig) -> Result<(), String> {
    if config.tenant_slug.trim().is_empty() {
        return Err("Tenantnaam is verplicht.".into());
    }

    if config.environment != "live" && config.environment != "test" {
        return Err("Omgeving moet live of test zijn.".into());
    }

    if config.device_name.trim().is_empty() {
        return Err("Toestelnaam is verplicht.".into());
    }

    if config.device_type.trim().is_empty() {
        return Err("Device type is verplicht.".into());
    }

    if config.profile.trim().is_empty() {
        return Err("Profiel is verplicht.".into());
    }

    Ok(())
}

pub fn build_base_url(config: &DesktopConfig) -> Result<Url, String> {
    let base = if config.environment == "live" {
        format!("https://{}.playdrive.be", config.tenant_slug)
    } else {
        format!("http://{}.playdrive.test", config.tenant_slug)
    };

    Url::parse(&base).map_err(|error| format!("Basis URL ongeldig: {error}"))
}

pub fn build_launch_url(config: &DesktopConfig) -> Result<Url, String> {
    let mut base = build_base_url(config)?
        .to_string()
        .trim_end_matches('/')
        .to_string();

    let route = match config.profile.as_str() {
        "frontdesk" => "/frontdesk",
        "kiosk" => "/kiosk",
        "staff" => "/staff",
        "client" => "/client",
        "display" => "/display",
        _ => "",
    };

    base.push_str(route);

    Url::parse(&base).map_err(|error| format!("Launch URL ongeldig: {error}"))
}

pub fn load_desktop_config_from_disk(app: &AppHandle) -> Result<Option<DesktopConfig>, String> {
    let path = config_path(app)?;

    if !path.exists() {
        return Ok(None);
    }

    let content = fs::read_to_string(&path)
        .map_err(|error| format!("Configuratie lezen mislukt: {error}"))?;

    let raw = serde_json::from_str::<DesktopConfig>(&content)
        .map_err(|error| format!("Configuratie parseren mislukt: {error}"))?;

    Ok(Some(raw))
}

pub fn open_playdrive_window(app: &AppHandle, config: &DesktopConfig) -> Result<(), String> {
    let normalized = normalize_desktop_config(config.clone());
    validate_config(&normalized)?;
    let target = build_launch_url(&normalized)?;

    let main_window = app
        .get_webview_window("main")
        .ok_or_else(|| "Main window niet gevonden.".to_string())?;

    main_window
        .set_title(&format!("Playdrive - {}", normalized.device_name))
        .map_err(|error| format!("Titel instellen mislukt: {error}"))?;

    if normalized.fullscreen {
        main_window
            .set_fullscreen(true)
            .map_err(|error| format!("Fullscreen instellen mislukt: {error}"))?;

        main_window
            .set_resizable(false)
            .map_err(|error| format!("Resizable uitschakelen mislukt: {error}"))?;
    } else {
        main_window
            .set_fullscreen(false)
            .map_err(|error| format!("Fullscreen uitschakelen mislukt: {error}"))?;

        main_window
            .set_resizable(true)
            .map_err(|error| format!("Resizable inschakelen mislukt: {error}"))?;

        main_window
            .set_size(tauri::Size::Logical(tauri::LogicalSize {
                width: 1920.0,
                height: 1080.0,
            }))
            .map_err(|error| format!("Venstergrootte instellen mislukt: {error}"))?;

        main_window
            .center()
            .map_err(|error| format!("Venster centreren mislukt: {error}"))?;
    }

    main_window
        .show()
        .map_err(|error| format!("Main window tonen mislukt: {error}"))?;

    main_window
        .navigate(target)
        .map_err(|error| format!("Navigeren naar Playdrive mislukt: {error}"))?;

    main_window
        .set_focus()
        .map_err(|error| format!("Focus zetten op main window mislukt: {error}"))?;

    Ok(())
}

#[tauri::command]
pub fn load_desktop_config(app: AppHandle) -> Result<Option<DesktopConfig>, String> {
    match load_desktop_config_from_disk(&app)? {
        Some(config) => Ok(Some(normalize_desktop_config(config))),
        None => Ok(None),
    }
}

#[tauri::command]
pub fn save_desktop_config(
    app: AppHandle,
    config: DesktopConfig,
) -> Result<DesktopConfig, String> {
    let normalized = normalize_desktop_config(config);
    validate_config(&normalized)?;

    let path = config_path(&app)?;

    if let Some(parent) = path.parent() {
        fs::create_dir_all(parent)
            .map_err(|error| format!("Config map aanmaken mislukt: {error}"))?;
    }

    let payload = serde_json::to_string_pretty(&normalized)
        .map_err(|error| format!("Configuratie serialiseren mislukt: {error}"))?;

    fs::write(&path, payload)
        .map_err(|error| format!("Configuratie bewaren mislukt: {error}"))?;

    Ok(normalized)
}

#[tauri::command]
pub fn reset_desktop_config(app: AppHandle) -> Result<bool, String> {
    let path = config_path(&app)?;

    if path.exists() {
        fs::remove_file(path)
            .map_err(|error| format!("Configuratie verwijderen mislukt: {error}"))?;
    }

    Ok(true)
}

#[tauri::command]
pub fn open_configured_profile(app: AppHandle, config: DesktopConfig) -> Result<(), String> {
    let normalized = normalize_desktop_config(config);
    open_playdrive_window(&app, &normalized)?;
    Ok(())
}
