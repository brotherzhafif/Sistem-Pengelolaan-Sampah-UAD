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

# Now get reports page snapshot
rep_req = urllib.request.Request(f'{BASE_URL}/reports', headers={'User-Agent': 'Mozilla/5.0'})
rep_html = opener.open(rep_req).read().decode('utf-8')
rep_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', rep_html).group(1)
rep_snap_match = re.search(r'wire:snapshot="([^"]+)"', rep_html)
rep_snap = json.loads(html.unescape(rep_snap_match.group(1)))

# Call setTab('kap')
tab_payload = {
    '_token': rep_csrf,
    'components': [{
        'snapshot': json.dumps(rep_snap),
        'updates': {},
        'calls': [{'path': '', 'method': 'setTab', 'params': ['kap']}]
    }]
}
try:
    tab_res = opener.open(urllib.request.Request(
        f'{BASE_URL}/livewire/update',
        data=json.dumps(tab_payload).encode('utf-8'),
        headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': rep_csrf, 'User-Agent': 'Mozilla/5.0'}
    ))
    tab_json = json.loads(tab_res.read().decode('utf-8'))
    rendered_html = tab_json['components'][0]['effects']['html']
except urllib.error.HTTPError as e:
    err_body = e.read().decode('utf-8')
    with open('scratch/kap_error.html', 'w', encoding='utf-8') as f:
        f.write(err_body)
    print('HTTP 500 Error!')
    title = re.search(r'<title>(.*?)</title>', err_body)
    if title: print('Title:', title.group(1))
    exc = re.search(r'class="text-xl font-bold[^>]*>(.*?)</h2>', err_body)
    if exc: print('Exc:', exc.group(1))
    for m in re.finditer(r'<span class="text-red-500[^>]*>(.*?)</span>', err_body):
        print('Red span:', m.group(1))
    for m in re.finditer(r'<h1[^>]*>(.*?)</h1>', err_body):
        print('H1:', m.group(1))
    exit(0)

with open('scratch/kap_tab_rendered.html', 'w', encoding='utf-8') as f:
    f.write(rendered_html)

idx = rendered_html.find('Indeks per Konstruk Perilaku')
print('Found Indeks at:', idx)
if idx != -1:
    print(rendered_html[idx:idx+2500])
