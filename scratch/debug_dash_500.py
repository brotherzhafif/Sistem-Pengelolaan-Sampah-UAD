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

dash_req = urllib.request.Request(f"{BASE_URL}/dashboard", headers={'User-Agent': 'Mozilla/5.0'})
dash_html = opener.open(dash_req).read().decode('utf-8')
dash_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', dash_html).group(1)
dash_snap_match = re.search(r'wire:snapshot="([^"]+)"', dash_html)
dash_snapshot = json.loads(html.unescape(dash_snap_match.group(1)))

kap_switch_payload = {
    "_token": dash_csrf,
    "components": [
        {
            "snapshot": json.dumps(dash_snapshot),
            "updates": {},
            "calls": [
                {"path": "", "method": "setDashboardMode", "params": ["kap"]}
            ]
        }
    ]
}
kap_switch_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(kap_switch_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': dash_csrf,
        'User-Agent': 'Mozilla/5.0',
        'Accept': 'application/json',
    }
)
try:
    res = opener.open(kap_switch_req)
    print("Success:", res.status)
except urllib.error.HTTPError as e:
    err_body = e.read().decode('utf-8')
    print("HTTP Error:", e.code)
    with open("scratch/dash_500.html", "w", encoding="utf-8") as f:
        f.write(err_body)
    # Search for exception message
    match = re.search(r'<title>(.*?)</title>', err_body)
    if match:
        print("Title:", match.group(1))
    for m in re.finditer(r'<span class="text-red-500[^>]*>(.*?)</span>', err_body):
        print("Red span:", m.group(1))
    for m in re.finditer(r'class="text-xl font-bold[^>]*>(.*?)</h2>', err_body):
        print("Error header:", m.group(1))

