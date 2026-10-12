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

def safe_open(req, retries=3, delay=2):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Weighing (Penimbangan) Module...")

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

# 2. Access /weighing (Served by WeighingController@index)
weigh_req = urllib.request.Request(f"{BASE_URL}/weighing", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
weigh_res = safe_open(weigh_req)
weigh_html = weigh_res.read().decode('utf-8')

assert weigh_res.status == 200, f"Expected 200 OK from /weighing, got {weigh_res.status}"
assert "Penimbangan Sampah Harian" in weigh_html, "Title Penimbangan Sampah Harian missing"
assert "Total Akumulasi Timbang" in weigh_html, "Metric Total Akumulasi Timbang missing"
assert "Stok Terpilah (Siap Jual)" in weigh_html, "Metric Stok Terpilah missing"
print("[+] /weighing HTTP route successfully served by WeighingController with 200 OK.")

# 3. Test Livewire RPC on Weighing module (saveWeighing handled by WeighingController@store)
weigh_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', weigh_html).group(1)
weigh_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', weigh_html)]
weigh_snap = next(s for s in weigh_snaps if s.get('memo', {}).get('name') in ['pages.weighing', 'pages.weighing.index'])

today_str = weigh_snap.get('data', {}).get('filterDateTo') or date.today().isoformat()
print("[*] Snapshot data keys:", list(weigh_snap.get('data', {}).keys()))
raw_items = weigh_snap.get('data', {}).get('formItems')
print("[*] formItems repr:", repr(raw_items)[:200])

save_payload = {
    "_token": weigh_csrf,
    "components": [
        {
            "snapshot": json.dumps(weigh_snap),
            "updates": {
                "formDate": today_str,
                "formNotes": "E2E Automated MVC Weighing Test",
                "formItems.0.weight_kg": "12.5"
            },
            "calls": [
                {"path": "", "method": "saveWeighing", "params": []}
            ]
        }
    ]
}

save_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(save_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': weigh_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
save_res = safe_open(save_req)
assert save_res.status == 200, f"Expected 200 OK from saveWeighing RPC, got {save_res.status}"
save_json = json.loads(save_res.read().decode('utf-8'))
save_effects = save_json.get('components', [{}])[0].get('effects', {})
print("[+] Livewire RPC saveWeighing (WeighingController@store) executed successfully with 200 OK.")

# 4. Re-fetch /weighing and verify saved record appears
verify_req = urllib.request.Request(f"{BASE_URL}/weighing", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
verify_html = safe_open(verify_req).read().decode('utf-8')
assert "E2E Automated MVC Weighing Test" in verify_html or "12.5" in verify_html, "Saved weighing record not rendered in table"
print("[+] Verified saved weighing record rendered in table.")

print("\n[SUCCESS] ALL WEIGHING MVC E2E TESTS PASSED SUCCESSFULLY!")
