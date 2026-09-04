#!/usr/bin/env python3
"""
services/telegram_listener.py — Headless MTProto daemon for Jotify.
Connects as the registered Telegram user account, listens for incoming direct
messages from @Jotihunt_bot in real time (< 300ms), instantaneously forwards
messages to all active hunter subscribers, and ingests them into Jotify backend.
"""

import os
import sys
import time
import json
import asyncio
import logging
import urllib.request
import urllib.error

try:
    from telethon import TelegramClient, events
except ImportError:
    print("Error: telethon library is not installed. Install via: pip install telethon")
    sys.exit(1)

# Configure logging
logging.basicConfig(
    format='[%(asctime)s] %(levelname)s: %(message)s',
    level=logging.INFO
)
logger = logging.getLogger("telegram_listener")

# Database and settings auto-discovery
def load_settings_from_db():
    """Extracts database credentials from dblogin.php and queries Site_Instellingen."""
    dblogin_path = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "dblogin.php"))
    if not os.path.exists(dblogin_path):
        return {}

    try:
        with open(dblogin_path, "r", encoding="utf-8") as f:
            content = f.read()

        import re, subprocess
        u = re.search(r'\$username\s*=\s*["\']([^"\']+)["\']', content)
        p = re.search(r'\$password\s*=\s*["\']([^"\']+)["\']', content)
        d = re.search(r'\$dbname\s*=\s*["\']([^"\']+)["\']', content)

        if not (u and p and d):
            return {}

        cmd = [
            "mysql",
            f"-u{u.group(1)}",
            f"-p{p.group(1)}",
            d.group(1),
            "-B",
            "-N",
            "-e",
            "SELECT Instelling, Waarde FROM Site_Instellingen WHERE Instelling LIKE 'TELEGRAM%';"
        ]
        res = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        settings = {}
        for line in res.stdout.strip().splitlines():
            parts = line.split("\t", 1)
            if len(parts) == 2:
                settings[parts[0]] = parts[1]
        return settings
    except Exception as ex:
        logger.warning(f"Could not load settings from database: {ex}")
        return {}

db_settings = load_settings_from_db()

# Configuration (from environment, DB Site_Instellingen, or defaults)
API_ID = os.getenv("TELEGRAM_API_ID") or db_settings.get("TELEGRAM_API_ID", "")
API_HASH = os.getenv("TELEGRAM_API_HASH") or db_settings.get("TELEGRAM_API_HASH", "")
SESSION_NAME = os.getenv("TELEGRAM_SESSION", os.path.join(os.path.dirname(__file__), "jotihunt_user.session"))
INGEST_URL = os.getenv("JOTIFY_INGEST_URL", "https://joti.maarleveld.app/api/telegram_ingest.php")
INGEST_SECRET = os.getenv("JOTIFY_INGEST_SECRET") or db_settings.get("TELEGRAM_INGEST_SECRET", "")
TARGET_BOTS = ["Jotihunt_bot", "Jotihunt_test_bot"]

# Subscribers cache
subscribers_cache = []
last_cache_time = 0
CACHE_TTL = 30  # seconds


def fetch_subscribers_from_backend():
    """Fetches active user chat IDs and central group ID from Jotify API."""
    global subscribers_cache, last_cache_time
    now = time.time()
    if subscribers_cache and (now - last_cache_time) < CACHE_TTL:
        return subscribers_cache

    req_url = f"{INGEST_URL}?action=subscribers"
    req = urllib.request.Request(req_url)
    req.add_header("Authorization", f"Bearer {INGEST_SECRET}")
    req.add_header("User-Agent", "JotifyListener/1.0")

    try:
        with urllib.request.urlopen(req, timeout=5) as response:
            data = json.loads(response.read().decode('utf-8'))
            targets = []
            if data.get("success"):
                group = data.get("group_chat")
                if group:
                    targets.append(group)
                for user in data.get("subscribers", []):
                    chat_id = user.get("chat_id")
                    if chat_id and chat_id not in targets:
                        targets.append(chat_id)

                subscribers_cache = targets
                last_cache_time = now
                logger.info(f"Loaded {len(targets)} active forward targets from Jotify backend.")
                return targets
    except Exception as e:
        logger.warning(f"Could not refresh subscribers from backend: {e}")
        return subscribers_cache


def post_to_jotify_backend(text, sender, msg_id, forwarded_targets):
    """Posts incoming message to api/telegram_ingest.php with bearer secret."""
    payload = json.dumps({
        "text": text,
        "sender": sender,
        "message_id": msg_id,
        "forwarded_to": forwarded_targets
    }).encode('utf-8')

    req = urllib.request.Request(INGEST_URL, data=payload, method='POST')
    req.add_header("Content-Type", "application/json")
    req.add_header("Authorization", f"Bearer {INGEST_SECRET}")
    req.add_header("User-Agent", "JotifyListener/1.0")

    try:
        with urllib.request.urlopen(req, timeout=10) as response:
            res_body = response.read().decode('utf-8')
            logger.info(f"Jotify Backend Ingest Response: {res_body.strip()[:150]}")
            return True
    except Exception as e:
        logger.error(f"Failed to post message to Jotify backend: {e}")
        return False


async def main():
    if not API_ID or not API_HASH:
        logger.error("TELEGRAM_API_ID and TELEGRAM_API_HASH must be provided.")
        sys.exit(1)

    logger.info(f"Starting Telegram MTProto Client (Session: {SESSION_NAME})...")
    client = TelegramClient(SESSION_NAME, int(API_ID), API_HASH)

    await client.start()
    me = await client.get_me()
    logger.info(f"Logged in as: {me.first_name} (@{me.username or 'no_username'}) [ID: {me.id}]")

    # Initial subscribers load
    fetch_subscribers_from_backend()

    @client.on(events.NewMessage)
    async def handler(event):
        # Determine sender
        sender = await event.get_sender()
        sender_username = (getattr(sender, 'username', '') or '').lower()
        sender_title = getattr(sender, 'title', getattr(sender, 'first_name', 'Unknown'))
        
        # Only process messages from Jotihunt official bot or test bots
        is_target_bot = any(b.lower() == sender_username for b in TARGET_BOTS)
        if not is_target_bot and not event.is_private:
            return

        raw_text = event.raw_text or ''
        logger.info(f"Received message from @{sender_username or sender_title}: {raw_text[:100]}...")

        # 1. Instant Forwarding to all active subscriber hunters & groups
        forward_targets = fetch_subscribers_from_backend()
        forwarded_success = []

        if forward_targets:
            logger.info(f"Forwarding to {len(forward_targets)} subscribers...")
            for target in forward_targets:
                try:
                    # Native forward preserving official @Jotihunt_bot header
                    await client.forward_messages(target, event.message)
                    forwarded_success.append(target)
                except Exception as ex:
                    logger.warning(f"Could not forward message to target '{target}': {ex}")

        # 2. Ingest message into Jotify backend
        post_to_jotify_backend(
            text=raw_text,
            sender=f"@{sender_username}" if sender_username else str(sender_title),
            msg_id=event.id,
            forwarded_targets=forwarded_success
        )

    logger.info("Listening for messages from @Jotihunt_bot. Press Ctrl+C to stop.")
    await client.run_until_disconnected()


if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        logger.info("Listener stopped by user.")
