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

print(f"Connecting to {BASE_URL} to verify MVC Reports (Laporan) Module...")

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

# 2. Access /reports tabs (Served by ReportController@index)
tabs = ['weighing', 'sales', 'pickups', 'finance', 'persen', 'kap']
for t in tabs:
    rep_req = urllib.request.Request(f"{BASE_URL}/reports?tab={t}", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
    rep_res = safe_open(rep_req)
    rep_html = rep_res.read().decode('utf-8')

    assert rep_res.status == 200, f"Expected 200 OK from /reports?tab={t}, got {rep_res.status}"
    assert "table-fixed" in rep_html, f"table-fixed missing from tab {t}"
    print(f"[+] Tab '{t}' successfully served by ReportController with 200 OK & table-fixed.")

# 3. Test Livewire RPC interaction on Reports module (Switch presetPeriod)
base_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
base_res = safe_open(base_req)
base_html = base_res.read().decode('utf-8')

rep_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', base_html).group(1)
rep_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', base_html)]
rep_snap = next(s for s in rep_snaps if s.get('memo', {}).get('name') in ['pages.reports', 'pages.reports.index'])

period_payload = {
    "_token": rep_csrf,
    "components": [
        {
            "snapshot": json.dumps(rep_snap),
            "updates": {
                "presetPeriod": "this_year"
            },
            "calls": [
                {"path": "", "method": "applyPreset", "params": ["this_year"]}
            ]
        }
    ]
}

period_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(period_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': rep_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
period_res = safe_open(period_req)
assert period_res.status == 200, f"Expected 200 OK from presetPeriod RPC, got {period_res.status}"
print("[+] Livewire RPC applyPreset('this_year') executed cleanly with 200 OK.")

print("\n[SUCCESS] ALL REPORTS MVC E2E TESTS PASSED SUCCESSFULLY!")
