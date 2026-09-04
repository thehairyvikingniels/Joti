---
name: jotihunt-game-domain
description: >-
  Comprehensive guide to Jotihunt game mechanics, rules, NATO phonetic areas, hunting/immunity workflows, counterhunts, assignments, scoring, API contracts, and how Jotify assists players.
---

# Jotihunt Game Domain & System Context

## 1. Game Overview & Structure
- **What is Jotihunt?**: A 26-hour real-time tactical foxhunt for Scouting groups across the province of Gelderland (Netherlands), organized annually during the third weekend of October (concurrent with global JOTA-JOTI).
- **Schedule**:
  - Starts **Saturday 10:00** and concludes **Sunday 12:00** (26 hours total).
  - Divided into **two 13-hour halves**:
    - **First Half**: Saturday 10:00 ??? Saturday 23:00.
    - **Second Half**: Saturday 23:00 ??? Sunday 12:00.
  - **Fox changeover (*Vossenwissel*)**: Around 23:00 (teams are inactive from 22:45 to 23:15). Fresh 3-person walking teams replace the first-half teams near the end location. Scores continue cumulatively.

## 2. Fox Teams (*Vossenteams*) & Areas (*Deelgebieden*)
- **Deelgebieden (Clusters)**: Named after the **NATO phonetic alphabet** (Alpha, Bravo, Charlie, Delta, Echo, Foxtrot, Golf, Hotel, etc.).
- **Fox Team Composition**: Each active team consists of 3 people walking through Gelderland to visit assigned scouting group clubhouses/homebases.
- **Fox Status States**:
  - ???? **Actief (Green)**: Walking / huntable.
  - ???? **Onderweg (Orange)**: Using relocation budget (still huntable).
  - ???? **Inactief (Red)**: Immune / not huntable (e.g. major transport by game wardens/jachtopzichters, during vossenwissel 22:45-23:15, or within 5 minutes of placing a counterhunt).

## 3. Hunting (*Hunten*) & 60-Minute Immunity Cooldown
- **The Hunt**: Hunters make physical contact with active walking foxes to obtain a unique huntcode and sticker photo.
- **Scoring**:
  - **Own cluster fox**: 6 points.
  - **Other permitted cluster fox**: 3 points.
  - **Happy Hour** (dynamically announced via spelsite): Double hunt points (e.g. 12 or 6 pts).
- **60-Minute Immunity Rule**:
  - After a hunt on Fox X, the scouting group **cannot hunt Fox X again for 60 minutes** (calculated from the timestamp recorded on the hunt).
  - Other fox teams may still be hunted during this hour.
- **Submission Window**: Huntcodes must be submitted via the spelsite within 30 minutes of the hunt.

## 4. Counter-hunt (*Tegenhunt*)
- A fox team (or assigned scouting group) places a counterhunt sticker near a target group's headquarters:
  - Within **450m ??? 500m** of the target group's homebase.
  - On a vertical surface between **0.5m and 1.7m** high.
  - Visible from an open public road or path (never on living objects except trees; never on movable objects).
- **Process & Scoring**:
  - Target group receives a Telegram notification containing the start time, compass direction, and brief explanation.
  - Initial score adjustment: **-10 points** upon start.
  - The group has **exact 30 minutes** to locate the sticker and submit the code online.
  - Finding and submitting the code in time awards **+20 points** (net +10 points).

## 5. Hints, Assignments (*Opdrachten*) & Coordinates
- **Hourly Hints**:
  - Published hourly on the website/API.
  - Cryptic puzzles containing 8-digit **Rijksdriehoek (RD)** coordinates (`XXXX YYYY`), WGS84 coordinates, or keywords/postcodes.
  - Worth **1 point** if solved and submitted correctly within **20 minutes**.
- **Assignments (*Opdrachten*)**:
  - Periodic creative, technical, or theatrical assignments published throughout the weekend.
  - Submissions require unedited, horizontal/landscape images (max 2MB JPG/PNG) or YouTube links, with the scouting group's physical logo/flag visibly present.
- **Continuous Photo Assignment (*Doorlopende foto-opdracht*)**:
  - Open all 26 hours, max 1 photo submission per hour, up to 5 points each.

## 6. Jotihunt Official API (v2.0)
- **Base URL**: `https://jotihunt.nl/api/2.0/`
- **Endpoints**:
  - `/subscriptions`: Participating scouting groups, club addresses, and lat/lon coordinates.
  - `/areas`: Fox team names, status (`green`/`orange`/`red`), and update timestamps.
  - `/articles`: News items, hints, and assignments.
  - `/photoAssignments`: Continuous photo assignments.
- **Rate Limit**: Hard limit of **30 calls per minute**. Exceeding returns HTTP 429; persistent violations result in IP blocks.

## 7. VOIP (Voice over IP)
- SIP server: `voip.jotihunt.nl`
- Organization / HQ reachable via shortcode **`800`** (test tone / waiting music on `810`).
- Maximum 2 registered devices (SIP clients like MicroSIP or Zoiper) per group.

## 8. How Jotify Maps to the Game
| Game Element | Jotify Implementation |
|---|---|
| Tactical Map & GIS | `kaarten.php` / `js/maps.js` with Mapbox GL JS, RD&harr;WGS84 converter, scout huts, hunter GPS tracking, and fox radius circles |
| Tactical Dispatch | `whiteboard.php` / `js/whiteboard.js` drag-and-drop car/hunter dispatch to NATO areas |
| Fox Status & Sync | `cron/areas.php`, `cron/articles.php`, `cron/subscriptions.php` background sync respecting API limits |
| Immunity Cooldown | `includes/topbar.php` & `js/app.js` live 60-minute countdown badges for hunted foxes |
| Score Tracking | `punten.php` and `cron/scraper_helper.php` syncing points, hunts, and assignments |
| HQ Projection | `kiosk.php` token-based auto-refreshing dashboard for big screens at headquarters |
| Hunter Mobile PWA | `js/gps.js`, Service Worker caching, and `offline.php` connectivity fallbacks |
| Instant Alerts | Web Push API engine (`cron/notifications.php`) for fox status updates, hints, and against-hunts |
