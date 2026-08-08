# Password Flow — Frontend Implementation Guide

Ada **dua flow** yang berbeda:

| Flow                | Trigger                                       | Endpoint OTP                                             |
| ------------------- | --------------------------------------------- | -------------------------------------------------------- |
| **Set Password**    | Manager buat user baru → magic link via email | OTP otomatis dikirim backend, tidak perlu request manual |
| **Forgot Password** | User lupa password → minta OTP sendiri        | `POST /api/password/forgot`                              |

Keduanya menggunakan `POST /api/password/reset` untuk submit password baru.

---

## Flow 1 — Set Password via Magic Link

### Overview

When a manager creates a new user, the backend automatically emails a magic link to the user. Clicking the link opens a "Set Password" page. The user fills in a new password, submits, and is redirected to login.

---

## Magic Link Format

```
{FRONTEND_URL}/set-password?email={email}&token={otp_code}
```

Example:
```
http://localhost:3000/set-password?email=john%40example.com&token=482910
```

Both `email` and `token` are URL-encoded. Parse them with `URLSearchParams`.

---

## Pages Required

### 1. `/set-password`

**Behavior on load:**
1. Read `email` and `token` from URL query params.
2. If either is missing → redirect to `/error?message=Invalid+link`.
3. Show the password form immediately (do not pre-validate — validation happens on submit).

**Form fields:**
- `new_password` — password input
- `new_password_confirmation` — confirm password input
- Submit button: "Set Password"

**On submit:**

```
POST /api/password/reset
Content-Type: application/json

{
  "email": "<from URL param>",
  "otp_code": "<token from URL param>",
  "new_password": "<user input>",
  "new_password_confirmation": "<user input>"
}
```

**Response handling:**

| HTTP Status | Action |
|-------------|--------|
| `200` | Redirect to `/login?message=Password+set+successfully` |
| `422` | Show inline validation error from `response.message` |
| `400` | Redirect to `/error?message=` + `encodeURIComponent(response.message)` |

---

### 2. `/error`

**Behavior on load:**
1. Read `message` from URL query param.
2. Display the message to the user.
3. Show a "Back to Login" button linking to `/login`.

**Common messages:**
- `OTP is invalid or expired` — link was already used or expired (15-minute window)
- `Invalid link` — URL params missing

---

## Password Validation Rules (client-side, mirror backend)

- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character: `@$!%*?&#^$`

Regex:
```js
/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#^$*])[A-Za-z\d@$!%*?&#^$*]{8,}$/
```

---

## Full Flow Diagram

```
Manager creates user (POST /api/users)
        │
        ▼
Backend generates OTP → saves to DB (expires in 15 min)
        │
        ▼
Backend emails magic link to user
  → {FRONTEND_URL}/set-password?email=...&token=...
        │
        ▼
User clicks link → /set-password page loads
        │
        ▼
User fills new_password + new_password_confirmation
        │
        ▼
POST /api/password/reset
  { email, otp_code, new_password, new_password_confirmation }
        │
     ┌──┴──┐
   200     400
     │       │
     ▼       ▼
 /login   /error?message=OTP+is+invalid+or+expired
```

---

## Environment Variable

Set `APP_FRONTEND_URL` on the backend `.env` to match your frontend origin:

```env
APP_FRONTEND_URL=http://localhost:3000
```

This is the base URL used when constructing the magic link in the email.

---

## Notes

- The OTP is **single-use**. Once `/api/password/reset` succeeds, the token is consumed and the link no longer works.
- Link expires in **15 minutes** from the time the user was created.
- If the user needs a new link, a manager must delete and recreate the user, or you can add a "resend welcome email" endpoint later.

---

## Flow 2 — Forgot Password

User yang sudah punya akun tapi lupa password dapat request OTP melalui halaman forgot password, lalu reset password dengan OTP tersebut.

### Halaman: `/forgot-password`

**Form fields:**

- `email` — email input
- Submit button: "Kirim OTP"

**On submit:**

```http
POST /api/password/forgot
Content-Type: application/json

{
  "email": "<user input>"
}
```

**Response handling:**

| HTTP Status | Action                                                                                   |
| ----------- | ---------------------------------------------------------------------------------------- |
| `200`       | Redirect ke `/reset-password?email=<email>` atau tampilkan form OTP di halaman yang sama |
| `400`       | Tampilkan error dari `response.message`                                                  |

> **Catatan:** Response selalu `200` meskipun email tidak terdaftar (mencegah user enumeration). Selalu tampilkan pesan "Jika email terdaftar, OTP telah dikirim."

---

### Halaman: `/reset-password`

**Form fields:**

- `otp_code` — input 6 digit OTP dari email
- `new_password` — password baru
- `new_password_confirmation` — konfirmasi password baru
- Submit button: "Reset Password"

**On submit:**

```http
POST /api/password/reset
Content-Type: application/json

{
  "email": "<dari query param atau state>",
  "otp_code": "<user input>",
  "new_password": "<user input>",
  "new_password_confirmation": "<user input>"
}
```

**Response handling:**

| HTTP Status | Action                                                    |
| ----------- | --------------------------------------------------------- |
| `200`       | Redirect ke `/login?message=Password+reset+successfully`  |
| `422`       | Tampilkan inline validation error dari `response.message` |
| `400`       | Tampilkan error "OTP tidak valid atau sudah expired"      |

---

### Full Flow Diagram — Forgot Password

```text
User klik "Lupa Password" di halaman login
        │
        ▼
/forgot-password — isi email → submit
        │
        ▼
POST /api/password/forgot { email }
        │
        ▼
Backend kirim OTP ke email (berlaku 15 menit, single-use)
        │
        ▼
/reset-password — isi OTP + password baru → submit
        │
        ▼
POST /api/password/reset { email, otp_code, new_password, new_password_confirmation }
        │
     ┌──┴──┐
   200     400/422
     │         │
     ▼         ▼
 /login     Tampilkan error, user coba lagi
```

---

### ⚠️ PENTING — Endpoint yang Benar untuk Forgot Password

**JANGAN** gunakan `POST /api/otp/send` untuk forgot password. Endpoint tersebut untuk kebutuhan lain dan menggunakan OTP type yang berbeda — password reset akan **selalu gagal** jika OTP dikirim lewat endpoint itu.

**GUNAKAN** `POST /api/password/forgot` yang sudah dirancang khusus untuk reset password.
