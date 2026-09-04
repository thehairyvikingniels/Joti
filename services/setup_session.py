#!/usr/bin/env python3
"""
services/setup_session.py — Interactive helper to authenticate your Telegram user account
with Telethon once and save the persistent session file for the MTProto listener daemon.
"""

import os
import sys
import asyncio

try:
    from telethon import TelegramClient
except ImportError:
    print("Error: Telethon is not installed. Run: pip3 install telethon")
    sys.exit(1)

# Import loader from telegram_listener
from telegram_listener import API_ID, API_HASH, SESSION_NAME

async def main():
    print("=" * 65)
    print("       JOTIFY TELEGRAM USER ACCOUNT SETUP (MTProto)       ")
    print("=" * 65)

    if not API_ID or not API_HASH:
        print("Fout: TELEGRAM_API_ID of TELEGRAM_API_HASH ontbreekt in Site_Instellingen.")
        sys.exit(1)

    print(f"Using App API ID:   {API_ID}")
    print(f"Session File Path:  {SESSION_NAME}\n")

    client = TelegramClient(SESSION_NAME, int(API_ID), API_HASH)

    print("Connecting to Telegram MTProto servers...")
    await client.connect()

    if await client.is_user_authorized():
        me = await client.get_me()
        print(f"\n[OK] Reeds ingelogd als: {me.first_name} (@{me.username or 'geen_username'}) [ID: {me.id}]")
        print("Sessie is al actief en gereed voor gebruik door de listener daemon!")
    else:
        print("\nAccount is nog niet geautoriseerd.")
        print("Volg de instructies hieronder om eenmalig in te loggen:")
        print("1. Voer je telefoonnummer in (bijv. +31612345678)")
        print("2. Voer de verificatiecode in die je via Telegram ontvangt.")
        print("3. (Optioneel) Voer je 2FA wachtwoord in als je dat hebt ingesteld.\n")

        await client.start()
        me = await client.get_me()
        print(f"\n[SUCCES] Ingelogd als: {me.first_name} (@{me.username or 'geen_username'}) [ID: {me.id}]")
        print(f"Sessiebestand opgeslagen op: {SESSION_NAME}")

    await client.disconnect()
    print("\nSetup voltooid!")

if __name__ == "__main__":
    try:
        asyncio.run(main())
    except KeyboardInterrupt:
        print("\nAfgebroken door gebruiker.")
