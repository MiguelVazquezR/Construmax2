# Attendance kiosk — Android app specification (Flutter)

> **Audience:** the Flutter agent/developer building the native Android kiosk app that replaces the
> browser kiosk (`/attendance/kiosk`). This file is **self-contained**: it holds the backend
> contract, the client behaviour, the exact Spanish texts and the Android kiosk hardening rules.
> **The app needs no device authorization**: the APK is installed manually, so any tablet can punch.
> **Backend counterpart:** `routes/api-kiosk.php`, `app/Http/Controllers/Api/V1/KioskController.php`,
> `app/Actions/Payroll/*` and `tests/Feature/Payroll/ApiKioskTest.php`.

---

## 1. What the app does

A tablet fixed at the entrance of a workplace lets collaborators register their attendance:

1. The app needs **no device authorization and no login**: it opens and loads the kiosk context by
   itself (§3).
2. The collaborator picks the **punch type** (entrada, inicio de comida, …).
3. The collaborator identifies themselves with **their face** (camera → AWS Rekognition 1:N) or with
   **their employee number** (keypad).
4. A punch is stored with the type, the time, the collaborator, the device and the **GPS location**
   (mandatory evidence), plus the face capture when the face method was used.

The app is a **kiosk**: it boots into the punch screen, cannot be closed by the collaborator, never
shows the employee password nor the ERP UI, and keeps working after a reboot.

---

## 2. Base URL and environments

| Environment | Base URL |
|-------------|----------|
| Local (Laravel `php artisan serve`) | `http://localhost:8000/api/v1/kiosk` |
| Any deployment | `{APP_URL}/api/v1/kiosk` |

The app must store the base URL in a build-time constant (`--dart-define=KIOSK_BASE_URL=...`), never
hard-coded in the screens. **Do not send the trailing slash**; build every path as
`$baseUrl/punch`, `$baseUrl/ping`, etc.

Every request must send:

```http
Accept: application/json
Content-Type: application/json
```

There is **no token and no login** (§3). The server also forces `Accept: application/json`, so every
error, including a `422` or a `429`, comes back as JSON (never an HTML page).

---

## 3. Authentication model

### 3.1 There is no device authorization

The backend does **not** require the tablet to be registered. The APK is installed manually by the
company, so the distribution is already controlled and **any tablet running the app can punch**:

* **No token, no registration password, no setup screen.** The app opens straight into the kiosk.
* **No endpoint answers `403`.** A punch is never rejected because of the device.
* The web kiosk (`/attendance/kiosk`) is a different client and keeps its own device authorization:
  do not copy that behaviour into the app.

### 3.2 Device identification (`device_key`) — optional, recommended

Send the **Android ID** (`device_info_plus` → `AndroidDeviceInfo.id`) as `device_key` and the model
(`AndroidDeviceInfo.model`) as `device_name` in `/bootstrap`, `/punch` and `/face-punch`. The server
uses them only to keep every punch traceable:

* The first contact creates an `attendance_devices` row named `Tablet <model>`, which the
  administrator can rename in *Recursos Humanos → Dispositivos de asistencia*.
* Later requests reuse that row, so the ERP knows which tablet registered each punch.
* Omitting both fields still works: the punch is stored with no device.

`device_key` is not a secret and grants nothing: it only identifies the tablet.

### 3.3 Optional endpoints (not needed by the app)

`/devices/register` and `/recover` belong to the **web** kiosk: they give a device a friendly name
and a location using the shared registration password. The app may ignore both. If it ever calls
them, it must keep the returned `token` and send it in the `X-Attendance-Device` header; with a valid
token `/ping` and `/bootstrap` return the device object instead of `null`.

### 3.4 No credentials to store

The app stores **no device token and no password**: `flutter_secure_storage` is only used for the
optional operator PIN of the hidden settings screen (§8).

### 3.5 Flow summary

```
   app start ──▶ GET /ping ──▶ 200 ──▶ POST /bootstrap ──▶ kiosk screen
        │
        └── network error / 5xx / timeout ──▶ unreachable screen (retry every 15 s)
```

---

## 4. Endpoints

| Method | Path | Auth | Throttle | Purpose |
|--------|------|------|----------|---------|
| `GET`  | `/ping` | none | 240/min per IP | Connectivity probe, clock sync |
| `POST` | `/bootstrap` | none | 120/min per IP | Kiosk context: device, punch types, settings |
| `POST` | `/punch` | none | 60/min per IP | Punch by employee number |
| `POST` | `/face-punch` | none | 60/min per IP | Punch by face |
| `POST` | `/devices/register` | optional (§3.3) | 10/min per IP | Register the tablet in the ERP (web kiosk) |
| `POST` | `/recover` | optional (§3.3) | 20/min per IP | Recover an ERP registration (web kiosk) |

**No endpoint requires a device token and none answers `403`** (§3.1).

Throttles are **per IP** (all tablets behind the same NAT share them) and the platform also applies
its global `60/minute` API limit. Treat **429** as “ask the collaborator to wait a few seconds and try
again”; never retry automatically in a loop.

### 4.1 `GET /ping`

```json
{
  "status": "ok",
  "device": { "id": 7, "name": "Tablet entrada", "location": "Recepción" },
  "server_time": "2026-11-07T09:14:22-06:00"
}
```

`device` is `null` unless the tablet identified itself with a valid device token (a case the app
never needs, see §3.3).

* Use it on start-up, after returning from the background and then every **60 s** while idle.
* Compare `server_time` with the device clock; if they differ by more than 2 minutes show
  `"La fecha y hora del dispositivo no son correctas. Pide al administrador que las ajuste."`
  (the punch times are stamped by the server, so a wrong device clock is a warning, not a blocker).

### 4.2 `POST /bootstrap`

Request body (optional but recommended, §3.2):

```json
{ "device_key": "9774d56d682e549c", "device_name": "SM-T500" }
```

Response `200`:

```json
{
  "device": { "id": 7, "name": "Tablet entrada", "location": "Recepción" },
  "punchTypes": {
    "check_in": "Entrada",
    "lunch_start": "Inicio de comida",
    "lunch_end": "Fin de comida",
    "break_start": "Permiso / salida",
    "break_end": "Regreso de permiso",
    "check_out": "Salida"
  },
  "faceRecognitionEnabled": true,
  "employeeNumberEnabled": true,
  "registrationUrl": "https://erp.example.com/attendance/devices/register",
  "recoveryPasswordConfigured": true,
  "appName": "Construmax",
  "server_time": "2026-11-07T09:14:22-06:00",
  "timezone": "America/Mexico_City"
}
```

| Field | Meaning for the app |
|-------|---------------------|
| `device` | The tablet row the server created or recognized from `device_key` (`null` when the app did not send it). Show `name` and `location` in the header; fall back to `Tablet Android` / no subtitle when it is `null`. |
| `punchTypes` | Ordered `key → label` map. **Render the labels, send the keys.** Never hard-code the list: read it from here. |
| `faceRecognitionEnabled` | `false` → hide the face option (the biometric provider is not configured). |
| `employeeNumberEnabled` | `false` → hide the employee-number option. When **both** are `false`, show `"El kiosco no tiene un método de registro activo. Pide ayuda al administrador."` |
| `registrationUrl` | Informational only (web fallback / QR in the hidden settings screen). The app does not need it. |
| `recoveryPasswordConfigured` | Informational only: whether the web kiosk may register new devices. The app ignores it. |
| `server_time`, `timezone` | Clock check and the “today” label. |

Call it right after `/ping` succeeds, and again every **5 minutes** so a settings change on the ERP
reaches the tablet without restarting the app.

### 4.3 `POST /punch` — employee number

Request:

```json
{
  "employee_number": "EMP-0100",
  "type": "check_in",
  "latitude": 20.676,
  "longitude": -103.347,
  "location_accuracy": 12.5,
  "photo": "data:image/jpeg;base64,..."
}
```

| Field | Required | Notes |
|-------|----------|-------|
| `employee_number` | yes | Max 50 chars. Matched **case-insensitively** against `payroll_profiles.employee_number`. |
| `type` | yes | One of the `punchTypes` keys. |
| `latitude` | **yes** | `-90..90`. A punch without location is rejected by design (evidence). |
| `longitude` | **yes** | `-180..180`. |
| `location_accuracy` | no | Meters from the GPS fix. Send it when available. |
| `photo` | no | Optional evidence snapshot (same encoding as `face-punch`). Omit it when the method is the employee number. |
| `device_key` | no | Android ID (§3.2). Identifies the tablet in the ERP. |
| `device_name` | no | Device model (§3.2). Used to name the tablet on its first contact. |

Response `200`:

```json
{
  "user_name": "Juan Pérez",
  "employee_number": "EMP-0100",
  "type": "check_in",
  "type_label": "Entrada",
  "punched_at": "09:14",
  "date_label": "07/11/2026",
  "suggested_next": "lunch_start",
  "suggested_next_label": "Inicio de comida"
}
```

### 4.4 `POST /face-punch` — face

Request:

```json
{
  "type": "check_in",
  "photo": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "latitude": 20.676,
  "longitude": -103.347,
  "location_accuracy": 12.5,
  "device_key": "9774d56d682e549c",
  "device_name": "SM-T500"
}
```

Same response as `/punch` plus:

```json
{ "similarity": 98.42 }
```

(`similarity` is a **0–100** percentage reported by AWS Rekognition. Show it only in the debug /
operator settings screen, never to the collaborator.)

**Photo rules (both endpoints):**

* `photo` must be a **base64 data URL**: `data:image/jpeg;base64,<payload>`.
* Format **JPEG**, quality **70–75**, longest side **max 1024 px**, EXIF orientation already applied.
* Hard limit: **2 000 000 characters** of the whole string (~1.5 MB of JPEG). Validate client-side and
  re-compress before sending; the server answers 422 `photo.max` otherwise.
* Only **one face** must be in the frame. Send the frame **without** pre-processing (no filters, no
  crop tighter than the head + shoulders).
* Never queue a photo for later: a punch is only valid at the time it is made.

### 4.5 `POST /recover` (optional — web kiosk)

Not needed by the app (§3.3). Used by the web kiosk when a device lost its token but was already
registered.

Request:

```json
{ "device_key": "9774d56d682e549c", "password": "kiosco-2026", "device_id": 7 }
```

| Field | Required | Notes |
|-------|----------|-------|
| `device_key` | yes | Android ID. |
| `password` | no | Registration password. Omit it for the first attempt. |
| `device_id` | no | Only after the server replied `choose_device`: the operator picks the row. |

Responses (all `200`, discriminate by `status`):

| `status` | Body highlights | What the client does |
|----------|-----------------|----------------------|
| `authorized` | `token`, `device` | Store the token (the web kiosk keeps it in `localStorage`). The previous token is dead. |
| `password_required` | `device` (may be `null`), `message` | Show the password field with `message`. Retry the same call **with** `password`. |
| `choose_device` | `devices: [{id, name, location}]`, `message` | Show a list; the operator selects one and retries with `device_id`. |
| `unavailable` | `message` | Registration is disabled on the ERP: show `message` and keep the screen read-only (retry button). |

A wrong password is a `422` with `errors.password`, not a `200`:

```json
{ "message": "Contraseña de registro incorrecta. Verifícala e intenta de nuevo.",
  "errors": { "password": ["Contraseña de registro incorrecta. Verifícala e intenta de nuevo."] } }
```

### 4.6 `POST /devices/register` (optional — web kiosk)

Not needed by the app (§3.3). Registers a **new** device with the shared registration password.

Request:

```json
{ "name": "Tablet entrada", "location": "Recepción", "notes": "Tablet de la puerta",
  "password": "kiosco-2026", "device_key": "9774d56d682e549c" }
```

* `name` is required (max 100) — pre-fill it with a sensible default such as
  `"Tablet " + androidInfo.model` so the operator only types the password.
* `location` (max 120) and `notes` (max 500) are optional.

Responses:

| Status | Body | Meaning |
|--------|------|---------|
| `201` | `{ "status": "registered", "device": {...}, "token": "...", "message": "Dispositivo registrado correctamente." }` | The token is **shown only once**; the client stores it. |
| `409` | `{ "status": "already_authorized", "device": {...}, "message": "Este dispositivo ya está autorizado como «…». No es necesario registrarlo de nuevo." }` | The header carried a valid token: the app already has a working device. Show `message` and offer *Continuar*. |
| `422` | `{ "message": ..., "errors": { "password": [...] } }` | Password not configured (registration disabled) or wrong password. |

---

## 5. Error handling

Every error is JSON. Two shapes exist:

```json
// Validation / business error (422)
{ "message": "Número de empleado no encontrado. Verifícalo o pide ayuda al administrador.",
  "errors": { "employee_number": ["Número de empleado no encontrado. Verifícalo o pide ayuda al administrador."] } }

// Plain error (404, 429, 5xx)
{ "message": "Demasiados intentos." }
```

| Status | When | App reaction |
|--------|------|--------------|
| `200` / `201` | Success | Show the success overlay. |
| `409` | Only if the app calls `/devices/register` with a token it already holds | Continue to the kiosk screen. |
| `422` | Validation or business rule (unknown employee number, no face detected, duplicate punch, provider not configured, GPS missing…) | Show `errors.<field>[0]` under the matching input, or as a toast for `face`. Keep the form state so the collaborator can retry. |
| `429` | Throttled | `"Demasiados intentos. Espera unos segundos e intenta de nuevo."` |
| `5xx` / timeout / no network | Server or connectivity problem | `unreachable` screen with a *Reintentar* button and an automatic retry every **15 s**. |

**`403` never happens in this API**: the device is not authorized (§3.1), so there is no
“device not authorized” screen to build.

Validation keys the app will see, mapped to their UI slot:

| Key | Shown in |
|-----|----------|
| `employee_number` | The keypad card |
| `type` | The punch-type step |
| `latitude` / `longitude` | Global banner: `"No se pudo obtener la ubicación del dispositivo. Activa la ubicación e intenta de nuevo."` |
| `photo` | The camera card |
| `face` | The camera card (face recognition failures) |
| `user` | Overlay of the current step (collaborator without attendance, terminated collaborator) |

**Never lose the collaborator's data:** on any 4xx keep the selected punch type and, for the face
method, let them retake the photo without starting over.

---

## 6. Client state machine

```
checking ──▶ ready ──▶ (punch flow) ──▶ ready
   │
   └──▶ unreachable (network / 5xx / timeout) ──▶ retry every 15 s ──▶ checking
```

| State | Screen |
|-------|--------|
| `checking` | Centered logo/spinner: `"Verificando conexión…"` |
| `ready` | Kiosk: header (device name + location + clock), step 1 punch types, step 2 identification |
| `unreachable` | `"No se pudo conectar con el servidor."` + `"Verifica la red del dispositivo."` + *Reintentar* |

Rules:

* There is **no setup and no recovery state**: nothing has to be provisioned before the first punch
  (§3.1). The only blockers are connectivity, the punch methods enabled in the ERP and the location
  permission.
* Transition **ready → checking** every 5 minutes (`ping` + `bootstrap`) without clearing the
  screen while the request is in flight; only a failure changes the state.
* A punch is only possible in `ready`. Disable the buttons while a request is in flight
  (double taps cause duplicate-punch 422s).
* After a successful punch show the overlay for **7 seconds**, then reset to step 1 automatically.
* No navigation stack: the kiosk screen is the root; the operator settings are reachable only
  through the hidden gesture (see §8).

---

## 7. Punch flow (UX)

1. **Step 1 — type.** Six large buttons from `punchTypes` (2×3 grid). Selecting one highlights it and
   reveals step 2. A `Ahora no` / back control returns to step 1.
2. **Step 2 — identification.** Two cards when both methods are enabled:
   * **Con mi rostro** — starts the front camera, shows a live preview and a *Capturar* button (or an
     automatic capture after 1.5 s with the face inside the guide). Shows
     `"Colócate de frente a la cámara, con buena iluminación."`
   * **Con mi número de empleado** — numeric keypad (`0-9`, borrar, continuar). While this method is
     selected the camera must be **stopped and released**.
   * When the employee-number method is used, show the notice
     `"El registro es personal e individual. No registres la asistencia de otra persona."`
     (with face recognition this notice is not needed: the face identifies the collaborator).
3. **Location.** Ask for the GPS fix as soon as the kiosk context is loaded and again on every submit,
   inside the user gesture (Android requires it to show the permission prompt). Show
   `"Obteniendo ubicación…"` while waiting. Do not block the UI: if the fix takes more than 10 s,
   still send the request and let the server decide.
4. **Punch.** Call `/punch` or `/face-punch`.
5. **Success overlay (7 s).**
   * Title: `"Registro exitoso"`
   * Collaborator name (`user_name`), `type_label`, `punched_at` and `date_label`.
   * Line: `"Siguiente registro sugerido: " + suggested_next_label` (hide it when `suggested_next`
     is `null`).
6. **Failure.** Inline message under the camera / keypad, form still ready (§5).

Attendance subjects only: `RegisterAttendancePunchAction` rejects collaborators that are not
attendance subjects, terminated collaborators (after `termination_date`) and duplicates within
**±2 minutes**; their messages come back as `422` with the key `user` or `type`. Show them verbatim.

---

## 8. Android kiosk hardening

The tablet is public: assume the collaborator will try to leave the app, swipe, or turn the screen off.

| Requirement | Implementation |
|-------------|----------------|
| Single app, no launcher | `MainActivity` as `HOME` intent filter (launcher app) **or** lock task mode. Prefer **lock task**: request device-owner/kiosk provisioning, then `startLockTask()`; on a non-provisioned device fall back to screen pinning. |
| Immersive full screen | Hide status and navigation bars (`SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky)`), lock the orientation with `SystemChrome.setPreferredOrientations`. |
| Screen always on | `wakelock_plus` → `WakelockPlus.enable()` while the kiosk screen is mounted. |
| Back / recents blocked | `PopScope(canPop: false)`; with lock task the recents bar and the gestures are already hidden. |
| Auto relaunch | `android:launchMode="singleTask"`; on `AppLifecycleState.paused` for more than 30 s, or on `onUserLeaveHint`, use a platform channel to bring `MainActivity` to the front and re-enter lock task. |
| Reboot | Declare a `BOOT_COMPLETED` receiver (`RECEIVE_BOOT_COMPLETED`) that starts the activity; on kiosk-provisioned devices let the device-owner policy relaunch it. |
| Hidden operator exit | Long-press (**3 s**) on the device name in the header opens the operator settings screen (base URL, device name, camera/GPS diagnostics, `server_time` drift). Protect it with a 4-digit operator PIN stored in secure storage at provisioning time. There is **no** other way out of the kiosk. |
| Screen off handling | Keep `wakelock` on; if the system turned the screen off anyway, the punch flow restarts at step 1 on resume (never keep a half-typed employee number). |
| Time | Do not require an exact device clock, but show the `server_time` drift warning (§4.1). |

`AndroidManifest.xml` essentials:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.CAMERA" />
<uses-permission android:name="android.permission.ACCESS_FINE_LOCATION" />
<uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION" />
<uses-permission android:name="android.permission.RECEIVE_BOOT_COMPLETED" />
<uses-permission android:name="android.permission.WAKE_LOCK" />
<uses-permission android:name="android.permission.REORDER_TASKS" />

<uses-feature android:name="android.hardware.camera.front" android:required="true" />
```

Runtime permissions to request on first launch (and from the operator settings screen), not during a
punch: `CAMERA` and `ACCESS_FINE_LOCATION` (`ACCESS_COARSE_LOCATION` as fallback). If the location
permission is denied the kiosk still loads, but every punch fails with the location banner — show a
persistent warning banner until the permission is granted instead of letting collaborators discover
it.

**Clear-text HTTP:** only for local development (`android:usesCleartextTraffic="true"` in the debug
manifest). Production must use HTTPS; in release mode the app must reject an `http://` base URL.

---

## 9. Flutter stack and project structure

| Package | Use |
|---------|-----|
| `dio` | HTTP client (timeouts, interceptors, base options). |
| `flutter_secure_storage` | Optional operator PIN of the hidden settings screen (`encryptedSharedPreferences: true`). |
| `device_info_plus` | `AndroidDeviceInfo.id` → `device_key`, `model` → `device_name`. |
| `geolocator` | GPS fix (`LocationAccuracy.high`, `timeLimit: 10 s`). |
| `camera` | Front camera preview + `takePicture()`. |
| `image` | Resize + JPEG re-compress before base64. |
| `wakelock_plus` | Keep the screen on. |
| `intl` | Clock and date labels (`HH:mm`, `dd/MM/yyyy`). |
| `connectivity_plus` | Fast `unreachable` detection (still verify with `/ping`). |
| `--dart-define` | Base URL configuration. |

Suggested structure (keep files small and single-purpose):

```
lib/
├── main.dart
├── config/kiosk_config.dart          # base URL, timeouts, retry intervals
├── data/
│   ├── kiosk_api.dart                # dio wrapper: ping, bootstrap, punch, facePunch
│   ├── kiosk_storage.dart            # secure storage: operator pin, optional installation id
│   └── models/                       # DeviceIdentity, KioskContext, PunchResult, ApiError
├── services/
│   ├── location_service.dart
│   ├── camera_service.dart           # preview controller + capture + compression
│   └── kiosk_lock_service.dart       # lock task, wakelock, immersive, relaunch
├── state/kiosk_controller.dart       # ChangeNotifier: state machine + punch flow
└── ui/
    ├── kiosk_screen.dart
    ├── operator_settings_screen.dart # base URL, device name, permissions, diagnostics
    ├── unreachable_screen.dart
    └── widgets/                      # PunchTypeGrid, KeypadCard, CameraCard, SuccessOverlay
```

* State management: plain `ChangeNotifier` + `ListenableBuilder` is enough; do not add a heavy
  framework for four screens.
* `dio` timeouts: connect **10 s**, receive **25 s** (a face punch can take a few seconds on AWS),
  send **25 s** (photo upload). One retry only for `GET /ping`.
* Parse errors into a single `ApiError` type with `statusCode`, `message` and `fieldErrors`
  (`Map<String, List<String>>`) so the UI never depends on raw JSON.

---

## 10. Acceptance checklist

Backend (already implemented, verified by `php artisan test --filter=ApiKioskTest`):

- [x] `GET /api/v1/kiosk/ping` and `POST /bootstrap|recover|devices/register|punch|face-punch` exist.
- [x] **No device authorization**: the route group uses `attendance.device.optional`, which never
      answers `403`; the tablet is registered on first contact from its Android ID.
- [x] Every error is JSON (the route group forces `Accept: application/json`).
- [x] Punches store device (when the app sends `device_key`), GPS, method and (face) capture; the
      duplicate window and the attendance-subject rules are enforced server-side.
- [x] `tests/Feature/Payroll/ApiKioskTest.php` covers ping, bootstrap, auto-registration of the
      tablet, both punch methods, recovery and registration.

App (what the Flutter agent must deliver):

- [ ] First launch: `ping` + `bootstrap` (with `device_key` / `device_name`) open the kiosk with **no
      operator interaction at all** — there is no setup, registration or recovery screen.
- [ ] Punch by employee number: keypad → location → success overlay with the suggested next punch.
- [ ] Punch by face: camera preview → capture → compressed JPEG data URL → success overlay.
- [ ] Failures (no location, unknown employee number, unknown face, duplicate punch) show the server
      message and let the collaborator retry without starting over.
- [ ] Airplane mode → `unreachable` screen with retry; the kiosk recovers by itself when the network
      returns.
- [ ] Lock task / immersive mode / wakelock active; back and recents cannot leave the app; a reboot
      returns to the kiosk screen.
- [ ] All labels in sentence case Spanish (see §7); all code, comments and identifiers in English.

---

## 11. Backend reference

| Piece | Path |
|-------|------|
| Routes | `routes/api-kiosk.php` (required from `routes/api.php`, prefix `api/v1/kiosk`) |
| Controller | `app/Http/Controllers/Api/V1/KioskController.php` (thin) |
| Form requests | `app/Http/Requests/Payroll/{BootstrapKiosk,KioskPunch,KioskFacePunch,RecoverKioskDevice,RegisterKioskDevice}Request.php` |
| Actions | `app/Actions/Payroll/{BootstrapKiosk,ResolveKioskDevice,RecoverKioskDevice,RegisterKioskDevice,RegisterEmployeeNumberPunch,RegisterFacePunch,BuildPunchResponse,RegisterAttendancePunch}Action.php` |
| Optional device resolution | `app/Http/Middleware/ResolveAttendanceDevice.php` (alias `attendance.device.optional`) — never blocks |
| Strict device auth (web kiosk only) | `app/Http/Middleware/EnsureAttendanceDevice.php` (alias `attendance.device`) |
| JSON enforcement | `app/Http/Middleware/ForceJsonResponse.php` |
| Services | `app/Services/Payroll/AttendanceDeviceService.php`, `app/Services/Payroll/FaceRecognition/FaceRecognitionService.php` |
| Tests | `tests/Feature/Payroll/ApiKioskTest.php` |

The web kiosk (`/attendance/kiosk`, `app/Http/Controllers/Payroll/KioskController.php`) uses the **same
actions**, so any behaviour change must be verified in both clients
(`php artisan test --testsuite=Feature --filter=KioskAttendanceTest`).
