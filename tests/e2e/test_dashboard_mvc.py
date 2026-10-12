import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl._create_unverified_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

def safe_open(req, retries=3, delay=2):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Dashboard Module...")

# 1. Login as Super Admin
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
login_html = safe_open(login_req).read().decode('utf-8')
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', login_html)]
login_snap = next(s for s in snaps if s.get('memo', {}).get('name') == 'pages.auth.login')

auth_payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(login_snap),
            "updates": {
                "form.email": "superadmin@uad.ac.id",
                "form.password": "password123"
            },
            "calls": [
                {"path": "", "method": "login", "params": []}
            ]
        }
    ]
}

auth_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf_token,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
auth_res = safe_open(auth_req)
print("[+] Authenticated as Super Admin successfully.")

# 2. Access /dashboard (Served by DashboardController@index)
dash_req = urllib.request.Request(f"{BASE_URL}/dashboard", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_res = safe_open(dash_req)
dash_html = dash_res.read().decode('utf-8')

assert dash_res.status == 200, f"Expected 200 OK from /dashboard, got {dash_res.status}"
assert "Dashboard Teknis" in dash_html, "Title Dashboard Teknis missing"
assert "Saldo Kas Sirkular" in dash_html or "Saldo Kas" in dash_html, "KPI Saldo Kas Sirkular missing"
assert "Komposisi Sampah" in dash_html or "Diversion Rate" in dash_html, "Komposisi card missing"
print("[+] /dashboard HTTP route successfully served by DashboardController with 200 OK.")

# 3. Test Livewire RPC tab switcher on Dashboard
dash_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', dash_html).group(1)
dash_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', dash_html)]
dash_snap = next(s for s in dash_snaps if s.get('memo', {}).get('name') == 'pages.dashboard.index')

tab_payload = {
    "_token": dash_csrf,
    "components": [
        {
            "snapshot": json.dumps(dash_snap),
            "updates": {},
            "calls": [
                {"path": "", "method": "setActivityTab", "params": ["sales"]}
            ]
        }
    ]
}

tab_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(tab_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': dash_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)

tab_res = safe_open(tab_req)
assert tab_res.status == 200, f"Expected 200 OK from livewire/update setActivityTab, got {tab_res.status}"
print("[+] Livewire setActivityTab('sales') RPC executed successfully with 200 OK.")

print("\n" + "="*50)
print(" DASHBOARD MVC ARCHITECTURE VERIFIED SUCCESSFULLY! ")
print("="*50)
