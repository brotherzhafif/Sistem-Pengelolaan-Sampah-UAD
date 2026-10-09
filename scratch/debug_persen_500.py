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
            "calls": [{"path": "", "method": "login", "params": []}]
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

reports_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0'})
reports_page = opener.open(reports_req).read().decode('utf-8')

all_snaps = re.findall(r'wire:snapshot="([^"]+)"', reports_page)
snapshot = None
for s_raw in all_snaps:
    s_obj = json.loads(html.unescape(s_raw))
    if s_obj.get('memo', {}).get('name') == 'pages.reports.index':
        snapshot = s_obj
        break

csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', reports_page).group(1)

payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(snapshot),
            "updates": {},
            "calls": [{"path": "", "method": "setTab", "params": ["persen"]}]
        }
    ]
}
req_post = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf_token,
        'User-Agent': 'Mozilla/5.0',
        'Accept': 'application/json',
    }
)
try:
    res = opener.open(req_post)
    print("Success:", res.status)
except urllib.error.HTTPError as e:
    err = e.read().decode('utf-8')
    with open('scratch/persen_500.html', 'w', encoding='utf-8') as f:
        f.write(err)
    match = re.search(r'"message":\s*"([^"]+)"', err)
    if match:
        print("Exception message:", match.group(1))
    else:
        title = re.search(r'<title>(.*?)</title>', err)
        if title:
            print("Title:", title.group(1))

