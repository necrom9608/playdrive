use pcsc::{Card, Context, Protocols, ReaderState, Scope, ShareMode, State};
use std::sync::atomic::{AtomicBool, Ordering};
use std::time::Duration;
use tauri::AppHandle;

static RFID_SCAN_CANCELLED: AtomicBool = AtomicBool::new(false);

const GET_UID_APDU: [u8; 5] = [0xFF, 0xCA, 0x00, 0x00, 0x00];
const DEFAULT_TIMEOUT_MS: u64 = 15_000;
const POLL_INTERVAL_MS: u64 = 250;

#[tauri::command]
pub fn cancel_rfid_scan() -> Result<bool, String> {
    RFID_SCAN_CANCELLED.store(true, Ordering::SeqCst);
    Ok(true)
}

#[tauri::command]
pub fn scan_rfid_once(_app: AppHandle, timeout_ms: Option<u64>) -> Result<String, String> {
    RFID_SCAN_CANCELLED.store(false, Ordering::SeqCst);

    let context = Context::establish(Scope::User)
        .map_err(|error| format!("PC/SC context starten mislukt: {error}"))?;

    let mut readers_buf = [0; 2048];
    let readers = context
        .list_readers(&mut readers_buf)
        .map_err(|error| format!("Kaartlezers ophalen mislukt: {error}"))?;

    let reader_name = readers
        .into_iter()
        .next()
        .ok_or_else(|| "Geen NFC/RFID-kaartlezer gevonden.".to_string())?;

    let timeout = Duration::from_millis(timeout_ms.unwrap_or(DEFAULT_TIMEOUT_MS));
    let started_at = std::time::Instant::now();

    loop {
        if RFID_SCAN_CANCELLED.load(Ordering::SeqCst) {
            return Err("RFID-scan geannuleerd.".into());
        }

        if started_at.elapsed() >= timeout {
            return Err("Geen RFID-tag gedetecteerd binnen de wachttijd.".into());
        }

        match context.connect(reader_name, ShareMode::Shared, Protocols::ANY) {
            Ok(card) => {
                let uid = read_uid(&card)?;
                RFID_SCAN_CANCELLED.store(false, Ordering::SeqCst);
                return Ok(uid);
            }
            Err(_) => {
                std::thread::sleep(Duration::from_millis(200));
            }
        }
    }
}

fn read_uid(card: &Card) -> Result<String, String> {
    let mut rapdu_buf = [0; 258];
    let rapdu = card
        .transmit(&GET_UID_APDU, &mut rapdu_buf)
        .map_err(|error| format!("RFID UID lezen mislukt: {error}"))?;

    if rapdu.len() < 2 {
        return Err("Ongeldig antwoord van de kaartlezer.".into());
    }

    let status = &rapdu[rapdu.len() - 2..];
    if status != [0x90, 0x00] {
        return Err(format!(
            "RFID UID lezen mislukt (status {:02X}{:02X}).",
            status[0], status[1]
        ));
    }

    let uid_bytes = &rapdu[..rapdu.len() - 2];
    if uid_bytes.is_empty() {
        return Err("Geen UID teruggekregen van de RFID-tag.".into());
    }

    Ok(uid_bytes
        .iter()
        .map(|byte| format!("{:02X}", byte))
        .collect::<String>())
}
