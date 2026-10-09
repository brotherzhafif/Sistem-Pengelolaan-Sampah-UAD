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

# 1. GET /login
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
login_html = opener.open(login_req).read().decode('utf-8')
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snap_match = re.search(r'wire:snapshot="([^"]+)"', login_html)
login_snapshot = json.loads(html.unescape(snap_match.group(1)))

# 2. Authenticate via Livewire RPC
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
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
auth_res = opener.open(auth_req)
auth_body = auth_res.read().decode('utf-8')
print("Auth response:", auth_body[:400])

# 3. Access /kap dashboard
kap_req = urllib.request.Request(f"{BASE_URL}/kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
kap_resp = opener.open(kap_req)
print("KAP final URL:", kap_resp.geturl())
kap_page = kap_resp.read().decode('utf-8')
print("KAP Page HTML snippet:", kap_page[:400])
assert "Survei Perilaku (KAP) Civitas UAD" in kap_page, "KAP index page title missing!"
assert "Salin Link Survei" in kap_page, "Copy link button missing!"

# Find the snapshot for pages.kap.index
all_snaps = re.findall(r'wire:snapshot="([^"]+)"', kap_page)
snapshot = None
for s_raw in all_snaps:
    s_obj = json.loads(html.unescape(s_raw))
    if s_obj.get('memo', {}).get('name') == 'pages.kap.index':
        snapshot = s_obj
        break

assert snapshot is not None, "pages.kap.index snapshot not found!"
print("Found pages.kap.index component snapshot!")

# 4. Test viewSurvey modal call (Detail Responden)
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', kap_page).group(1)
payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(snapshot),
            "updates": {},
            "calls": [
                {"path": "", "method": "viewSurvey", "params": [1]}
            ]
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
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
try:
    res = opener.open(req_post)
    res_json = json.loads(res.read().decode('utf-8'))
    modal_html = res_json['components'][0].get('effects', {}).get('html', '')

    assert "Rincian Survei Responden" in modal_html, "Modal title missing!"
    assert "Knowledge" in modal_html, "Knowledge dimension missing!"
    assert "Attitude" in modal_html, "Attitude dimension missing!"
    assert "Practice" in modal_html, "Practice dimension missing!"
    assert "Kepuasan" in modal_html, "Satisfaction dimension missing from modal!"
    assert "Skor KAP" in modal_html, "Composite score missing from modal!"

    print("[SUCCESS] Admin KAP Dashboard and 5-Dimension Detail Modal fully verified!")
except urllib.error.HTTPError as e:
    err_body = e.read().decode('utf-8', errors='ignore')
    with open('scratch/view_survey_error.html', 'w', encoding='utf-8') as f:
        f.write(err_body)
    print("viewSurvey Error Status:", e.code)
    for line in err_body.splitlines():
        if any(w in line for w in ['"message":', 'class="exception_title"', 'class="exception_message"', 'ErrorException', 'TypeError', 'Undefined']):
            print("ERR LINE:", line[:250])

