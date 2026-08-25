---
name: joti-browser-testing
description: >-
  Runbook for executing automated end-to-end browser and view integration tests across all user privilege roles using headless Chromium and Selenium.
---

# Jotify Browser & Multi-Role Testing

## 1. Test User Accounts & Privilege Matrix
| Role | Username | Password | Privilege Level | Accessible Areas |
| :--- | :--- | :--- | :---: | :--- |
| **Guest / Kiosk** | `Test0` | `Test0!!!!!` | `0` | `home.php`, `vossen.php`, `punten.php`, `kaarten.php`, `instellingen.php` |
| **Vossenjager (Hunter)** | `Test1` | `Test1!!!!!` | `1` | All hunter views (`voslocaties.php`, `whiteboard.php`, `autos.php`, `hints.php`, `opdrachten.php`) |
| **Admin** | `Test2` | `test2!!!!!` | `2` | Admin user portal, service accounts, database manager, cronjobs |
| **Superadmin** | `Test3` | `Test3!!!!!` | `3` | Full system access, site settings, global notifications |

## 2. Authentication Contract
- Login endpoint: `POST /login.php`
- Form fields: `username` and `pswd` (note: `pswd`, not `password`).

## 3. Automated Selenium Python Pattern
When testing in Python, launch headless Chrome:
```python
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

options = Options()
options.add_argument('--headless=new')
options.add_argument('--no-sandbox')
options.add_argument('--disable-dev-shm-usage')
options.add_argument('--ignore-certificate-errors')

driver = webdriver.Chrome(options=options)
```

## 4. Key Verification Checklists
1. **`home.php` Feeds**:
   - Verify `gebeurtenissen` (recent events), `autosonderweg` (cars en route), and `invulgegevens` (hunter quick data) populate via AJAX without JS console errors.
2. **`kaarten.php` GIS & Iframes**:
   - Switch to `iframe01` frame and verify Mapbox initializes with `mapboxgl-map` class.
   - Verify game half filters (`helft1`, `helft2`) and fox team layers toggle properly.
3. **`opdrachten.php` & `hints.php` Task Claiming**:
   - Locate button `toewijzingen-btn-opdracht-{id}`.
   - Click to claim task -> verify text becomes *"Stop hiermee"*.
   - Click again to release task -> verify text reverts to *"Ga hiermee aan de slag"*.
4. **`admin/cronjobs.php` Countdown**:
   - Verify `cron_exec_next_0` decreases every second.
   - Verify disabled cronjobs display `" - disabled - "`.
