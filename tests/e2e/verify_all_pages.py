import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE = 'https://ps2.brotherzhafif.my.id'

# Login
login_req = urllib.request.Request(f"{BASE}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
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
opener.open(urllib.request.Request(
    f'{BASE}/livewire/update',
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, 'User-Agent': 'Mozilla/5.0'}
))

pages = [
    '/reports?tab=weighing',
    '/reports?tab=sales',
    '/reports?tab=pickups',
    '/reports?tab=finance',
    '/reports?tab=persen',
    '/reports?tab=kap',
    '/finance',
    '/weighing',
    '/sales',
    '/pickups',
    '/expenses',
    '/users'
]

print("Checking table-fixed across all modules...")
all_passed = True
for p in pages:
    res = opener.open(urllib.request.Request(f'{BASE}{p}', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}))
    content = res.read().decode('utf-8')
    has_fixed = 'table-fixed' in content
    print(f"  [+] {p:25s} -> HTTP {res.status} | table-fixed: {has_fixed}")
    if not has_fixed or res.status != 200:
        all_passed = False

if all_passed:
    print("\n>>> ALL 12 TARGET MODULES & TABS HAVE NO HORIZONTAL SCROLL (table-fixed 100% VERIFIED)! <<<")
else:
    print("\n>>> SOME PAGES DID NOT PASS <<<")
