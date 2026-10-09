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

matches = re.findall(r'wire:snapshot="([^"]+)"', dash_html)
print(f"Found {len(matches)} snapshots on /dashboard:")
for i, m in enumerate(matches):
    parsed = json.loads(html.unescape(m))
    comp_memo = parsed.get('memo', {})
    name = comp_memo.get('name')
    print(f"  [{i}] name: {name}")
    if name == 'pages.dashboard.index':
        print(f"      Targeting component {name}!")
        kap_switch_payload = {
            "_token": dash_csrf,
            "components": [
                {
                    "snapshot": json.dumps(parsed),
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
            print("      Status:", res.status)
            res_data = json.loads(res.read().decode('utf-8'))
            effects = res_data.get('components', [{}])[0].get('effects', {})
            html_out = effects.get('html', '')
            if "Indeks Perilaku Pemilahan Sampah Kampus UAD" in html_out or "Pencapaian 4 Dimensi KAP" in html_out:
                print("      [SUCCESS] KAP Mode view rendered correctly!")
            else:
                print("      [WARNING] HTML returned but target text not found. Length:", len(html_out))
        except urllib.error.HTTPError as e:
            err = e.read().decode('utf-8')
            with open("scratch/dash_index_500.html", "w", encoding="utf-8") as f:
                f.write(err)
            match = re.search(r'"message":\s*"([^"]+)"', err)
            if match:
                print("      Exception message:", match.group(1))
            else:
                title = re.search(r'<title>(.*?)</title>', err)
                if title:
                    print("      Title:", title.group(1))
