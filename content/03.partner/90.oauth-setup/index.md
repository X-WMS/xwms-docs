---
title: General Setup
description: ""
layout: docs
---

# General Setup – Works with Any Programming Language

This guide teaches you how to use **XWMS Authentication APIs** in **any** programming language — Python, Node.js, PHP, C#, Go, Ruby, or anything else.

You don’t need our helper functions or a specific SDK — just use HTTP or HTTPS requests.

We’ll use an AI chatbot (like ChatGPT, Gemini, Claude, or Copilot) to help you generate complete working code for your preferred language.

---

## ⚙️ What You’ll Do

1. Open your favorite AI assistant (ChatGPT, Gemini, Claude, Copilot, etc.)
2. Copy and paste the full prompt below.
3. Replace `[your programming language]` with the language or framework you want (for example: **Python (Flask)** or **Node.js (Express)**).
4. The AI will generate **a full working app** for you — with environment setup, routes, and error handling.

---

## 🧠 The Complete Prompt

Paste this into your AI chat:

```bash
You are an AI assistant for developers. Your task is to generate a COMPLETE, WORKING EXAMPLE APP that integrates with the XWMS Authentication API.

The user will replace [your programming language] with their desired programming language or framework.

If the user did NOT replace [your programming language], politely ask them which language they want to use first, and wait.

---

## ✅ GOAL
Build a runnable example project in [your programming language] that connects to XWMS’s authentication system.
The project must show how to:
- Start the authentication process (sign-token)
- Redirect the user to the XWMS login page
- Handle the callback and verify the token (sign-token-verify)
- Display the user’s verified information

---

## 🧩 API Details
Use the following 3 API endpoints:

1. **GET** `https://xwms.nl/api/info` → Fetch basic service info  
2. **POST** `https://xwms.nl/api/sign-token` → Start authentication (requires `redirect_url` in JSON body)  
3. **POST** `https://xwms.nl/api/sign-token-verify` → Validate token (requires `token` in JSON body)

### Required Headers

X-Client-Id: your_client_id
X-Client-Secret: your_secret
X-Client-Domain: your_domain or domainId
Accept: application/json

### Example Values for sign-token

redirect_url: [http://localhost:3000/xwms/validateToken](http://localhost:3000/xwms/validateToken)

### Example Values for sign-token-verify

token: example-token-value

---

## 💻 Your Task as the AI

1. Create a small, runnable **demo app** (not just code snippets).
2. Include **installation commands**, file structure, and setup instructions.
3. Use **environment variables** (e.g., `.env`) for client ID, secret, and redirect URL.
4. Add comments that explain each line clearly.
5. Show how to start the app (`npm run dev`, `python app.py`, `dotnet run`, etc.).
6. Include proper **error handling** and **logging**.
7. Include a simple **UI route** (like `/login`) with a button or link: “Login with XWMS”.
8. When the user logs in and comes back, display their info as JSON.
9. Explain how to test it locally.
10. Explain how to deploy it (e.g., Render, Vercel, Heroku, etc.)

---

## 🔒 Security Notes
- Always load secrets from `.env` or secure config files.
- Never hardcode `client_secret` in your code.
- Use HTTPS in production. or HTTP (depens if the user uses it as test)
- Handle missing or invalid tokens gracefully.

---

## 📦 Example Expected Output (from the AI)

A working code example that includes:
- Dependencies installation commands
- `.env` example
- Complete server file (e.g., `app.js`, `main.py`, etc.)
- Routes:
  - `/xwms/auth` → starts authentication
  - `/xwms/validateToken` → handles callback
  - `/` → shows a login link
- Token verification logic
- JSON response showing verified user info

---

## 🧠 Example Dialogue

**You:** (after pasting the prompt)
> I want to use JavaScript with Express.

**AI:** (should respond with)
> Great! Let’s build a Node.js (Express) app that uses XWMS Authentication. Here’s your complete example...

And then it should show full code, setup commands, and `.env` configuration.
```
---

## ✅ That’s It!
Once you paste the full prompt and specify your language, the AI will give you a fully functional app that uses **XWMS Authentication APIs**.

You can use it to:
- Log users in securely with XWMS
- Validate tokens
- Retrieve authenticated user data
- Integrate with dashboards, web apps, or enterprise tools

> 💡 This prompt works with **any AI chatbot** that can write code — ChatGPT, Gemini, Copilot, Claude, or others.
> You don’t need Laravel or PHP; it works in **any language** that supports HTTP or HTTPS requests.
