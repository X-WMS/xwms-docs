---
title: Js/Node Installation
description: ""
layout: docs
---

# XWMS Authentication – JavaScript / Node.js Guide

This guide explains how to connect your **JavaScript** or **Node.js** app to XWMS for secure login.
You can use it with Express, Next.js, NestJS or any other Node backend.

We start with a **short version**, then a **simple explanation**  
so that even someone who doesn’t code (or a 10‑year‑old) can follow the idea.

The important idea: when XWMS sends user data back, you should link
accounts using the **stable `sub` id**, not the email address.

---

## 🚀 Short Version (Developers Who Just Want It Working)

### 1. Create an XWMS App

In your XWMS developer portal, create an app and copy:

- `client_id`
- `domain`
- `client_secret`
- `redirect_uri`

### 2. Install Dependencies

```bash
npm install express axios dotenv
```

### 3. Add Environment Variables

Create a `.env` file in your project:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here     # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://localhost:3000/xwms/callback
XWMS_AUTH_URL=https://xwms.com/oauth/authorize
XWMS_TOKEN_URL=https://xwms.com/oauth/token
XWMS_USERINFO_URL=https://xwms.com/api/userinfo
```

### 4. Build Your Express App

```js
import express from 'express';
import axios from 'axios';
import dotenv from 'dotenv';

dotenv.config();
const app = express();
const PORT = 3000;

// 1) Redirect user to XWMS login page
app.get('/login', (req, res) => {
  const redirectUrl =
    `${process.env.XWMS_AUTH_URL}` +
    `?response_type=code` +
    `&client_id=${process.env.XWMS_CLIENT_ID}` +
    `&redirect_uri=${encodeURIComponent(process.env.XWMS_REDIRECT_URI)}`;

  res.redirect(redirectUrl);
});

// 2) Handle XWMS callback and exchange code for access token
app.get('/xwms/callback', async (req, res) => {
  const code = req.query.code;
  if (!code) return res.status(400).send('Missing code');

  try {
    const tokenResponse = await axios.post(process.env.XWMS_TOKEN_URL, {
      grant_type: 'authorization_code',
      code,
      redirect_uri: process.env.XWMS_REDIRECT_URI,
      client_id: process.env.XWMS_CLIENT_ID,
      domain: process.env.XWMS_DOMAIN,
      client_secret: process.env.XWMS_CLIENT_SECRET,
    });

    const accessToken = tokenResponse.data.access_token;

    // 3) Get user info
    const userResponse = await axios.get(process.env.XWMS_USERINFO_URL, {
      headers: { Authorization: `Bearer ${accessToken}` },
    });

    const user = userResponse.data;

    // 4) Professional linking: use the stable "sub" id
    // pseudo-code: findOrCreateUserBySub(user.sub, user);
    res.send(`Welcome ${user.name || 'User'} (sub: ${user.sub})`);
  } catch (error) {
    console.error(error.response?.data || error.message);
    res.status(500).send('Login failed');
  }
});

app.listen(PORT, () => console.log(`Server running on http://localhost:${PORT}`));
```

Then open <http://localhost:3000/login> and try logging in.

---

## 🧠 Full Explanation – Like You’re 10

### 1. Playground, Guard and Ticket

Imagine your app is a **playground**.  
XWMS is a **security guard** who checks who may enter.

When someone wants to play:

1. You send them to the guard (XWMS).  
2. The guard checks who they are.  
3. If everything is ok, the guard gives them a **ticket** (a token).  
4. They come back to your playground and show the ticket.  

Your Node server then uses that ticket to ask XWMS:

> “Who is this? What is their id, name, email…?”

---

### 2. Why we don’t link users by email

Many tutorials say:

> “Find the user by email, or create a new user with this email.”

That looks easy, but it has problems:

- people can change their email  
- someone can lose access to their email  
- in some companies one email is shared by several people  

If email changes, your link between “XWMS account” and “your user” breaks.

So XWMS gives every account a **stable id** called `sub`:

- it never changes  
- it works across devices and sessions  
- you can think of it as the **number on a bus card or library card**

We store this `sub` in our own database and use **that** to find the user.

---

### 3. Pseudo‑code: professional account linking in Node

Below is simple pseudo‑code for a typical handler.
It is intentionally written in a clear way, not framework‑specific:

```js
// userData is what you get back from XWMS after verification
async function handleXwmsUser(userData) {
  const sub = userData.sub; // stable XWMS id
  if (!sub) throw new Error('Missing XWMS sub');

  // 1) Try to find an existing link by sub
  let link = await db.xwms_connections.findOne({ sub }).populate('user');

  if (link && link.user) {
    // Optional: update local user data from XWMS
    link.user.name = userData.name ?? link.user.name;
    link.user.email = userData.email ?? link.user.email;
    await link.user.save();

    return link.user; // log this user in
  }

  // 2) No account yet: create one and connect it to this sub
  const name =
    userData.name ??
    `${userData.given_name || ''} ${userData.family_name || ''}`.trim() ||
    'User';

  const user = await db.users.create({
    name,
    email: userData.email ?? null,
    avatar: userData.picture ?? null,
    // generate a long random password so this account is safe
    passwordHash: generateRandomPasswordHash(),
  });

  await db.xwms_connections.create({ userId: user.id, sub });

  return user;
}
```

You can adapt this idea to:

- MongoDB (Mongoose)  
- SQL (Prisma / Knex)  
- any other database system  

The **flow** is always:

1. read `sub`  
2. look up a connection row using `sub`  
3. if found → log in that user  
4. if not found → create user, create connection, log them in  

---

### 4. What happens step by step

| Step | Action                           | Description                              |
| ---- | -------------------------------- | ---------------------------------------- |
| 1    | User clicks “Login with XWMS”    | You redirect to the XWMS login page      |
| 2    | XWMS shows its login screen      | User enters username / password there    |
| 3    | XWMS redirects back with a code  | Only your server can use this code       |
| 4    | Your app exchanges code for token| You prove your app is allowed to do this |
| 5    | Your app fetches user info       | You get `sub`, name, email, picture, …   |
| 6    | You link user by `sub`           | You find or create a local account       |

---

### 5. Example login button (frontend)

If you have a front‑end (React, Vue, plain HTML), you only need a link:

```html
<a href="http://localhost:3000/login">Login with XWMS</a>
```

Clicking it starts the whole flow.

---

### 6. Debugging Tips

If something doesn’t work:

- Check your **redirect URI** – it must exactly match what’s set in XWMS.  
- Log `error.response?.data` when a request fails.  
- Make sure your `.env` file is loaded (`console.log(process.env.XWMS_CLIENT_ID)` once).  
- Use fresh codes each time – authorization codes expire quickly.  

---

## Summary

- XWMS is the “guard” that signs people in for you.  
- Your Node app redirects people to XWMS, then receives a token back.  
- You use that token to fetch user info, including the **stable `sub` id**.  
- You store and use `sub` to link to your own users – **not** the email address.  

This gives you a professional, robust login system that keeps working  
even when people change their email or name.

