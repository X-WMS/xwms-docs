---
title: Global API (JS)
description: ""
layout: docs
---

# Global API (JavaScript / Node.js)

These endpoints return global data about XWMS or public lists.
All responses use a consistent JSON envelope:

```json
{
  "status": "success",
  "message": "Human readable message",
  "data": { "..." : "payload" }
}
```

---

## Scenario (example flow)

1) You call **Info** to check the service status.  
2) You fetch **Countries** to power address forms.  
3) You fetch **Projects** to show the latest public projects.  

Below you see the **exact structure** you can expect from each endpoint.

---

## 1) Info

**Endpoint:** `GET /api/info`  
**Auth:** none

**Expected response (shortened)**

```json
{
  "status": "success",
  "message": "The kingdom of XWMS stands tall ...",
  "data": {
    "application": {
      "name": "XWMS",
      "description": "XWMS is a forward-thinking software company...",
      "version": "v.2",
      "locale": "en",
      "timezone": "Europe/Amsterdam",
      "time": "2026-01-12T12:00:00Z"
    },
    "client": {
      "ip": "203.0.113.10",
      "user_agent": "Mozilla/5.0 ..."
    },
    "api": {
      "documentation": "https://docs.xwms.nl/",
      "endpoints": {
        "general_info": "https://xwms.nl/api/info",
        "countries": "https://xwms.nl/api/global/countries",
        "projects": "https://xwms.nl/api/global/projects",
        "user_address": "https://xwms.nl/api/get/user/address",
        "user_info": "https://xwms.nl/api/get/user/info",
        "auth_signin": "https://xwms.nl/api/sign-token",
        "auth_verify": "https://xwms.nl/api/sign-token-verify"
      }
    }
  }
}
```

**Helper (npm)**
```js
const info = await xwms.getGlobalInfo();
```

---

## 2) Countries

**Endpoint:** `POST /api/global/countries`  
**Auth:** client credentials required

**Expected response (shortened)**

```json
{
  "status": "success",
  "message": "Countries retrieved successfully.",
  "data": {
    "countries": [
      {
        "id": 1,
        "short_name": "NL",
        "name": "Netherlands",
        "phonecode": "+31",
        "is_eu_member": true,
        "capital": "Amsterdam",
        "iso_alpha3": "NLD",
        "region": "Europe",
        "timezone": "Europe/Amsterdam",
        "currency_code": "EUR"
      }
    ]
  }
}
```

**Helper (npm)**
```js
const countries = await xwms.getCountries();
```

---

## 3) Projects

**Endpoint:** `GET /api/global/projects`  
**Auth:** none

**Expected response (shortened)**

```json
{
  "status": "success",
  "message": "Projects retrieved successfully.",
  "data": {
    "projects": [
      {
        "id": 10,
        "title": "Project Name",
        "description": "Short description",
        "image": "https://...",
        "link": "https://...",
        "is_new": true,
        "direct_redirect": false,
        "published_at": "2026-01-01T12:00:00Z",
        "categories": [
          { "id": 2, "name": "Automation" }
        ]
      }
    ]
  }
}
```

**Helper (npm)**
```js
const projects = await xwms.getProjects();
```
