# IITH Sanchari

Bus ticket booking for the IIT Hyderabad community — campus shuttle and outstation buses (Patancheru, Miyapur), developed in coordination with the IIT Hyderabad Transport Department.

**Live:** [transport.iith.online](https://transport.iith.online)

## Start here

If you're an AI agent (Claude, Gemini, Codex, or otherwise) or a new contributor picking this up:

1. Read [`docs/PROGRESS.md`](docs/PROGRESS.md) first — it has the project state, what's done, what's broken, and the full history of decisions and why they were made. Don't assume you have context from a prior conversation; this file is the actual continuity mechanism.
2. Read [`docs/DESIGN.md`](docs/DESIGN.md) before touching anything visual — colors, type, radius, and component patterns are all locked and documented there.
3. **After you make changes, append a dated entry to `docs/PROGRESS.md`.** This is not optional — it's how the next person/agent avoids re-discovering the same bugs and decisions.

## Stack

Static HTML/CSS/JS frontend, PHP 8 backend (no framework, no Composer, no npm/build step — Hostinger shared hosting), MySQL (not yet provisioned), Razorpay for payments. Deployed via GitHub → Hostinger Git auto-deploy. Full details in `docs/PROGRESS.md`.

## Folder structure

```
index.html, ticket.html, legal.html   ← served pages (repo root = public_html, don't move these)
assets/                                ← served CSS/JS/images
api/                                   ← served PHP endpoints (api/config.php is git-ignored — real secrets)
docs/                                  ← PROGRESS.md, DESIGN.md, historical planning docs
inspirations/                          ← locked design references, raw source image drops
```
