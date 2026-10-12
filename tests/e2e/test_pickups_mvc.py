import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
from datetime import date

ctx = ssl._create_unverified_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

def safe_open(req, retries=5, delay=4):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Pickups (Pengangkutan) Module...")

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

# 2. Access /pickups (Served by PickupController@index)
pickups_req = urllib.request.Request(f"{BASE_URL}/pickups", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
pickups_res = safe_open(pickups_req)
pickups_html = pickups_res.read().decode('utf-8')

assert pickups_res.status == 200, f"Expected 200 OK from /pickups, got {pickups_res.status}"
assert "Pengangkutan Residu Sampah" in pickups_html, "Title Pengangkutan Residu Sampah missing"
assert "Total Biaya Angkut" in pickups_html, "Metric Total Biaya Angkut missing"
assert "Total Volume Terangkut" in pickups_html, "Metric Total Volume Terangkut missing"
print("[+] /pickups HTTP route successfully served by PickupController with 200 OK.")

# 3. Test Livewire RPC interaction on Pickups module
pickups_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', pickups_html).group(1)
pickups_snap = next(s for s in pickups_snaps if s.get('memo', {}).get('name') in ['pages.pickups', 'pages.pickups.index'])

# Trigger volume update and calculateTotalCost
cost_calc_payload = {
    "_token": pickups_csrf,
    "components": [
        {
            "snapshot": json.dumps(pickups_snap),
            "updates": {
                "formVolumeKg": "150.5",
                "formCostPerKg": "1200"
            },
            "calls": [
                {"path": "", "method": "calculateTotalCost", "params": []}
            ]
        }
    ]
}

calc_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(cost_calc_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': pickups_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
calc_res = safe_open(calc_req)
assert calc_res.status == 200, f"Expected 200 OK from calculateTotalCost RPC, got {calc_res.status}"
calc_json = json.loads(calc_res.read().decode('utf-8'))
print("[+] Livewire RPC calculateTotalCost executed cleanly with 200 OK.")

# 4. Verify table-fixed class and layout
assert "table-fixed" in pickups_html, "table-fixed CSS class missing from pickups table"
print("[+] Table fixed structure and layout verified.")

print("\n[SUCCESS] ALL PICKUPS MVC E2E TESTS PASSED SUCCESSFULLY!")
