# Subdomains for Pterodactyl

[![Release](https://img.shields.io/github/v/release/CCBlueX/Pterodactyl-Extension-Subdomains?style=flat-square)](https://github.com/CCBlueX/Pterodactyl-Extension-Subdomains/releases/latest)
[![Downloads](https://img.shields.io/github/downloads/CCBlueX/Pterodactyl-Extension-Subdomains/total?style=flat-square)](https://github.com/CCBlueX/Pterodactyl-Extension-Subdomains/releases)
[![Pterodactyl](https://img.shields.io/badge/Pterodactyl-2.0-0e4688?style=flat-square)](https://pterodactyl.io)
[![License](https://img.shields.io/github/license/CCBlueX/Pterodactyl-Extension-Subdomains?style=flat-square)](LICENSE)

Let users give their servers a name like `play.example.com`, on your Cloudflare, Bunny DNS or PowerDNS domains.

![Server subdomains](docs/screenshots/server.png)

## Install

1. Download `subdomains-<version>.pteroext` from the [latest release](https://github.com/CCBlueX/Pterodactyl-Extension-Subdomains/releases/latest), then install and enable it under **Admin → Extensions**.
2. Under **Admin → Subdomains → Domains**, add a domain with its DNS provider's API token and click **Test connection**.
3. Optional: pick which eggs may use it and adjust the record templates.

## Features

- **Self-service**: users create, re-point and delete subdomains, pick the allocation and add a note.
- **Record templates**: A, AAAA or CNAME plus optional SRV with placeholders, per egg. Minecraft gets its SRV record out of the box.
- **Three DNS providers**: Cloudflare, Bunny DNS and PowerDNS, with encrypted credentials per domain.
- **You stay in control**: limits per server, reserved names and patterns, eggs per domain, and an overview of every subdomain.
- **Stays correct**: records follow allocation and template changes, fall back to the primary allocation and disappear with the server.

![Admin domains](docs/screenshots/admin.png)

## Permissions

Subusers get a **Subdomains** group in the server's **Users** tab: see, create, change and delete. The older "manage" permission still covers all three changes.

---

Inspired by the Pelican plugins Subdomains and MC-HOST24 Subdomains.
