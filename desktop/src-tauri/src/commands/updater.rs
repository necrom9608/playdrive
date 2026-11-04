// Automatisch bijwerken van Playdrive Desktop (tauri-plugin-updater).
//
// Releases: deploy/build-client.ps1 (Playmix Ops → Client bouwen) zet de ondertekende installer +
// latest.json in public/desktop/ van PlayDrive; "Commit, push & deploy" zet ze online op
// https://playdrive.be/desktop/latest.json (zie tauri.conf.json → plugins.updater).
//
//   - bij het opstarten: nieuwe versie? → meteen installeren, nog voor de frontdesk opent
//     (het hoofdvenster toont "Bijwerken…", de installer loopt in passive mode)
//   - tijdens de werking: elke 30 min kijken en op de achtergrond downloaden; de frontdesk
//     toont dan "Herstarten" (desktop_update_status / desktop_update_install). Nooit zelf
//     herstarten midden in de werking: anders gebeurt het bij de volgende start.

use serde::Serialize;
use std::sync::Mutex;
use std::time::Duration;
use tauri::{AppHandle, Emitter, Manager, State};
use tauri_plugin_updater::{Update, UpdaterExt};

const STARTUP_CHECK_TIMEOUT: Duration = Duration::from_secs(8);
const DOWNLOAD_TIMEOUT: Duration = Duration::from_secs(300);
const CHECK_EVERY: Duration = Duration::from_secs(30 * 60);
const FIRST_BACKGROUND_CHECK: Duration = Duration::from_secs(10 * 60);

#[derive(Default)]
pub struct UpdateState(Mutex<Pending>);

#[derive(Default)]
struct Pending {
    /// gedownloade update, klaar om te installeren
    ready: Option<(Update, Vec<u8>)>,
    /// checking | downloading | installing (voor het venster "Bijwerken…")
    phase: Option<String>,
    version: Option<String>,
    error: Option<String>,
}

#[derive(Serialize, Clone)]
#[serde(rename_all = "camelCase")]
pub struct UpdateStatus {
    pub current: String,
    /// versie die klaarstaat (gedownload), of null
    pub available: Option<String>,
    pub notes: Option<String>,
    /// versie waarnaar bijgewerkt wordt (tijdens downloading/installing)
    pub version: Option<String>,
    pub phase: Option<String>,
    pub error: Option<String>,
}

fn status_of(app: &AppHandle, p: &Pending) -> UpdateStatus {
    UpdateStatus {
        current: app.package_info().version.to_string(),
        available: p.ready.as_ref().map(|(u, _)| u.version.clone()),
        notes: p.ready.as_ref().and_then(|(u, _)| u.body.clone()),
        version: p.version.clone(),
        phase: p.phase.clone(),
        error: p.error.clone(),
    }
}

fn set_phase(app: &AppHandle, phase: Option<&str>, version: Option<String>) {
    let state = app.state::<UpdateState>();
    let status = {
        let mut p = state.0.lock().unwrap();
        p.phase = phase.map(String::from);
        if version.is_some() {
            p.version = version;
        }
        status_of(app, &p)
    };
    let _ = app.emit("desktop-update", status);
}

fn set_error(app: &AppHandle, error: String) {
    let state = app.state::<UpdateState>();
    let mut p = state.0.lock().unwrap();
    p.phase = None;
    p.error = Some(error);
}

async fn check(app: &AppHandle, timeout: Duration) -> Result<Option<Update>, String> {
    let updater = app
        .updater_builder()
        .timeout(timeout)
        .build()
        .map_err(|e| format!("Updater starten mislukt: {e}"))?;
    updater.check().await.map_err(|e| format!("Zoeken naar updates mislukt: {e}"))
}

async fn download(update: &Update) -> Result<Vec<u8>, String> {
    update
        .download(|_chunk, _total| {}, || {})
        .await
        .map_err(|e| format!("Update downloaden mislukt: {e}"))
}

/// Bij het opstarten (vóór de frontdesk opent). true = er wordt geïnstalleerd (de app sluit af).
pub async fn install_on_startup(app: &AppHandle) -> bool {
    // Kort wachten op het antwoord: geen internet mag het opstarten niet ophouden.
    let found = match check(app, STARTUP_CHECK_TIMEOUT).await {
        Ok(Some(update)) => update,
        Ok(None) => return false,
        Err(e) => {
            eprintln!("[updater] {e}");
            set_error(app, e);
            return false;
        }
    };

    // Opnieuw ophalen met een ruime timeout voor de download zelf.
    let update = match check(app, DOWNLOAD_TIMEOUT).await {
        Ok(Some(update)) => update,
        _ => found,
    };

    if let Some(main) = app.get_webview_window("main") {
        let _ = main.show();
        let _ = main.set_focus();
    }
    set_phase(app, Some("downloading"), Some(update.version.clone()));

    let bytes = match download(&update).await {
        Ok(bytes) => bytes,
        Err(e) => {
            eprintln!("[updater] {e}");
            set_error(app, e);
            return false;
        }
    };

    set_phase(app, Some("installing"), None);
    // Windows: de installer neemt over en deze app sluit af.
    match update.install(bytes) {
        Ok(()) => {
            app.restart();
        }
        Err(e) => {
            let msg = format!("Update installeren mislukt: {e}");
            eprintln!("[updater] {msg}");
            set_error(app, msg);
            false
        }
    }
}

/// Tijdens de werking: af en toe kijken en op de achtergrond downloaden.
pub fn spawn_background_checks(app: AppHandle) {
    tauri::async_runtime::spawn(async move {
        tokio_sleep(FIRST_BACKGROUND_CHECK).await;
        loop {
            let already = {
                let state = app.state::<UpdateState>();
                let p = state.0.lock().unwrap();
                p.ready.as_ref().map(|(u, _)| u.version.clone())
            };

            match check(&app, DOWNLOAD_TIMEOUT).await {
                Ok(Some(update)) if already.as_deref() != Some(update.version.as_str()) => {
                    match download(&update).await {
                        Ok(bytes) => {
                            let status = {
                                let state = app.state::<UpdateState>();
                                let mut p = state.0.lock().unwrap();
                                p.ready = Some((update, bytes));
                                p.error = None;
                                status_of(&app, &p)
                            };
                            let _ = app.emit("desktop-update", status);
                        }
                        Err(e) => set_error(&app, e),
                    }
                }
                Ok(_) => {}
                Err(e) => eprintln!("[updater] {e}"),
            }

            tokio_sleep(CHECK_EVERY).await;
        }
    });
}

async fn tokio_sleep(duration: Duration) {
    tokio::time::sleep(duration).await;
}

#[tauri::command]
pub fn desktop_update_status(app: AppHandle, state: State<'_, UpdateState>) -> UpdateStatus {
    let p = state.0.lock().unwrap();
    status_of(&app, &p)
}

/// Klaarstaande update nu installeren (de app sluit af en start bijgewerkt opnieuw).
#[tauri::command]
pub fn desktop_update_install(app: AppHandle, state: State<'_, UpdateState>) -> Result<(), String> {
    let ready = state.0.lock().unwrap().ready.take();
    let Some((update, bytes)) = ready else {
        return Err("Er staat geen update klaar.".into());
    };
    set_phase(&app, Some("installing"), Some(update.version.clone()));
    if let Some(main) = app.get_webview_window("main") {
        let _ = main.show();
    }
    update.install(bytes).map_err(|e| {
        let msg = format!("Update installeren mislukt: {e}");
        set_error(&app, msg.clone());
        msg
    })?;
    app.restart();
}
