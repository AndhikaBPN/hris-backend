# Set Password via Magic Link — Frontend Implementation Guide

## Overview

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
