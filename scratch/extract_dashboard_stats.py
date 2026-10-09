import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl._create_unverified_context()
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE_URL = 'https://ps2.brotherzhafif.my.id'
login_req = urllib.request.Request(f'{BASE_URL}/login', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
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

dash_req = urllib.request.Request(f'{BASE_URL}/dashboard', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_html = opener.open(dash_req).read().decode('utf-8')

# Extract Total
total_m = re.search(r'Komposisi Sampah Kampus</h3>.*?<span class="text-xs font-mono font-bold text-slate-700">\s*([^<]+)\s*</span>', dash_html, re.DOTALL)
if total_m:
    print('Total Komposisi:', total_m.group(1).strip())

# Extract Categories
for m in re.finditer(r'(Organik|Anorganik|Residu)</span>\s*<span class="font-mono[^"]*">\s*([^<]+)\s*</span>\s*<span class="[^"]*">\s*([^<]+)\s*</span>', dash_html):
    print(f'{m.group(1)}: {m.group(2).strip()} ({m.group(3).strip()})')

# Extract Diversion Rate
m_div = re.search(r'<span class="font-mono text-xl font-bold text-indigo-700">\s*([^<]+)\s*</span>\s*<span class="text-xs text-indigo-500 font-bold">%</span>', dash_html)
if m_div:
    print('Diversion Rate:', m_div.group(1).strip() + '%')

