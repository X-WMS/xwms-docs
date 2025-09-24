XWMS Documentation

Welcome to the documentation for XWMS — a modern, secure, and scalable platform for managing users, partners, authentication, API clients, and more.

This repository contains structured documentation for both:

End-users (logging in, registering, managing accounts)

Partners (developers or businesses integrating with XWMS via OAuth & API)

Bonus: Server Setup Docs for deployment and infrastructure

📘 Main Documentation Sections
1. Getting Started with XWMS

Description: A simplified guide to get started with logging in, registering, and managing your account in XWMS.

Page: /getting-started

Includes:

How to log in securely using email/password, OAuth (Google/Microsoft), and 2FA

Secure registration with email verification and spam protection

Managing account settings, devices, addresses, preferences, and sessions

➡️ Go to Guide

2. Getting Started with XWMS Authentication & Partner Setup

Description: Overview of user authentication flows and partner OAuth setup.

Page: /getting-started-auth

Includes:

Overview of authentication (OAuth, 2FA, suspicious login detection, recovery)

How partners (clients) can create OAuth apps and API clients

Secure configuration of scopes, secrets, allowed domains, and more

➡️ Go to Guide

🔐 End-User Documentation

Login Guide
: Secure login with 2FA, OAuth, and recovery flows

Register Guide
: Register securely with email confirmation and real name detection

Account Settings
: Manage your account, devices, addresses, notifications, etc.

🤝 Partner & Developer Docs

Partner Dashboard
: Create OAuth clients and manage integration settings

OAuth Integration Guide
: Learn how to integrate XWMS OAuth into your application (includes Laravel, JS, PHP, etc.)

⚙️ Server & Deployment Docs (Bonus)

While the primary focus is authentication and OAuth integration, we've included advanced Linux server setup documentation for developers and devops working on deployment.

Server Setup
: Reinstalling, hardening and configuring a Linux server (UFW, SSH, fail2ban, PHP, MySQL, firewall, etc.)

Using GitHub on Server
: Clone GitHub projects into /var/www, configure SSH access

MySQL Access via SSH Tunnel
: Connect securely to remote MySQL databases locally using SSH

File Permissions & Groups
: Set correct file permissions, create groups, assign users and ensure Laravel/Apache works smoothly

Domain Management
: Setup Apache virtual hosts, enable HTTPS using Certbot, and manage site configs

🧠 Summary for New Users & Developers
Area	Topics Covered
End-Users	Login, register, manage sessions, change settings, recovery
Partners	Create OAuth clients, scopes, domains, API secrets
Developers	Use Laravel or JS to authenticate with XWMS
Server Admins	Ubuntu setup, Apache, MySQL, GitHub, SSH, Certbot, Permissions
🧭 Navigation Icons

Every page in the documentation is marked with a helpful icon:

i-lucide-rocket → General getting started

i-lucide-shield-check → Authentication and security

i-lucide-server-cog → Server setup

i-lucide-github → GitHub and deployment

i-lucide-key → SSH / MySQL access

i-lucide-file → File permissions

i-lucide-cable → Domain and Apache management

📞 Support

If you need help:

Check the documentation at the paths above

For account/login issues, start at Login
 or Register

For OAuth/API issues, go to the Partner Dashboard

Still stuck? Visit the Support Page

📌 About XWMS

XWMS (Extended Web Management System) is designed to simplify secure authentication, user management, and partner integration for modern web applications.

This documentation helps streamline both the user experience and the developer integration process, making it easy to work with OAuth, APIs, and server infrastructure.