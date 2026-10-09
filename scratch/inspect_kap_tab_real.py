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

# Now get reports page snapshot, but search for component for reports.index
rep_req = urllib.request.Request(f'{BASE_URL}/reports', headers={'User-Agent': 'Mozilla/5.0'})
rep_html = opener.open(rep_req).read().decode('utf-8')

# Find all wire:snapshot in HTML
for m in re.finditer(r'wire:snapshot="([^"]+)"', rep_html):
    snap_str = html.unescape(m.group(1))
    snap_data = json.loads(snap_str)
    memo = snap_data.get('memo', {})
    name = memo.get('name')
    print("Found component:", name)
    if 'reports' in name or 'report' in name:
        target_snap = snap_data
        break

# Now call setTab('kap') on target_snap!
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', rep_html).group(1)
tab_payload = {
    '_token': csrf_token,
    'components': [{
        'snapshot': json.dumps(target_snap),
        'updates': {},
        'calls': [{'path': '', 'method': 'setTab', 'params': ['kap']}]
    }]
}
tab_res = opener.open(urllib.request.Request(
    f'{BASE_URL}/livewire/update',
    data=json.dumps(tab_payload).encode('utf-8'),
    headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf_token, 'User-Agent': 'Mozilla/5.0'}
))
tab_json = json.loads(tab_res.read().decode('utf-8'))
html_out = tab_json['components'][0]['effects']['html']

with open('scratch/kap_live_tab.html', 'w', encoding='utf-8') as f:
    f.write(html_out)

print("Saved scratch/kap_live_tab.html, length:", len(html_out))

# Look for Indeks per Konstruk Perilaku
idx = html_out.find('Indeks per Konstruk Perilaku')
print("Found Indeks at:", idx)
if idx != -1:
    print(html_out[idx:idx+1500])

