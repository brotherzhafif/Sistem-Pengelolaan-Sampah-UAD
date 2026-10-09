import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
BASE_URL = 'https://ps2.brotherzhafif.my.id'
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

login_html = opener.open(urllib.request.Request(f'{BASE_URL}/login', headers={'User-Agent': 'Mozilla/5.0'})).read().decode('utf-8')
csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snap = json.loads(html.unescape(re.search(r'wire:snapshot="([^"]+)"', login_html).group(1)))

auth_payload = {
    '_token': csrf,
    'components': [{
        'snapshot': json.dumps(snap),
        'updates': {'form.email': 'superadmin@uad.ac.id', 'form.password': 'password123'},
        'calls': [{'path': '', 'method': 'login', 'params': []}]
    }]
}
opener.open(urllib.request.Request(
    f'{BASE_URL}/livewire/update',
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf,
        'User-Agent': 'Mozilla/5.0'
    }
))

rep_html = opener.open(urllib.request.Request(f'{BASE_URL}/reports', headers={'User-Agent': 'Mozilla/5.0'})).read().decode('utf-8')

# Verify top sources count
m_sources = re.findall(r'Top (\d+) Titik Sumber', rep_html)
print('Source recap count match:', m_sources)
assert len(m_sources) > 0 and int(m_sources[0]) <= 6, f'Expected <= 6 sources, found: {m_sources}'

# Verify table columns
assert 'Avg/Hari' in rep_html, 'Avg/Hari column missing in category recap!'
assert 'Total m³' in rep_html, 'Total m3 column missing in source recap!'
assert 'Riwayat Sesi Penimbangan' in rep_html, 'Table Riwayat Sesi Penimbangan missing!'
assert 'Jenis Tervalidasi' in rep_html, 'Badge Jenis Tervalidasi missing!'
assert 'wire:click="viewSession(' in rep_html, 'viewSession button missing!'

print('[+] ALL SOURCE RECAP AND WEIGHING TABLE VERIFICATIONS PASSED 100%!')

