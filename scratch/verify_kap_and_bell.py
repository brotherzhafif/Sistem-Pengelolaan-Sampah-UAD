import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
import time

time.sleep(3)

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE_URL = 'https://ps2.brotherzhafif.my.id'

# 1. Login
login_req = urllib.request.Request(f'{BASE_URL}/login', headers={'User-Agent': 'Mozilla/5.0'})
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
    f'{BASE_URL}/livewire/update',
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, 'User-Agent': 'Mozilla/5.0'}
))

# 2. Check /reports?tab=kap directly
rep_req = urllib.request.Request(f'{BASE_URL}/reports?tab=kap', headers={'User-Agent': 'Mozilla/5.0'})
rep_resp = opener.open(rep_req)
assert rep_resp.status == 200, f'Expected 200, got {rep_resp.status}'
rep_html = rep_resp.read().decode('utf-8')

print('--- Verification Results ---')
has_fab = 'fixed bottom-6 right-6' in rep_html or 'fixed bottom-8 right-8' in rep_html
print('1. Floating Bell at Bottom-Right:', has_fab)
has_upward = 'bottom-full mb-3' in rep_html
print('2. Notification Dropdown Opens Upwards:', has_upward)

has_kap_chart = 'Grafik Indeks per Konstruk Perilaku' in rep_html
print('3. Grafik Indeks per Konstruk Perilaku present:', has_kap_chart)

has_svg_chart = 'viewBox="0 0 1000 240"' in rep_html and 'barGradKnowledge' in rep_html
print('4. Responsive SVG Bar Chart present:', has_svg_chart)

has_proto_table = 'Indeks per Konstruk' in rep_html and 'Skala Asli' in rep_html
print('5. Prototype Indeks per Konstruk Table present:', has_proto_table)

has_k_items = 'Detail per Item Knowledge' in rep_html
print('6. Detail per Item Knowledge present:', has_k_items)

assert has_fab, 'Notification FAB not found at bottom-right!'
assert has_upward, 'Notification dropdown upward positioning not found!'
assert has_kap_chart, 'KAP Chart title not found!'
assert has_svg_chart, 'KAP SVG Bar Chart not found!'
assert has_proto_table, 'Prototype table not found!'

print('ALL CHECKS PASSED SUCCESSFULLY!')

