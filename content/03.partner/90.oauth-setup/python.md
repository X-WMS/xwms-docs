---
title: Python Installation
description: ""
layout: docs
---

# 🐍 XWMS Login for Python Apps

This guide helps you connect your **Python app** (for example Flask or FastAPI)
to **XWMS Login** – a secure way to sign users in using tokens.

We start with a quick version for developers, then explain it in very simple
language so even someone who does not code can follow.

Important: we will link users using the **stable XWMS id `sub`**,
not their email address.

---

## 🚀 Quick Setup (Short Version)

If you already know Python web basics, follow these steps.

### 1. Install dependencies

```bash
pip install requests python-dotenv flask
```

### 2. Add `.env` file

Create a `.env` file:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here      # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://localhost:5000/xwms/validateToken
XWMS_API_URL=https://api.xwms.com/
```

### 3. Simple Flask login flow

```python
from flask import Flask, redirect, request, jsonify
import requests, os
from dotenv import load_dotenv

load_dotenv()
app = Flask(__name__)

API_URL = os.getenv("XWMS_API_URL")

@app.route("/xwms/auth")
def xwms_auth():
    # Ask XWMS for a login URL
    resp = requests.post(
        f"{API_URL}sign-token",
        json={
            "client_id": os.getenv("XWMS_CLIENT_ID"),
            "domain": os.getenv("XWMS_DOMAIN"),
            "client_secret": os.getenv("XWMS_CLIENT_SECRET"),
            "redirect_url": os.getenv("XWMS_REDIRECT_URI"),
        },
        timeout=10,
    )
    data = resp.json()
    login_url = data.get("data", {}).get("url")
    return redirect(login_url)


@app.route("/xwms/validateToken")
def xwms_callback():
    token = request.args.get("token")
    if not token:
        return jsonify({"error": "Missing token."}), 400

    resp = requests.post(
        f"{API_URL}sign-token-verify",
        json={
            "token": token,
            "client_id": os.getenv("XWMS_CLIENT_ID"),
            "domain": os.getenv("XWMS_DOMAIN"),
            "client_secret": os.getenv("XWMS_CLIENT_SECRET"),
        },
        timeout=10,
    )
    data = resp.json()
    if data.get("status") != "success":
        return jsonify({"error": "Invalid or expired token."}), 400

    user = data.get("data", {})

    # Professional account linking: use the stable "sub" id
    # pseudo‑code:
    # user_obj = find_or_create_user_by_sub(user["sub"], user)

    return jsonify({"message": "User verified", "user": user})


if __name__ == "__main__":
    app.run(debug=True)
```

Visit `http://localhost:5000/xwms/auth` to start login.

---

## 🧠 Full Explanation – Like You’re 10

### 1. Guard, ticket and playground

Imagine your Python app is a **playground**.  
XWMS is the **guard** at the gate.

When someone wants to play:

1. Your app sends them to the guard (XWMS).  
2. The guard checks who they are and, if all is good, gives a **ticket** (token).  
3. The person comes back to your app with that ticket.  
4. Your app asks the guard, “Is this ticket real? Who is this?”  

The answer includes a stable id called `sub`.

---

### 2. Why we don’t rely on email

Old examples often say:

> “Find the user by email, or create a new one using that email.”

This is risky:

- people can change their email  
- one email might belong to multiple people (shared mailbox)  
- email alone is not a permanent id  

If email changes, your database might think it is a new person.

XWMS therefore gives you a **stable id** called `sub`:

- it does not change when email or name changes  
- it uniquely identifies the XWMS account  
- you can imagine it like a **student number** at school  

We store `sub` in our own database and use that to look people up.

---

### 3. Pseudo‑code for linking using `sub`

Below is language‑agnostic pseudo‑code – you can implement it with
SQLAlchemy, Django ORM, plain SQL, etc.

```python
def find_or_create_user_by_sub(sub: str, user_data: dict):
    if not sub:
        raise ValueError("Missing XWMS sub")

    # 1) Try to find an existing connection row
    connection = XwmsConnection.query.filter_by(sub=sub).first()
    if connection and connection.user:
        # Optional: keep some fields in sync
        user = connection.user
        user.name = user_data.get("name") or user.name
        user.email = user_data.get("email") or user.email
        db.session.commit()
        return user

    # 2) No user yet → create one
    name = (
        user_data.get("name")
        or f"{user_data.get('given_name', '')} {user_data.get('family_name', '')}".strip()
        or "User"
    )

    user = User(
        name=name,
        email=user_data.get("email"),
        avatar=user_data.get("picture"),
        password_hash=generate_random_password_hash(),
    )
    db.session.add(user)
    db.session.commit()

    # 3) Store the new connection
    connection = XwmsConnection(user_id=user.id, sub=sub)
    db.session.add(connection)
    db.session.commit()

    return user
```

You would call this inside your `/xwms/validateToken` route,
after verifying the token and reading `user = data["data"]`.

---

### 4. Example with FastAPI (advanced)

```python
from fastapi import FastAPI, Request
from fastapi.responses import RedirectResponse, JSONResponse
import requests, os
from dotenv import load_dotenv

load_dotenv()
app = FastAPI()

API_URL = os.getenv("XWMS_API_URL")


@app.get("/xwms/auth")
def auth():
    res = requests.post(
        API_URL + "sign-token",
        json={
            "client_id": os.getenv("XWMS_CLIENT_ID"),
            "domain": os.getenv("XWMS_DOMAIN"),
            "client_secret": os.getenv("XWMS_CLIENT_SECRET"),
            "redirect_url": os.getenv("XWMS_REDIRECT_URI"),
        },
    )
    login_url = res.json().get("data", {}).get("url")
    return RedirectResponse(login_url)


@app.get("/xwms/validateToken")
def callback(request: Request):
    token = request.query_params.get("token")
    res = requests.post(
        API_URL + "sign-token-verify",
        json={"token": token},
    )
    data = res.json()

    if data.get("status") != "success":
        return JSONResponse({"error": "Invalid or expired token"}, status_code=400)

    user_data = data.get("data", {})
    # Again: use sub for linking
    # user = find_or_create_user_by_sub(user_data["sub"], user_data)

    return JSONResponse({"message": "User verified", "user": user_data})
```

Run with:

```bash
uvicorn app:app --reload
```

---

### 5. Debugging tips

- Check that `XWMS_REDIRECT_URI` in `.env` matches the callback route exactly.  
- Print `data = response.json()` when something fails to see error messages.  
- Make sure your `.env` is loaded (`load_dotenv()` is called before reading).  

---

## Summary

- Your Python app redirects users to XWMS to log in.  
- XWMS sends them back with a token.  
- You verify the token and get user data including a **stable `sub` id**.  
- You use `sub` to find or create a local user in your database,
  instead of relying on email.  

This gives you a robust, professional login flow that keeps working
even when users change their email address.

