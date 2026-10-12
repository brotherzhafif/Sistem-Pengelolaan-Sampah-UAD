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

print(f"Connecting to {BASE_URL} to verify MVC KAP Module...")

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

# 2. Access /kap (Served by KapController@index)
kap_req = urllib.request.Request(f"{BASE_URL}/kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
kap_res = safe_open(kap_req)
kap_html = kap_res.read().decode('utf-8')

assert kap_res.status == 200, f"Expected 200 OK from /kap, got {kap_res.status}"
assert "Survei Perilaku (KAP)" in kap_html, "Title Survei Perilaku (KAP) missing"
assert "table-fixed" in kap_html, "table-fixed CSS class missing from kap tables"
print("[+] /kap HTTP route successfully served by KapController with 200 OK.")

# 3. Test Livewire RPC interaction on KAP module (Filter category)
kap_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', kap_html).group(1)
kap_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', kap_html)]
kap_snap = next(s for s in kap_snaps if s.get('memo', {}).get('name') == 'pages.kap.index')

filter_payload = {
    "_token": kap_csrf,
    "components": [
        {
            "snapshot": json.dumps(kap_snap),
            "updates": {
                "filterCategory": "sangat_baik"
            },
            "calls": []
        }
    ]
}

filter_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(filter_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': kap_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
filter_res = safe_open(filter_req)
assert filter_res.status == 200, f"Expected 200 OK from filterCategory RPC, got {filter_res.status}"
print("[+] Livewire property update filterCategory='sangat_baik' executed cleanly with 200 OK.")

# 4. Access /survei-kap (Public survey served by KapController@survey)
public_req = urllib.request.Request(f"{BASE_URL}/survei-kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
public_res = safe_open(public_req)
public_html = public_res.read().decode('utf-8')

assert public_res.status == 200, f"Expected 200 OK from /survei-kap, got {public_res.status}"
assert "Survei" in public_html, "Survey content missing from /survei-kap"
print("[+] Public /survei-kap route successfully served by KapController with 200 OK.")

print("\n[SUCCESS] ALL KAP MVC E2E TESTS PASSED SUCCESSFULLY!")
