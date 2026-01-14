---
title: User API (JS)
description: ""
layout: docs
---

# User API (JavaScript / Node.js)

These endpoints require **client credentials** and a valid user `sub`.
The `sub` is the **XWMS user id** (stable number/id for that person).
You get `sub` after a successful login in your app and store it in your own
`xwms_connections` table. Use that stored `sub` here.

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

1) User logs in via XWMS -> you store `sub` in `xwms_connections`.  
2) You call **User info** to show the profile.  
3) You call **Address CRUD** to list/create/update addresses.  

Below you see the exact response structure.

---

## 1) User info

**Endpoint:** `POST /api/get/user/info`  
**Auth:** client credentials  
**Scope:** `userinfo` for extra fields  

**Request**
```json
{ "sub": 12345, "socials": true }
```

**Expected response (shortened)**
```json
{
  "status": "success",
  "message": "User addresses retrieved successfully.",
  "data": {
    "user": {
      "sub": "12345",
      "name": "John Doe",
      "given_name": "John",
      "family_name": "Doe",
      "email": "john@example.com",
      "email_verified": true,
      "picture": "https://...",
      "birth_date": "2000-01-01",
      "gender": "male",
      "second_email": null,
      "country": "NL",
      "socials": [
        { "platform": "instagram", "username": "john", "url": "https://..." }
      ]
    }
  }
}
```

If your client does **not** have `userinfo` scope, the extra fields may be null.

**Helper (npm)**
```js
const info = await xwms.getUserInfo(sub, { socials: true });
```

---

## 2) Address CRUD

**Endpoint:** `POST /api/user/address`  
**Auth:** client credentials  
**Scope:** `useraddresses`

**Actions**
- `list`
- `create`
- `update`
- `delete`

### Create request example

```json
{
  "sub": 12345,
  "action": "create",
  "address": {
    "type": "billing",
    "name": "Home",
    "company_name": null,
    "firstname": "John",
    "lastname": "Doe",
    "phone": "+31 612345678",
    "email": "john@example.com",
    "tax_nr": null,
    "registration_nr": null,
    "access_code": null,
    "postal_code": "1012AB",
    "house_number": "10A",
    "street": "Mainstreet",
    "city": "Amsterdam",
    "country_id": 1
  }
}
```

### Expected response (shortened)

```json
{
  "status": "success",
  "message": "Address created successfully.",
  "data": {
    "address": {
      "id": 55,
      "type": "billing",
      "name": "Home",
      "firstname": "John",
      "lastname": "Doe",
      "city": "Amsterdam",
      "country": {
        "id": 1,
        "short_name": "NL",
        "name": "Netherlands",
        "phonecode": "+31",
        "is_eu_member": true
      }
    }
  }
}
```

**Helpers (npm)**
```js
const list = await xwms.userAddressCrud(sub, "list");
const created = await xwms.userAddressCrud(sub, "create", { address });
const updated = await xwms.userAddressCrud(sub, "update", { addressId: 55, address });
const removed = await xwms.userAddressCrud(sub, "delete", { addressId: 55 });
```
