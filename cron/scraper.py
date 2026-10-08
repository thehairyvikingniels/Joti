import requests
from bs4 import BeautifulSoup
import time
import sys
import os
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
HUNTER_URL = f"{BASE_URL}/hunter"
HUNTER_NEW_URL = f"{BASE_URL}/hunter/new"

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

def scrape_hunters(session):
    """Haalt alle geregistreerde hunters op van https://jotihunt.nl/hunter en downloadt de PDF raampassen."""
    hunters_list = []
    try:
        hunter_page = session.get(HUNTER_URL, timeout=12)
        hunter_page.raise_for_status()
        hunter_soup = BeautifulSoup(hunter_page.text, 'html.parser')
        
        base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        abs_passes_dir = os.path.join(base_dir, "media", "hunter_passes")
        os.makedirs(abs_passes_dir, exist_ok=True)

        hunter_table = hunter_soup.find('table')
        if hunter_table and hunter_table.find('tbody'):
            for row in hunter_table.find('tbody').find_all('tr'):
                cols = row.find_all('td')
                if len(cols) >= 6:
                    h_type = cols[0].text.strip().lower()
                    h_name = cols[1].text.strip()
                    h_phone = cols[2].text.strip()
                    h_code = cols[3].text.strip()
                    h_plate = cols[4].text.strip().upper()
                    
                    actions_td = cols[5]
                    download_link = actions_td.find('a', href=re.compile(r'/hunter/download/(\d+)'))
                    portal_id = None
                    if download_link and download_link.get('href'):
                        m = re.search(r'/hunter/download/(\d+)', download_link['href'])
                        if m:
                            portal_id = int(m.group(1))

                    pdf_rel_path = None
                    if portal_id:
                        pdf_filename = f"{portal_id}_{h_code}.pdf"
                        abs_pdf_path = os.path.join(abs_passes_dir, pdf_filename)
                        pdf_rel_path = f"media/hunter_passes/{pdf_filename}"
                        
                        try:
                            if not os.path.exists(abs_pdf_path) or os.path.getsize(abs_pdf_path) == 0:
                                dl_url = f"{BASE_URL}/hunter/download/{portal_id}"
                                pdf_resp = session.get(dl_url, timeout=15)
                                if pdf_resp.status_code == 200 and len(pdf_resp.content) > 100:
                                    with open(abs_pdf_path, 'wb') as pf:
                                        pf.write(pdf_resp.content)
                        except Exception as pe:
                            print(f"Waarschuwing: PDF downloaden mislukt voor hunter {portal_id}: {pe}", file=sys.stderr)

                    hunters_list.append({
                        "portal_id": portal_id,
                        "type": h_type,
                        "name": h_name,
                        "phone": h_phone,
                        "code": h_code,
                        "license_plate": h_plate,
                        "pdf_file": pdf_rel_path
                    })
    except Exception as e:
        print(f"Waarschuwing: Fout bij ophalen van hunters: {e}", file=sys.stderr)
    return hunters_list

def register_hunter(session, h_type, h_name, h_phone, h_plate=""):
    """Meldt een nieuw voertuig/hunter aan via https://jotihunt.nl/hunter/new."""
    new_page = session.get(HUNTER_NEW_URL, timeout=12)
    new_page.raise_for_status()
    new_soup = BeautifulSoup(new_page.text, 'html.parser')
    
    csrf_token = None
    token_input = new_soup.find('input', attrs={'name': '_token'})
    if token_input:
        csrf_token = token_input.get('value')
    if not csrf_token:
        meta_token = new_soup.find('meta', attrs={'name': 'csrf-token'})
        if meta_token:
            csrf_token = meta_token.get('content')
            
    if not csrf_token:
        raise ValueError("Kon geen CSRF token vinden op hunter/new pagina.")
        
    payload = {
        '_token': csrf_token,
        'type': h_type,
        'name': h_name,
        'telephone': h_phone,
        'license_plate': h_plate
    }
    
    session.headers.update({'Referer': HUNTER_NEW_URL})
    post_res = session.post(HUNTER_URL, data=payload, timeout=12)
    post_res.raise_for_status()
    time.sleep(random.uniform(1.0, 2.0))
    return True

def sync_and_register_vehicles(session, local_vehicles):
    """
    Vergelijkt lokale voertuigen met de geregistreerde hunters op Jotihunt.nl.
    Meldt ontbrekende lokale voertuigen automatisch aan.
    Downloadt PDF raampassen (alleen als ze nog niet lokaal bestaan).
    """
    portal_hunters = scrape_hunters(session)
    if not local_vehicles:
        return portal_hunters, []

    # Bepaal welke portal hunters er al zijn
    registered_plates = set()
    registered_names = set()
    for ph in portal_hunters:
        plate = ph.get('license_plate', '').replace('-', '').replace(' ', '').upper()
        if plate:
            registered_plates.add(plate)
        name = ph.get('name', '').strip().lower()
        if name:
            registered_names.add(name)

    newly_registered = []
    for lv in local_vehicles:
        l_type = lv.get('type', 'car').lower()
        l_plate = lv.get('clean_plate', '').replace('-', '').replace(' ', '').upper()
        l_name = lv.get('naam', '').strip()
        if '(' in l_name and ')' in l_name:
            clean_name = re.sub(r'\s*\([^)]*\)$', '', l_name).strip()
            if clean_name:
                l_name = clean_name
        l_phone = lv.get('telefoon', '').strip() or '0600000000'

        is_already_on_portal = False
        if l_type in ['car', 'motorcycle', 'scooter'] and l_plate:
            if l_plate in registered_plates:
                is_already_on_portal = True
        elif l_name and l_name.lower() in registered_names:
            is_already_on_portal = True

        if not is_already_on_portal:
            print(f"Nieuw lokaal voertuig gevonden dat nog niet op Jotihunt.nl staat: {l_name} ({l_type}, {lv.get('kenteken')}). Bezig met automatisch aanmelden...")
            try:
                portal_type = 'motorcycle' if l_type == 'scooter' else (l_type if l_type in ['car', 'motorcycle', 'bike', 'foot'] else 'other')
                plate_arg = lv.get('kenteken', '') if (l_type in ['car', 'motorcycle', 'scooter'] and not lv.get('kenteken', '').startswith(('FIETS', 'VOET', 'HELI', 'OVERIG', 'BALLON', 'UNIT'))) else ''
                register_hunter(session, portal_type, l_name, l_phone, plate_arg)
                newly_registered.append(lv)
                print(f" -> Succesvol aangemeld op Jotihunt.nl!")
                if plate_arg and l_plate:
                    registered_plates.add(l_plate)
                registered_names.add(l_name.lower())
            except Exception as re_err:
                print(f"Waarschuwing: Aanmelden mislukt voor {l_name}: {re_err}", file=sys.stderr)

    # Als er nieuwe voertuigen zijn aangemeld, haal de bijgewerkte lijst opnieuw op
    if newly_registered:
        time.sleep(random.uniform(1.5, 3.0))
        portal_hunters = scrape_hunters(session)

    return portal_hunters, newly_registered

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
        
        # Check of er een specifieke actie is aangevraagd om een hunter te registreren
        if "--register-hunter" in sys.argv:
            idx = sys.argv.index("--register-hunter")
            reg_args = sys.argv[idx+1:]
            if len(reg_args) < 3:
                print("Error: Minimaal type, naam en telefoonnummer vereist voor registratie.")
                sys.exit(1)
            h_type = reg_args[0]
            h_name = reg_args[1]
            h_phone = reg_args[2]
            h_plate = reg_args[3] if len(reg_args) > 3 else ""
            
            print(f"Bezig met registreren van hunter: {h_name} ({h_type})...")
            register_hunter(session, h_type, h_name, h_phone, h_plate)
            print("Hunter succesvol geregistreerd op Jotihunt.nl! Ophalen van bijgewerkte lijst...")
            time.sleep(1.0)
            updated_hunters = scrape_hunters(session)
            print(json.dumps({
                "status": "success",
                "registered": True,
                "hunter": {
                    "type": h_type,
                    "name": h_name,
                    "phone": h_phone,
                    "license_plate": h_plate
                },
                "hunters": updated_hunters
            }, indent=4, ensure_ascii=False))
            sys.exit(0)

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
            "foto_opdrachten": [],
            "telegram_code": None,
            "hunters": []
        }

        # Look for group_id in links or page text e.g. /scoutingGroup/12
        group_id_match = re.search(r'/(?:scoutingGroup|scoutinggroep|groep|subscriptions)/(\d+)', dashboard_page.text)
        if group_id_match:
            scraped_data["group_id"] = int(group_id_match.group(1))
        else:
            scraped_data["group_id"] = None

        # Look for group name in title or heading
        group_name = None
        title_tag = dashboard_soup.find('title')
        if title_tag and '-' in title_tag.text:
            parts = [p.strip() for p in title_tag.text.split('-')]
            for p in parts:
                if p.lower() not in ['jotihunt', 'dashboard', 'home', 'inloggen']:
                    group_name = p
                    break

        if not group_name:
            for heading in dashboard_soup.find_all(['h1', 'h2', 'span', 'p']):
                txt = heading.text.strip()
                if any(k in txt.lower() for k in ['scouting', 'groep']):
                    if len(txt) < 60 and not any(s in txt.lower() for s in ['ingestuurd', 'dashboard', 'deelgebieden', 'punten', 'hunts', 'foto-opdracht']):
                        group_name = txt
                        break

        scraped_data["group_name"] = group_name

        # Look for Telegram registration code e.g. "/register ev8Noa"
        telegram_match = re.search(r'/register\s+([A-Za-z0-9]+)', dashboard_page.text)
        if telegram_match:
            scraped_data["telegram_code"] = telegram_match.group(1)

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

                # Extract remarks/opmerkingen if present in child tags or title attributes
                opmerkingen = None
                remark_elem = row.find(['p', 'span', 'small', 'i', 'div'], class_=re.compile("text-muted|text-gray|italic|comment|remark|opmerking|feedback", re.IGNORECASE))
                if remark_elem and remark_elem != a_tag:
                    opmerkingen = remark_elem.text.strip()
                elif row.get('title'):
                    opmerkingen = row.get('title', '').strip()

                scraped_data["foto_opdrachten"].append({
                    "id": int(opdracht_id) if opdracht_id.isdigit() else None,
                    "titel": titel,
                    "punten": int(punten_str) if punten_str.isdigit() else 0,
                    "opmerkingen": opmerkingen
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

        local_vehicles = []
        if "--local-vehicles" in sys.argv:
            try:
                lv_idx = sys.argv.index("--local-vehicles")
                if lv_idx + 1 < len(sys.argv):
                    local_vehicles = json.loads(sys.argv[lv_idx + 1])
            except Exception as je:
                print(f"Waarschuwing: Kon --local-vehicles JSON niet parsen: {je}", file=sys.stderr)

        print("Hunters vergelijken, aanmelden en raampassen synchroniseren...")
        time.sleep(random.uniform(1.0, 2.0))
        portal_hunters, newly_registered = sync_and_register_vehicles(session, local_vehicles)
        scraped_data["hunters"] = portal_hunters
        scraped_data["newly_registered_count"] = len(newly_registered)

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