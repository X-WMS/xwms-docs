---

title: Python Installation
description: ""
layout: docs
---

# 🐍 XWMS Login for Python Apps

This guide helps you connect your **Python app** (like Flask or FastAPI) with **XWMS Login** — a secure way to log users in using tokens.

You’ll learn how to:

* Add XWMS login to your app
* Verify the login token safely
* Work with environment variables using `.env`
* Handle the callback after login

---

## ⚡ Quick Setup (Short Version)

If you already know Python web basics, follow these quick steps 👇

### Step 1: Install dependencies

```bash
pip install requests python-dotenv flask
```

### Step 2: Add `.env` file

In your project folder, create a `.env` file:

```env
XWMS_CLIENT_ID=your_client_id_here
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=your_secret_here
XWMS_REDIRECT_URI=http://localhost:5000/xwms/validateToken
XWMS_API_URL=https://api.xwms.com/
```

### Step 3: Create a simple login flow

Here’s an example using **Flask**:

```python
from flask import Flask, redirect, request, jsonify
import requests, os
from dotenv import load_dotenv

load_dotenv()
app = Flask(__name__)

XWMS_API_URL = os.getenv("XWMS_API_URL")
DOMAIN = os.getenv("XWMS_DOMAIN")
CLIENT_DOMAIN = os.getenv("XWMS_CLIENT_DOMAIN")
CLIENT_SECRET = os.getenv("XWMS_CLIENT_SECRET")
REDIRECT_URI = os.getenv("XWMS_REDIRECT_URI")

@app.route("/xwms/auth")
def xwms_auth():
    try:
        # Step 1: Ask XWMS for a login URL
        response = requests.post(
            f"{XWMS_API_URL}sign-token",
            json={
                "client_id": CLIENT_ID,
                "domain": DOMAIN,
                "client_secret": CLIENT_SECRET,
                "redirect_url": REDIRECT_URI,
            },
            timeout=10
        )
        data = response.json()
        login_url = data.get("data", {}).get("url")

        if not login_url:
            return jsonify({"error": "No login URL returned from XWMS."}), 500

        return redirect(login_url)

    except Exception as e:
        return jsonify({"error": str(e)}), 500

@app.route("/xwms/validateToken")
def xwms_callback():
    try:
        token = request.args.get("token")
        if not token:
            return jsonify({"error": "Missing token."}), 400

        # Step 2: Verify the token with XWMS
        response = requests.post(
            f"{XWMS_API_URL}sign-token-verify",
            json={
                "token": token,
                "client_id": CLIENT_ID,
                "domain": DOMAIN,
                "client_secret": CLIENT_SECRET
            },
            timeout=10
        )

        data = response.json()

        if data.get("status") != "success":
            return jsonify({"error": "Invalid or expired token."}), 400

        user_data = data.get("data", {})
        return jsonify({"message": "User verified successfully!", "user": user_data})

    except Exception as e:
        return jsonify({"error": str(e)}), 500

if __name__ == "__main__":
    app.run(debug=True)
```

### Step 4: Run your app 🎉

```bash
python app.py
```

Visit [http://localhost:5000/xwms/auth](http://localhost:5000/xwms/auth) — this will open the **XWMS login** page.

When the user finishes logging in, XWMS redirects them back to your `/xwms/validateToken` route.

---

## 🧠 Full Step-by-Step (Explained Like You’re 10)

### 🪄 Step 1: What we’re building

We want a simple Python website that lets people click **Login with XWMS**. XWMS will check who they are, and your app will get their verified info.

### ⚙️ Step 2: Install some tools

We need three helpers:

* `requests` → talk to the XWMS API
* `flask` → make a simple web server
* `python-dotenv` → load our secret keys from a `.env` file

Type this in your terminal:

```bash
pip install requests flask python-dotenv
```

### 🧾 Step 3: Set your secrets

We keep our **secrets** in a hidden file called `.env`.

```env
XWMS_CLIENT_ID=123456
XWMS_DOMAIN=your_domain_here # like example.com
XWMS_CLIENT_SECRET=mysecretkey
XWMS_REDIRECT_URI=http://localhost:5000/xwms/validateToken
XWMS_API_URL=https://api.xwms.com/
```

These tell XWMS who your app is and where to send people after they log in.

### 🌐 Step 4: Make your `app.py`

Here’s a simple **Flask** app that lets people log in via XWMS:

```python
from flask import Flask, redirect, request, jsonify
import requests, os
from dotenv import load_dotenv

load_dotenv()
app = Flask(__name__)

@app.route("/")
def home():
    return '<a href="/xwms/auth">🔑 Login with XWMS</a>'

@app.route("/xwms/auth")
def start_login():
    response = requests.post(
        os.getenv("XWMS_API_URL") + "sign-token",
        json={
            "client_id": os.getenv("XWMS_CLIENT_ID"),
            "domain": os.getenv("XWMS_CLIENT_DOMAIN"),
            "client_secret": os.getenv("XWMS_CLIENT_SECRET"),
            "redirect_url": os.getenv("XWMS_REDIRECT_URI"),
        }
    )
    data = response.json()
    return redirect(data["data"]["url"])

@app.route("/xwms/validateToken")
def verify_user():
    token = request.args.get("token")
    verify = requests.post(
        os.getenv("XWMS_API_URL") + "sign-token-verify",
        json={"token": token}
    )
    return jsonify(verify.json())

if __name__ == "__main__":
    app.run(debug=True)
```

### 💡 Step 5: Test it!

* Run: `python app.py`
* Go to: [http://localhost:5000](http://localhost:5000)
* Click the **Login with XWMS** link
* Log in via XWMS
* You’ll see your verified info printed as JSON

---

## 🧩 Advanced Usage (FastAPI Example)

If you prefer **FastAPI**, here’s a version that works the same way:

```python
from fastapi import FastAPI, Request
from fastapi.responses import RedirectResponse, JSONResponse
import requests, os
from dotenv import load_dotenv

load_dotenv()
app = FastAPI()

@app.get("/xwms/auth")
def auth():
    res = requests.post(
        os.getenv("XWMS_API_URL") + "sign-token",
        json={
            "client_id": os.getenv("XWMS_CLIENT_ID"),
            "domain": os.getenv("XWMS_CLIENT_DOMAIN"),
            "client_secret": os.getenv("XWMS_CLIENT_SECRET"),
            "redirect_url": os.getenv("XWMS_REDIRECT_URI"),
        }
    )
    login_url = res.json().get("data", {}).get("url")
    return RedirectResponse(login_url)

@app.get("/xwms/validateToken")
def callback(request: Request):
    token = request.query_params.get("token")
    res = requests.post(
        os.getenv("XWMS_API_URL") + "sign-token-verify",
        json={"token": token}
    )
    return JSONResponse(res.json())
```

Run with:

```bash
uvicorn app:app --reload
```

---

## ✅ Summary

* 🧩 XWMS works with any Python web framework
* 🔒 Always use `.env` to store secrets
* 🚀 The login flow uses `sign-token` and `sign-token-verify`
* 🧠 Works the same as in PHP or Laravel — just Python-style!

> This setup is simple, secure, and perfect for any Python app that wants to use **enterprise-grade login with XWMS**.
