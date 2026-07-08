import requests
from bs4 import BeautifulSoup
import time
import sys
import re
import json
import random

if len(sys.argv) < 3:
    print("Error: Geen inloggegevens meegegeven aan het script.")
    sys.exit(1)

# Credentials from PHP
USERNAME = sys.argv[1]
PASSWORD = sys.argv[2]

# Config
BASE_URL = "https://jotihunt.nl"
LOGIN_URL = f"{BASE_URL}/login"
DASHBOARD_URL = f"{BASE_URL}/scoutingGroup/dashboard"
HUNTS_URL = f"{BASE_URL}/hunts"

# A list of user-agent profiles to randomize requests
PROFILES = [
    {
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept-Language": "nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7",
        "Sec-Ch-Ua": '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
        "Sec-Ch-Ua-Mobile": "?0",
        "Sec-Ch-Ua-Platform": '"Windows"'
    },
    {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2.1 Safari/605.1.15",
        "Accept-Language": "en-GB,en;q=0.9",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin"
    },
    {
        "User-Agent": "Mozilla/5.0 (X11; Linux x86_64; rv:121.0) Gecko/20100101 Firefox/121.0",
        "Accept-Language": "nl,en-US;q=0.7,en;q=0.3",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin",
        "Upgrade-Insecure-Requests": "1"
    },
    {
        "User-Agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1",
        "Accept-Language": "nl-NL,nl;q=0.9",
        "Sec-Fetch-Dest": "document",
        "Sec-Fetch-Mode": "navigate",
        "Sec-Fetch-Site": "same-origin"
    },
    {
        "User-Agent": "Mozilla/5.0 (Linux; Android 14; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36",
        "Accept-Language": "en-US,en;q=0.9,nl;q=0.8",
        "Sec-Ch-Ua": '"Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
        "Sec-Ch-Ua-Mobile": "?1",
        "Sec-Ch-Ua-Platform": '"Android"'
    }
]

def main():
    print("Startende scraper voor Jotihunt portal...")
    
    selected_profile = random.choice(PROFILES)
    headers = {
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        "Connection": "keep-alive"
    }
    headers.update(selected_profile)

    session = requests.Session()
    session.headers.update(headers)

    try:
        login_page = session.get(LOGIN_URL, timeout=10)
        login_page.raise_for_status()
        
        soup = BeautifulSoup(login_page.text, 'html.parser')
        
        csrf_token = None
        token_input = soup.find('input', attrs={'name': '_token'})
        if token_input:
            csrf_token = token_input.get('value')
        else:
            meta_token = soup.find('meta', attrs={'name': 'csrf-token'})
            if meta_token:
                csrf_token = meta_token.get('content')

        if not csrf_token:
            print("Error: Kon geen CSRF token vinden op de login pagina.")
            sys.exit(1)
            
        print("CSRF Token gevonden. Bezig met inloggen...")
        time.sleep(random.uniform(1.2, 2.8))

        login_payload = {
            '_token': csrf_token,
            'email': USERNAME,
            'password': PASSWORD
        }
        
        session.headers.update({'Referer': LOGIN_URL})
        login_post = session.post(LOGIN_URL, data=login_payload, timeout=10)
        login_post.raise_for_status()

        time.sleep(random.uniform(1.5, 3.5))

        dashboard_page = session.get(DASHBOARD_URL, timeout=10)
        dashboard_page.raise_for_status()
        
        if "login" in dashboard_page.url.lower():
            print("Error: Inloggen is mislukt.")
            sys.exit(1)
            
        dashboard_soup = BeautifulSoup(dashboard_page.text, 'html.parser')
        
        time.sleep(random.uniform(1.5, 4.0))

        hunts_page = session.get(HUNTS_URL, timeout=10)
        hunts_page.raise_for_status()
        hunts_soup = BeautifulSoup(hunts_page.text, 'html.parser')

        print("Data succesvol opgehaald! Bezig met parseren...\n")
        
        scraped_data = {
            "deelgebied": None,
            "punten": {
                "totaal": 0,
                "categorieen": {}
            },
            "hunts": [],
            "opdrachten": [],
            "foto_opdrachten": []
        }

        deelgebied_header = dashboard_soup.find('h1', string=re.compile("Deelgebieden", re.IGNORECASE))
        if deelgebied_header:
            sterk_text = deelgebied_header.find_next('strong')
            if sterk_text:
                scraped_data["deelgebied"] = sterk_text.text.strip()

        totaal_div = dashboard_soup.find(string=re.compile("Totaal aantal punten:"))
        if totaal_div:
            scraped_data["punten"]["totaal"] = int(re.sub(r'\D', '', totaal_div.parent.text))
        
        categorie_namen = ["Hunts", "Tegenhunts", "Opdrachten", "Foto opdrachten", "Hints", "Strafpunten"]
        for cat in categorie_namen:
            cat_label = dashboard_soup.find('div', class_='font-bold', string=re.compile(cat))
            if cat_label:
                waarde_text = cat_label.parent.text.replace(cat, "").replace(":", "").strip()
                scraped_data["punten"]["categorieen"][cat] = int(waarde_text) if waarde_text.isdigit() else 0

        opdrachten_header = dashboard_soup.find('h2', string=re.compile("Ingestuurd opdrachten", re.IGNORECASE))
        if opdrachten_header:
            opdracht_rows = opdrachten_header.parent.find_all('div', class_=re.compile("border-b"))
            for row in opdracht_rows:
                a_tag = row.find('a')
                if not a_tag: continue 
                
                titel = a_tag.text.strip()
                opdracht_id = a_tag['href'].split('/')[-1]
                punten_str = row.contents[-1].strip().replace("pt.", "").strip()
                
                scraped_data["opdrachten"].append({
                    "id": int(opdracht_id) if opdracht_id.isdigit() else None,
                    "titel": titel,
                    "punten": int(punten_str) if punten_str.isdigit() else 0
                })

        foto_header = dashboard_soup.find('h2', string=re.compile("Foto-opdracht", re.IGNORECASE))
        if foto_header:
            foto_rows = foto_header.parent.find_all('div', class_=re.compile("border-b"))
            for row in foto_rows:
                a_tag = row.find('a')
                if not a_tag: continue 
                
                titel = a_tag.text.strip()
                opdracht_id = a_tag['href'].split('/')[-1]
                punten_str = row.contents[-1].strip().replace("pt.", "").strip()
                
                scraped_data["foto_opdrachten"].append({
                    "id": int(opdracht_id) if opdracht_id.isdigit() else None,
                    "titel": titel,
                    "punten": int(punten_str) if punten_str.isdigit() else 0
                })

        hunts_table = hunts_soup.find('tbody')
        if hunts_table:
            # Pak alle rijen, beperk tot de eerste 15
            rows = hunts_table.find_all('tr')[:15]
            for row in rows:
                cols = row.find_all('td')
                if len(cols) >= 5:
                    deelgebied = cols[0].text.strip()
                    huntcode = cols[1].text.strip()
                    status = cols[2].text.strip()
                    
                    punten_raw = cols[3].text.strip()
                    punten = int(punten_raw) if punten_raw.isdigit() else 0
                    
                    hunttijd = cols[4].text.strip()

                    scraped_data["hunts"].append({
                        "deelgebied": deelgebied,
                        "huntcode": huntcode,
                        "status": status,
                        "punten": punten,
                        "hunttijd": hunttijd
                    })

        # Output the scraped data as JSON to PHP
        print(json.dumps(scraped_data, indent=4, ensure_ascii=False))
        sys.exit(0)

    except requests.exceptions.RequestException as e:
        print(f"Error: Netwerkfout tijdens het scrapen: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"Error: Onverwachte fout: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()