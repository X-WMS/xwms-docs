---
title: Server Setup & Deployment Guide
description: Complete guide to reinstall, secure, configure your Strato Ubuntu server and deploy GitHub projects.
navigation:
  icon: i-lucide-server-cog
seo:
  title: Server Setup & Deployment Guide
  description: Learn how to reinstall your server, configure firewall and SSH, deploy GitHub projects, and manage domains.
---

## Reinstalling the Server

Use the Strato control panel to reinstall Ubuntu 22.04, set a strong root password, and add your SSH public key.

- [Reinstalling the Server](/server/setup)
- [Creating an SSH Key with PuTTYgen](/server/setup)

---

## Firewall and Security Setup

Configure UFW firewall to allow HTTP, HTTPS, and SSH ports. Disable root SSH login, add a non-root user, install fail2ban and unattended-upgrades for security.

- [Firewall Configuration & Security](/server/setup)

---

## Web Server and Software Installation

Install Apache, PHP 8.3, MySQL, PhpMyAdmin and configure them for your web projects.

- [MySQL & Web Server Setup](/server/mysql)

---

## Deploying GitHub Projects

Generate SSH keys for GitHub, add keys to your GitHub account, clone repositories to `/var/www`, and set proper file permissions and groups.

- [GitHub Project Deployment](/server/github)
- [Managing File Permissions & Groups](/server/filepermissions)

---

## Composer and Frontend Setup

Install Composer and Node.js, run Laravel commands, and configure Apache virtual hosts.

- [Composer & Frontend Tools](/server/github)

---

## HTTPS Setup with Certbot

Use Certbot to obtain and install SSL certificates, enabling HTTPS on your domains.

- [Domain Management & HTTPS Setup](/server/domainmanagment)

---

## Accessing MySQL Remotely via SSH Tunnel

Create SSH tunnels for local database access without exposing MySQL publicly.

- [MySQL Remote Access](/server/mysql)

---

## Domain Management on Strato Server

Add new websites, configure Apache virtual hosts, manage permissions, and secure your domains with HTTPS.

- [Domain Management](/server/domainmanagment)

---

**For detailed steps, visit the linked sections or ask for specific instructions.**
