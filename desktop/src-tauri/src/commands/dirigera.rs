// IKEA DIRIGERA-hubs in het lokale netwerk (slimme stekkers voor Zaal openen/sluiten).
//
// PlayDrive draait in de cloud en kan de hubs niet bereiken; de kassa-app wel. De frontdesk
// (Zaal) roept deze commands aan; PlayDrive bewaart enkel het token en de koppeling stekker ↔
// speelplek.
//
// API van de hub (lokaal, https://<ip>:8443/v1, zelfondertekend certificaat):
//   koppelen  GET  /oauth/authorize?audience=homesmart.local&response_type=code
//                  &code_challenge=<S256>&code_challenge_method=S256        → { code }
//             (knop onderaan de hub indrukken)
//             POST /oauth/token  code, name, grant_type=authorization_code,
//                  code_verifier                                           → { access_token }
//   lezen     GET  /devices                       (Bearer)                   → [ { id, deviceType, isReachable, attributes: { customName, isOn, currentActivePower?, model }, room: { name } } ]
//   schakelen PATCH /devices/<id>  [ { "attributes": { "isOn": true } } ]   → 202

use base64::{engine::general_purpose::URL_SAFE_NO_PAD, Engine as _};
use rand::{distributions::Alphanumeric, Rng};
use serde::Serialize;
use serde_json::Value;
use sha2::{Digest, Sha256};
use std::time::Duration;

const PORT: u16 = 8443;
const CLIENT_NAME: &str = "Playdrive Frontdesk";

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct PairStart {
    pub code: String,
    pub verifier: String,
}

#[derive(Debug, Serialize)]
#[serde(rename_all = "camelCase")]
pub struct PairResult {
    /// false = knop nog niet ingedrukt, opnieuw proberen.
    pub done: bool,
    pub token: Option<String>,
}

#[derive(Debug, Serialize, PartialEq)]
#[serde(rename_all = "camelCase")]
pub struct Outlet {
    pub id: String,
    pub name: String,
    pub room: Option<String>,
    pub model: Option<String>,
    pub is_on: Option<bool>,
    pub reachable: Option<bool>,
    pub power_w: Option<f64>,
}

fn client() -> Result<reqwest::Client, String> {
    reqwest::Client::builder()
        // De hub gebruikt een zelfondertekend certificaat.
        .danger_accept_invalid_certs(true)
        .timeout(Duration::from_secs(8))
        .connect_timeout(Duration::from_secs(4))
        .build()
        .map_err(|e| format!("HTTP-client starten mislukt: {e}"))
}

/// Enkel IP-adres of hostnaam (geen schema, poort of pad).
fn base_url(host: &str) -> Result<String, String> {
    let host = host.trim();
    let valid = !host.is_empty()
        && host.len() <= 255
        && host.chars().all(|c| c.is_ascii_alphanumeric() || c == '.' || c == '-');
    if !valid {
        return Err(format!("Ongeldig adres voor de hub: {host}"));
    }
    Ok(format!("https://{host}:{PORT}/v1"))
}

fn network_error(host: &str, e: reqwest::Error) -> String {
    if e.is_timeout() || e.is_connect() {
        format!("Hub {host} niet bereikbaar. Staat hij aan en in hetzelfde netwerk als de kassa?")
    } else {
        format!("Hub {host}: {e}")
    }
}

fn random_verifier() -> String {
    rand::thread_rng()
        .sample_iter(&Alphanumeric)
        .take(128)
        .map(char::from)
        .collect()
}

fn challenge_for(verifier: &str) -> String {
    URL_SAFE_NO_PAD.encode(Sha256::digest(verifier.as_bytes()))
}

/// Eén toestel uit GET /devices omzetten; None als het geen stekker is.
fn parse_outlet(device: &Value) -> Option<Outlet> {
    let kind = device
        .get("deviceType")
        .or_else(|| device.get("type"))
        .and_then(Value::as_str)
        .unwrap_or_default();
    if kind != "outlet" {
        return None;
    }

    let id = device.get("id")?.as_str()?.to_string();
    let attrs = device.get("attributes").cloned().unwrap_or(Value::Null);
    let text = |v: Option<&Value>| {
        v.and_then(Value::as_str)
            .map(str::trim)
            .filter(|s| !s.is_empty())
            .map(String::from)
    };

    let model = text(attrs.get("model"));
    let name = text(attrs.get("customName"))
        .or_else(|| model.clone())
        .unwrap_or_else(|| "Stekker".to_string());

    Some(Outlet {
        id,
        name,
        room: text(device.get("room").and_then(|r| r.get("name"))),
        model,
        is_on: attrs.get("isOn").and_then(Value::as_bool),
        reachable: device.get("isReachable").and_then(Value::as_bool),
        power_w: attrs.get("currentActivePower").and_then(Value::as_f64),
    })
}

pub fn parse_outlets(devices: &Value) -> Vec<Outlet> {
    devices
        .as_array()
        .map(|list| list.iter().filter_map(parse_outlet).collect())
        .unwrap_or_default()
}

#[tauri::command]
pub async fn dirigera_pair_start(host: String) -> Result<PairStart, String> {
    let base = base_url(&host)?;
    let verifier = random_verifier();
    let challenge = challenge_for(&verifier);

    let response = client()?
        .get(format!("{base}/oauth/authorize"))
        .query(&[
            ("audience", "homesmart.local"),
            ("response_type", "code"),
            ("code_challenge", challenge.as_str()),
            ("code_challenge_method", "S256"),
        ])
        .send()
        .await
        .map_err(|e| network_error(&host, e))?;

    if !response.status().is_success() {
        return Err(format!("Hub {host} weigerde het koppelen ({}).", response.status()));
    }

    let body: Value = response
        .json()
        .await
        .map_err(|e| format!("Onverwacht antwoord van de hub: {e}"))?;
    let code = body
        .get("code")
        .and_then(Value::as_str)
        .ok_or_else(|| "De hub gaf geen koppelcode terug.".to_string())?
        .to_string();

    Ok(PairStart { code, verifier })
}

/// Na het indrukken van de knop: token ophalen. done = false zolang de knop niet ingedrukt is.
#[tauri::command]
pub async fn dirigera_pair_finish(host: String, code: String, verifier: String) -> Result<PairResult, String> {
    let base = base_url(&host)?;

    let response = client()?
        .post(format!("{base}/oauth/token"))
        .form(&[
            ("code", code.as_str()),
            ("name", CLIENT_NAME),
            ("grant_type", "authorization_code"),
            ("code_verifier", verifier.as_str()),
        ])
        .send()
        .await
        .map_err(|e| network_error(&host, e))?;

    let status = response.status();
    if status.is_client_error() {
        // Knop nog niet ingedrukt (de hub antwoordt dan met een 4xx).
        return Ok(PairResult { done: false, token: None });
    }
    if !status.is_success() {
        return Err(format!("Koppelen mislukt ({status})."));
    }

    let body: Value = response
        .json()
        .await
        .map_err(|e| format!("Onverwacht antwoord van de hub: {e}"))?;
    let token = body
        .get("access_token")
        .and_then(Value::as_str)
        .ok_or_else(|| "De hub gaf geen token terug.".to_string())?
        .to_string();

    Ok(PairResult { done: true, token: Some(token) })
}

#[tauri::command]
pub async fn dirigera_list_outlets(host: String, token: String) -> Result<Vec<Outlet>, String> {
    let base = base_url(&host)?;

    let response = client()?
        .get(format!("{base}/devices"))
        .bearer_auth(token.trim())
        .send()
        .await
        .map_err(|e| network_error(&host, e))?;

    let status = response.status();
    if status == reqwest::StatusCode::UNAUTHORIZED || status == reqwest::StatusCode::FORBIDDEN {
        return Err(format!("Hub {host} kent de kassa niet meer. Koppel de hub opnieuw."));
    }
    if !status.is_success() {
        return Err(format!("Hub {host}: stekkers uitlezen mislukt ({status})."));
    }

    let body: Value = response
        .json()
        .await
        .map_err(|e| format!("Onverwacht antwoord van de hub: {e}"))?;

    Ok(parse_outlets(&body))
}

#[tauri::command]
pub async fn dirigera_set_outlet(host: String, token: String, id: String, on: bool) -> Result<(), String> {
    let base = base_url(&host)?;
    if id.trim().is_empty() || id.contains('/') {
        return Err("Ongeldige stekker.".into());
    }

    let response = client()?
        .patch(format!("{base}/devices/{}", id.trim()))
        .bearer_auth(token.trim())
        .json(&serde_json::json!([{ "attributes": { "isOn": on } }]))
        .send()
        .await
        .map_err(|e| network_error(&host, e))?;

    let status = response.status();
    if status == reqwest::StatusCode::UNAUTHORIZED || status == reqwest::StatusCode::FORBIDDEN {
        return Err(format!("Hub {host} kent de kassa niet meer. Koppel de hub opnieuw."));
    }
    if !status.is_success() {
        return Err(format!("Stekker schakelen mislukt ({status})."));
    }

    Ok(())
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn challenge_is_base64url_without_padding() {
        // RFC 7636, bijlage B
        assert_eq!(
            challenge_for("dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"),
            "E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM"
        );
        assert_eq!(random_verifier().len(), 128);
    }

    #[test]
    fn host_validation() {
        assert!(base_url("192.168.1.50").is_ok());
        assert!(base_url("gw2-abc.local").is_ok());
        assert!(base_url("https://1.2.3.4").is_err());
        assert!(base_url("1.2.3.4:8443").is_err());
        assert!(base_url("").is_err());
    }

    #[test]
    fn parses_only_outlets() {
        let body = serde_json::json!([
            {
                "id": "abc_1", "type": "outlet", "deviceType": "outlet", "isReachable": true,
                "attributes": { "customName": "LAN-PC 1", "isOn": true, "currentActivePower": 87.4, "model": "INSPELNING Smart plug" },
                "room": { "id": "r1", "name": "LAN" }
            },
            {
                "id": "abc_2", "deviceType": "outlet", "isReachable": false,
                "attributes": { "customName": "", "isOn": false, "model": "TRETAKT Smart plug" }
            },
            { "id": "lamp", "deviceType": "light", "attributes": { "customName": "Lamp" } }
        ]);
        let outlets = parse_outlets(&body);
        assert_eq!(outlets.len(), 2);
        assert_eq!(outlets[0].name, "LAN-PC 1");
        assert_eq!(outlets[0].room.as_deref(), Some("LAN"));
        assert_eq!(outlets[0].power_w, Some(87.4));
        assert_eq!(outlets[1].name, "TRETAKT Smart plug");
        assert_eq!(outlets[1].power_w, None);
        assert_eq!(outlets[1].reachable, Some(false));
    }
}
