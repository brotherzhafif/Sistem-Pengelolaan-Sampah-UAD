import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0'})
login_html = opener.open(login_req).read().decode('utf-8')
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snap_match = re.search(r'wire:snapshot="([^"]+)"', login_html)
login_snapshot = json.loads(html.unescape(snap_match.group(1)))

auth_payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(login_snapshot),
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
        'User-Agent': 'Mozilla/5.0',
        'Accept': 'application/json',
    }
)
opener.open(auth_req)
print("Logged in!")

try:
    reports_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0'})
    res = opener.open(reports_req)
    print("Reports status:", res.status)
except urllib.error.HTTPError as e:
    content = e.read().decode('utf-8', errors='ignore')
    with open("scratch/reports_error.html", "w", encoding="utf-8") as f:
        f.write(content)
    print("Status:", e.code)
    
    # Parse title / exception / message
    lines = content.split('\n')
    for idx, l in enumerate(lines):
        if any(keyword in l.lower() for keyword in ['exception', 'error', 'stack-trace', 'class="exc']):
            print(f"L{idx}: {l[:150]}")

