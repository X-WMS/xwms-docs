---
title: User API (PHP)
description: ""
layout: docs
---

# User API (PHP)

Requires client credentials and a valid `sub`.
The `sub` is the **XWMS user id** (stable id/number for that person).  
You receive it after login and store it in `xwms_connections`.

---

## Scenario (example flow)

1) User logs in via XWMS -> you store `sub`.  
2) You call **User info** to display profile.  
3) You call **Address CRUD** to manage addresses.  

---

## 1) User info

**Endpoint:** `POST /api/get/user/info`  
**Scope:** `userinfo`

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

**Helper**
```php
$info = (new XwmsApiHelperPHP())->getUserInfo($sub, ['socials' => true]);
```

---

## 2) Address CRUD

**Endpoint:** `POST /api/user/address`  
**Scope:** `useraddresses`

### Create request example
```json
{
  "sub": 12345,
  "action": "create",
  "address": {
    "type": "billing",
    "name": "Home",
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
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
      "city": "Amsterdam",
      "country": { "id": 1, "name": "Netherlands" }
    }
  }
}
```

**Helpers**
```php
$helper = new XwmsApiHelperPHP();
$list = $helper->userAddressCrud($sub, 'list');
$create = $helper->userAddressCrud($sub, 'create', ['address' => $address]);
$update = $helper->userAddressCrud($sub, 'update', [
    'address_id' => 55,
    'address' => $address,
]);
$delete = $helper->userAddressCrud($sub, 'delete', ['address_id' => 55]);
```
