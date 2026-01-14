---
title: JS/Node Installation
description: ""
layout: docs
---

# JavaScript / Node.js — XWMS OAuth Setup

This is the **official** JS/Node setup guide for XWMS authentication.
It explains the real flow used by XWMS (token redirect + verify),
not a generic OAuth example.

Important rule: **link users by the stable `sub` id**, never by email.

---

## Quick Setup (the fastest way)

### 1) Install the package

```bash
npm install xwms-package
```

### 2) Add env variables

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_CLIENT_SECRET=your_client_secret_here
XWMS_DOMAIN=your_domain_here     # like example.com
XWMS_REDIRECT_URI=http://localhost:3000/api/auth/xwms/callback
XWMS_API_URI=http://127.0.0.1:8000/api/
```

### 3) Plug in the Express router (drop-in)

```js
import express from "express";
import session from "express-session";
import { XwmsClient, createXwmsExpressRouter, createNodeStorage } from "xwms-package";

const app = express();
app.use(session({
  secret: process.env.SESSION_SECRET,
  resave: false,
  saveUninitialized: false
}));

const xwms = new XwmsClient({
  apiUri: process.env.XWMS_API_URI,
  clientId: process.env.XWMS_CLIENT_ID,
  clientSecret: process.env.XWMS_CLIENT_SECRET,
  domain: process.env.XWMS_DOMAIN,
  redirectUri: process.env.XWMS_REDIRECT_URI,
});

const adapter = {
  findConnectionBySub: async (sub) => db.xwmsConnections.findBySub(sub),
  findUserByEmail: async (email) => db.users.findByEmail(email),
  createUser: async (attrs) => db.users.create(attrs),
  updateUser: async (user, attrs) => db.users.update(user.id, attrs),
  connectUser: async (user, sub) => db.xwmsConnections.connect(user.id, sub),
  getSubForUser: async (user) => db.xwmsConnections.getSub(user.id),
};

app.use(
  "/api/auth",
  createXwmsExpressRouter({
    client: xwms,
    adapter,
    createRouter: () => express.Router(),
    imageSync: {
      enabled: true,
      directory: "data/xwms",
      storage: createNodeStorage(process.cwd()),
    },
    redirectAfterLogin: "/dashboard",
  })
);
```

Now your routes are ready:

- `GET /api/auth/login`
- `GET /api/auth/xwms/callback`
- `GET /api/auth/me`
- `POST /api/auth/sync`
- `GET /api/auth/avatar`
- `POST /api/auth/logout`

---

## What actually happens (real XWMS flow)

1) You send the user to XWMS using **sign-token**  
2) XWMS authenticates the user  
3) XWMS redirects back with a **token**  
4) Your server verifies the token using **sign-token-verify**  
5) You store the user with the stable **sub** id

This is **not** the OAuth code exchange flow used by Google.
XWMS returns a token directly to your callback URL.

---

## Response format (always JSON)

All XWMS responses use the same envelope:

```json
{
  "status": "success",
  "message": "Human readable message",
  "data": { "..." : "payload" }
}
```

- `status` is `success` or `error`
- `message` explains what happened
- `data` contains the payload for that endpoint

---

## sign-token response (start auth)

When you call `sign-token`, XWMS responds with:

- `client_id` (string)
- `token` (string)
- `email` (string, if available)
- `expires_at` (ISO 8601 string)
- `url` (string, the login/redirect URL)

The SDK/Helper uses `url` and redirects for you, so you typically do not
handle it manually.

---

## sign-token-verify response (user data)

When you verify the token, XWMS returns user data in `data`:

- `sub` (string, stable user id)
- `name` (string)
- `given_name` (string)
- `family_name` (string or null)
- `email` (string)
- `email_verified` (boolean)
- `picture` (string URL)

Always link users by `sub`.

---

## Full Manual Setup (no router, full control)

If you want to build your own routes, use the handlers:

```js
import { XwmsClient, createXwmsHandlers } from "xwms-package";

const xwms = new XwmsClient({
  apiUri: process.env.XWMS_API_URI,
  clientId: process.env.XWMS_CLIENT_ID,
  clientSecret: process.env.XWMS_CLIENT_SECRET,
  domain: process.env.XWMS_DOMAIN,
  redirectUri: process.env.XWMS_REDIRECT_URI,
});

const handlers = createXwmsHandlers({
  client: xwms,
  adapter,
});

app.get("/api/auth/login", handlers.login);
app.get("/api/auth/xwms/callback", handlers.callback);
app.get("/api/auth/me", handlers.me);
app.post("/api/auth/sync", handlers.sync);
app.get("/api/auth/avatar", handlers.avatar);
app.post("/api/auth/logout", handlers.logout);
```

---

## Why `sub` is mandatory (do not use email)

Email can change. Some users share email. Some lose access.
If you link by email, you break accounts.

XWMS gives every account a **stable, permanent id** called `sub`.

Always link your local user to `sub`:
- if you already have it, log in
- if you do not have it, create a user and store the sub

---

## Example: Database structure

Minimal tables:

```
users
  id, name, email, picture

xwms_connections
  id, user_id, sub
```

This guarantees stable identity even if the user changes email or name.

---

## Example login button

```html
<a href="/api/auth/login">Login with XWMS</a>
```

---

## Troubleshooting

- Check that `XWMS_REDIRECT_URI` matches exactly the callback URL you registered.
- Log `error.message` or response body from your server if login fails.
- Ensure your `.env` file is loaded.
- Tokens are single-use; try again if you already used it.

---

## Summary

- Use `xwms-package` for JS/Node apps.
- Redirect with **sign-token**, verify with **sign-token-verify**.
- Link users by **sub**, not email.
- Use the Express router to get everything instantly.
