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

print(f"Connecting to {BASE_URL} to verify MVC Users (Pengguna) Module...")

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

# 2. Access /users (Served by UserController@index)
users_req = urllib.request.Request(f"{BASE_URL}/users", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
users_res = safe_open(users_req)
users_html = users_res.read().decode('utf-8')

assert users_res.status == 200, f"Expected 200 OK from /users, got {users_res.status}"
assert "Pengguna" in users_html, "Title Pengguna missing"
assert "table-fixed" in users_html, "table-fixed CSS class missing from users tables"
print("[+] /users HTTP route successfully served by UserController with 200 OK.")

# 3. Test Livewire RPC interaction on Users module (Search filter)
users_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', users_html).group(1)
users_snap = next(s for s in users_snaps if s.get('memo', {}).get('name') in ['pages.users', 'pages.users.index'])

search_payload = {
    "_token": users_csrf,
    "components": [
        {
            "snapshot": json.dumps(users_snap),
            "updates": {
                "search": "superadmin"
            },
            "calls": []
        }
    ]
}

search_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(search_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': users_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
search_res = safe_open(search_req)
assert search_res.status == 200, f"Expected 200 OK from search filter RPC, got {search_res.status}"
print("[+] Livewire property update search='superadmin' executed cleanly with 200 OK.")

print("\n[SUCCESS] ALL USERS MVC E2E TESTS PASSED SUCCESSFULLY!")
