import urllib.request, ssl, re, json, html, http.cookiejar
ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
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
opener.open(urllib.request.Request(f'{BASE_URL}/livewire/update', data=json.dumps(auth_payload).encode('utf-8'), headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, 'User-Agent': 'Mozilla/5.0'}))

# Check /kap page to see how many surveys exist
kap_res = opener.open(urllib.request.Request(f'{BASE_URL}/kap', headers={'User-Agent': 'Mozilla/5.0'}))
kap_html = kap_res.read().decode('utf-8')

with open('scratch/kap_page.html', 'w', encoding='utf-8') as f:
    f.write(kap_html)

for m in re.finditer(r'([0-9\.,]+)\s*responden', kap_html, re.I):
    print("Responden match:", m.group(0))

for m in re.finditer(r'<h3[^>]*>(.*?)</h3>', kap_html):
    print("KAP H3:", m.group(1))

