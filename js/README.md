# xwms-package

XWMS JavaScript / Node.js SDK.

## Install
```bash
npm install xwms-package
```

## Quick start
```js
import { XwmsClient } from "xwms-package";

const xwms = new XwmsClient({
  apiUri: "http://127.0.0.1:8000/api/",
  clientId: process.env.XWMS_CLIENT_ID,
  clientSecret: process.env.XWMS_CLIENT_SECRET,
  domain: process.env.XWMS_DOMAIN,
  redirectUri: process.env.XWMS_REDIRECT_URI,
});

const { redirectUrl } = await xwms.authenticateUser();
```

## Verify token
```js
const response = await xwms.verifyToken(token);
```

## Sync user with your DB
```js
import { authenticateAndSyncUser, createNodeStorage } from "xwms-package";

const result = await authenticateAndSyncUser({
  client: xwms,
  token,
  adapter: {
    findConnectionBySub: async (sub) => db.xwmsConnections.findBySub(sub),
    findUserByEmail: async (email) => db.users.findByEmail(email),
    createUser: async (attrs) => db.users.create(attrs),
    updateUser: async (user, attrs) => db.users.update(user.id, attrs),
    connectUser: async (user, sub) => db.xwmsConnections.connect(user.id, sub),
    getSubForUser: async (user) => db.xwmsConnections.getSub(user.id),
  },
  options: {
    imageSync: {
      enabled: true,
      directory: "data/xwms",
      storage: createNodeStorage(process.cwd()),
    },
  },
});
```

## Express drop‑in router (fastest setup)
```js
import express from "express";
import session from "express-session";
import {
  XwmsClient,
  createXwmsExpressRouter,
  createNodeStorage,
} from "xwms-package";

const app = express();
app.use(session({ secret: process.env.SESSION_SECRET, resave: false, saveUninitialized: false }));

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

## Framework‑agnostic handlers
```js
import { createXwmsHandlers } from "xwms-package";

const handlers = createXwmsHandlers({
  client: xwms,
  adapter,
});

// Example usage:
// app.get("/api/auth/login", handlers.login)
// app.get("/api/auth/xwms/callback", handlers.callback)
```
