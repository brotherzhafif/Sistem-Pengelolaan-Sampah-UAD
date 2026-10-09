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

login_req = urllib.request.Request(f'{BASE}/login', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
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
    headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}
))

try:
    res = opener.open(urllib.request.Request(f'{BASE}/weighing', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}))
    print('Weighing status:', res.status)
except urllib.error.HTTPError as e:
    err = e.read().decode('utf-8')
    with open('scratch/weighing_error.html', 'w', encoding='utf-8') as f:
        f.write(err)
    title = re.search(r'<title>(.*?)</title>', err)
    h1 = re.search(r'<h1[^>]*>(.*?)</h1>', err)
    print('Title:', title.group(1) if title else 'None')
    print('H1:', h1.group(1) if h1 else 'None')
    for m in re.finditer(r'<span class="text-red-500[^>]*>(.*?)</span>', err):
        print("Red span:", m.group(1))
    for m in re.finditer(r'class="text-xl font-bold[^>]*>(.*?)</h2>', err):
        print("Error header:", m.group(1))

