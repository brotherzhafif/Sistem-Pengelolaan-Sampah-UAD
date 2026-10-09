import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
import time

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE_URL = "https://ps2.brotherzhafif.my.id"

# Wait a few seconds for GitHub Actions deploy if needed
time.sleep(5)

print("1. Authenticating as Super Admin...")
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
login_html = opener.open(login_req).read().decode('utf-8')
csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snap_match = re.search(r'wire:snapshot="([^"]+)"', login_html)
login_snap = json.loads(html.unescape(snap_match.group(1)))

auth_payload = {
    '_token': csrf,
    'components': [{
        'snapshot': json.dumps(login_snap),
        'updates': {'form.email': 'superadmin@uad.ac.id', 'form.password': 'password123'},
        'calls': [{'path': '', 'method': 'login', 'params': []}]
    }]
}
auth_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
opener.open(auth_req)
print("[+] Authenticated.")

pages_to_test = [
    '/dashboard',
    '/reports',
    '/finance',
    '/weighing',
    '/sales',
    '/pickups',
    '/expenses',
    '/kap',
    '/users'
]

print("\n2. Verifying pages for floating notification bell and absence of redundant unit selector...")
for path in pages_to_test:
    req = urllib.request.Request(f"{BASE_URL}{path}", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
    resp = opener.open(req)
    content = resp.read().decode('utf-8')
    assert resp.status == 200, f"Page {path} returned status {resp.status}"

    has_floating_bell = ('fixed top-3.5 right-4' in content) and ('SRS M10' in content or 'Peringatan' in content or 'Notifikasi Sistem' in content)
    print(f"  [+] {path:15s} -> HTTP {resp.status} | Floating Notification Bell: {has_floating_bell}")
    assert has_floating_bell, f"Floating notification bell missing on {path}!"

    if path == '/dashboard':
        # Check that redundant unit dropdown is gone
        has_unit_selector = 'Semua Kampus (Pusat UAD)' in content and '<select' in content and 'Unit:' in content
        print(f"  [+] Dashboard Unit Selector present: {has_unit_selector} (expected False)")
        assert not has_unit_selector, "Redundant unit selector still found in Dashboard header!"

print("\n=======================================================")
print(" ALL CHECKS PASSED: FLOATING BELL ACROSS ALL PAGES & UNIT SELECTOR REMOVED ")
print("=======================================================")

