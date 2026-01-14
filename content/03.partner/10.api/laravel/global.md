---
title: Global API (Laravel)
description: ""
layout: docs
---

# Global API (Laravel)

All responses use:

```json
{
  "status": "success",
  "message": "Human readable message",
  "data": { "..." : "payload" }
}
```

---

## Scenario (example flow)

1) Call **Info** to show status on your dashboard.  
2) Use **Countries** to populate a checkout form.  
3) Use **Projects** to show public projects.  

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
      "version": "v.2",
      "time": "2026-01-12T12:00:00Z"
    },
    "api": {
      "documentation": "https://docs.xwms.nl/"
    }
  }
}
```

**Helper**
```php
$info = XwmsApiHelper::info();
```

---

## 2) Countries

**Endpoint:** `POST /api/global/countries`  
**Auth:** client credentials

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

**Helper**
```php
$countries = XwmsApiHelper::getCountries();
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

**Helper**
```php
$projects = XwmsApiHelper::getProjects();
```
