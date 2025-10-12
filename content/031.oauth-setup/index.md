---
title: General Setup
---

## Installation (ANY AI CHATBOT)
Use this prompt to ask any AI chatbot (like ChatGPT, Gemini, Copilot, Claude, etc.) how to work with the XWMS authentication API in your preferred programming language.

What you need to do:
Paste the full prompt below into an AI chat.

Replace [your programming language] with the language you want to use (e.g. Python, JavaScript, C#, etc.).

Let the AI generate the example for you — with the correct endpoints, headers, and request structure.
You do not need to use Laravel or PHP — this works with any language.

```bash
You are an AI assistant for developers integrating the XWMS Authentication API.

I want you to generate example code in [your programming language] that interacts with the following 3 API endpoints:

1. `GET https://xwms.nl/api/info` – Fetch basic service info.
2. `POST https://xwms.nl/api/sign-token` – Start an authentication request.
3. `POST https://xwms.nl/api/sign-token-verify` – Validate a token after redirection.

These endpoints require custom headers:

- `X-Client-Id`: your client ID (e.g., "your_client_id")
- `X-Client-Secret`: your client secret (e.g., "your_secret")
- `Accept`: application/json

For `sign-token`, you must include `redirect_url` in the JSON body.

For `sign-token-verify`, you must include `token` in the JSON body.

---

Please generate example code (e.g., using `fetch`, `axios`, `requests`, `http.client`, etc. depending on the language) that does the following:

- Send a `GET` request to `/info`
- Send a `POST` request to `/sign-token` with a `redirect_url`
- Send a `POST` request to `/sign-token-verify` with a `token`

Make sure you include all headers and body structures as needed.

Use the following placeholder values:
- client ID: `your_client_id`
- client secret: `your_secret`
- redirect_url: `http://example.com/validateToken`
- token: `example-token-value`

Output only the code in [your language], nothing else. If you need to set variables or install a package (like `axios` or `requests`), include it too.
if the user did NOT replace [your programming language] please ask the user to provide an programming language first.
```

✅ That’s it! You’re now ready to use the XWMS authentication APIs in your Laravel app.