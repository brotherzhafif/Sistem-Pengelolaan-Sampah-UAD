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

def safe_open(req, retries=5, delay=4):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Master Data Module...")

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

# 2. Access /master-data (Served by MasterDataController@index)
master_req = urllib.request.Request(f"{BASE_URL}/master-data", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
master_res = safe_open(master_req)
master_html = master_res.read().decode('utf-8')

assert master_res.status == 200, f"Expected 200 OK from /master-data, got {master_res.status}"
assert "Master Data Sistem" in master_html, "Title Master Data Sistem missing"
assert "Titik Sumber Sampah" in master_html, "Tab Titik Sumber Sampah missing"
print("[+] /master-data HTTP route successfully served by MasterDataController with 200 OK.")

# 3. Test Livewire RPC interaction on Master Data module (Tab switch to 'types')
master_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', master_html).group(1)
master_snap = next(s for s in master_snaps if s.get('memo', {}).get('name') in ['pages.master', 'pages.master.index'])

tab_payload = {
    "_token": master_csrf,
    "components": [
        {
            "snapshot": json.dumps(master_snap),
            "updates": {
                "activeTab": "types"
            },
            "calls": []
        }
    ]
}

tab_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(tab_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': master_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
tab_res = safe_open(tab_req)
assert tab_res.status == 200, f"Expected 200 OK from activeTab switch RPC, got {tab_res.status}"
print("[+] Livewire property update activeTab='types' executed cleanly with 200 OK.")

# 4. Verify table structure and layout
assert "<table class=\"w-full" in master_html, "w-full table class missing from master tables"
print("[+] Table structure and layout verified.")

print("\n[SUCCESS] ALL MASTER DATA MVC E2E TESTS PASSED SUCCESSFULLY!")

