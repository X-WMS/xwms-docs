---
title: Js/Node Installation
description: ""
layout: docs
---

# XWMS Authentication — JavaScript / Node.js Guide

This guide explains how to connect your **JavaScript** or **Node.js** app to XWMS for secure login — even if you **don’t have any helper functions** like in Laravel.

We’ll start with a **simple, short version** and then go into a **detailed, beginner-friendly explanation** (as if you’re 10 years old 🧒).

---

## ⚡ Short Version (Developers Who Just Want It Working)

### 1️⃣ Step 1: Create an XWMS App

Go to your XWMS developer portal and create a new app. You’ll get:

* `client_id`
* `domain`
* `client_secret`
* `redirect_uri`

These will be needed in your code.

### 2️⃣ Step 2: Install Dependencies

```bash
npm install express axios dotenv
```

### 3️⃣ Step 3: Add Environment Variables

Create a `.env` file in your project:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://localhost:3000/xwms/callback
XWMS_AUTH_URL=https://xwms.com/oauth/authorize
XWMS_TOKEN_URL=https://xwms.com/oauth/token
XWMS_USERINFO_URL=https://xwms.com/api/userinfo
```

### 4️⃣ Step 4: Build Your Express App

Here’s a minimal Node.js example:

```js
import express from 'express';
import axios from 'axios';
import dotenv from 'dotenv';

dotenv.config();
const app = express();
const PORT = 3000;

// Step 1: Redirect user to XWMS login page
app.get('/login', (req, res) => {
  const redirectUrl = `${process.env.XWMS_AUTH_URL}?response_type=code&client_id=${process.env.XWMS_CLIENT_ID}&redirect_uri=${encodeURIComponent(process.env.XWMS_REDIRECT_URI)}`;
  res.redirect(redirectUrl);
});

// Step 2: Handle XWMS callback and exchange code for access token
app.get('/xwms/callback', async (req, res) => {
  const code = req.query.code;
  if (!code) return res.send('Missing code');

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

    // Step 3: Get user info
    const userResponse = await axios.get(process.env.XWMS_USERINFO_URL, {
      headers: { Authorization: `Bearer ${accessToken}` }
    });

    const user = userResponse.data;

    // ✅ Handle login (save user, create session, etc.)
    res.send(`Welcome ${user.name || 'User'}!`);
  } catch (error) {
    console.error(error.response?.data || error.message);
    res.status(500).send('Login failed');
  }
});

app.listen(PORT, () => console.log(`Server running on http://localhost:${PORT}`));
```

### 5️⃣ Step 5: Test It

Run the app:

```bash
node index.js
```

Open [http://localhost:3000/login](http://localhost:3000/login) in your browser and try logging in. 🎉

---

## 🧒 Full Explanation — Like You’re 10

Let’s imagine you have a playground (your app) 🏫 and there’s a **security guard** (XWMS) who decides who can enter.

When someone wants to enter your playground, they first go to the guard, show their ID, and if the guard approves, they get a **pass** (a token) that lets them play.

Your Node.js app does the following:

1. **Sends the user to XWMS** → “Please log in.”
2. **Gets a special code** when they come back.
3. **Exchanges the code** for an access token.
4. **Uses the token** to ask for the user’s information.

That’s the OAuth2 flow — simple!

---

### ⚙️ Step 1: Install Tools

We need a web server (`express`), a request helper (`axios`), and a way to hide secrets (`dotenv`).

```bash
npm install express axios dotenv
```

---

### 🔐 Step 2: Keep Secrets Safe

Make a file named `.env` and fill it with your app info. This is like your secret note that only your app can read.

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://localhost:3000/xwms/callback
```

These values come from your XWMS developer dashboard.

---

### 🌐 Step 3: Create Login and Callback Routes

* `/login`: sends the user to XWMS
* `/xwms/callback`: handles the return after login

Your code sends users to XWMS with their **client_id** and **domain** and **redirect_uri**, then listens for a **code** in the callback.

You exchange that code for an **access token** (like a VIP badge 🪪). With that token, you can get user info safely.

---

### 🧠 Step 4: What’s Happening Behind the Scenes

| Step | Action                          | Description                  |
| ---- | ------------------------------- | ---------------------------- |
| 1    | User clicks “Login with XWMS”   | Redirects to XWMS login page |
| 2    | XWMS checks credentials         | Verifies who the user is     |
| 3    | XWMS redirects back with `code` | Your app gets the code       |
| 4    | App exchanges code for token    | App proves it’s trusted      |
| 5    | App fetches user info           | You now know who logged in   |

---

### 💻 Example Login Button (Frontend)

If you have a frontend (React, Vue, etc.), you can make a simple button:

```html
<a href="http://localhost:3000/login">Login with XWMS</a>
```

Clicking it starts the whole flow.

---

### 🐞 Debugging Tips

If something doesn’t work:

* Check your **redirect URI** — it must exactly match what’s set in XWMS.
* Add `console.log(error.response?.data)` to see detailed errors.
* Make sure your `.env` variables load properly (`console.log(process.env)` can help).
* Try again with a fresh code each time — they expire!

---

### 🧩 Summary

✅ What You Learned:

* How to send users to XWMS to log in
* How to get a code and exchange it for a token
* How to use that token to fetch user info
* How to make it all work in a Node.js app

With this, you can add secure XWMS login to any **JavaScript app**, from plain Express servers to React or Next.js frontends.

---

**You did it! 🎉** You’ve just built your own OAuth login flow — no helpers, no magic, just clear logic and a few lines of code.
