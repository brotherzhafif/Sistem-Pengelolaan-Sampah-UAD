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

# Login
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
    headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf, 'User-Agent': 'Mozilla/5.0'}
))

# Open /reports
rep_html = opener.open(urllib.request.Request(f'{BASE_URL}/reports', headers={'User-Agent': 'Mozilla/5.0'})).read().decode('utf-8')
csrf_rep = re.search(r'<meta name="csrf-token" content="([^"]+)"', rep_html).group(1)

all_snaps = re.findall(r'wire:snapshot="([^"]+)"', rep_html)
rep_snap = None
for s_raw in all_snaps:
    s_obj = json.loads(html.unescape(s_raw))
    if s_obj.get('memo', {}).get('name') == 'pages.reports.index':
        rep_snap = s_obj
        break

assert rep_snap is not None

def call_lw(snapshot, updates, calls):
    payload = {
        '_token': csrf_rep,
        'components': [{
            'snapshot': json.dumps(snapshot),
            'updates': updates,
            'calls': calls
        }]
    }
    req = urllib.request.Request(
        f'{BASE_URL}/livewire/update',
        data=json.dumps(payload).encode('utf-8'),
        headers={'Content-Type': 'application/json', 'X-Livewire': 'true', 'X-CSRF-TOKEN': csrf_rep, 'User-Agent': 'Mozilla/5.0'}
    )
    res = opener.open(req)
    res_data = json.loads(res.read().decode('utf-8'))
    comp = res_data['components'][0]
    return json.loads(comp['snapshot']), comp.get('effects', {})

# 1. Test Weighing Modal
weigh_id = int(re.search(r'wire:click="viewSession\((\d+)\)"', rep_html).group(1))
snap_weigh, fx = call_lw(rep_snap, {}, [{'path': '', 'method': 'viewSession', 'params': [weigh_id]}])
assert 'Rincian Komposisi Penimbangan Sampah' in fx.get('html', ''), 'Weighing modal not rendered!'
print(f'[1] Weighing modal verified with Session #{weigh_id}!')

# 2. Test Sales Tab & Modal
snap_sales, fx = call_lw(snap_weigh, {}, [{'path': '', 'method': 'setTab', 'params': ['sales']}])
sale_id = int(re.search(r'wire:click="viewSale\((\d+)\)"', fx.get('html', '')).group(1))
snap_sale_modal, fx = call_lw(snap_sales, {}, [{'path': '', 'method': 'viewSale', 'params': [sale_id]}])
assert 'Rincian Transaksi Penjualan' in fx.get('html', ''), 'Sales modal not rendered!'
print(f'[2] Sales modal verified with Sale #{sale_id}!')

# 3. Test Pickups Tab & Modal
snap_pickup, fx = call_lw(snap_sale_modal, {}, [{'path': '', 'method': 'setTab', 'params': ['pickups']}])
pickup_id = int(re.search(r'wire:click="viewPickup\((\d+)\)"', fx.get('html', '')).group(1))
snap_pickup_modal, fx = call_lw(snap_pickup, {}, [{'path': '', 'method': 'viewPickup', 'params': [pickup_id]}])
assert 'Rincian Pengangkutan Residu' in fx.get('html', ''), 'Pickup modal not rendered!'
print(f'[3] Pickups modal verified with Pickup #{pickup_id}!')

# 4. Test Finance Tab & Modal
snap_fin, fx = call_lw(snap_pickup_modal, {}, [{'path': '', 'method': 'setTab', 'params': ['finance']}])
fin_id = int(re.search(r'wire:click="viewKeuangan\((\d+)\)"', fx.get('html', '')).group(1))
snap_fin_modal, fx = call_lw(snap_fin, {}, [{'path': '', 'method': 'viewKeuangan', 'params': [fin_id]}])
assert 'Rincian Transaksi Jurnal Buku Kas' in fx.get('html', ''), 'Finance modal not rendered!'
print(f'[4] Finance modal verified with Keuangan #{fin_id}!')

# 5. Test KAP Tab & Modal
snap_kap, fx = call_lw(snap_fin_modal, {}, [{'path': '', 'method': 'setTab', 'params': ['kap']}])
survey_id = int(re.search(r'wire:click="viewSurvey\((\d+)\)"', fx.get('html', '')).group(1))
snap_kap_modal, fx = call_lw(snap_kap, {}, [{'path': '', 'method': 'viewSurvey', 'params': [survey_id]}])
assert 'Rincian Evaluasi KAP Responden' in fx.get('html', ''), 'KAP modal not rendered!'
print(f'[5] KAP modal verified with Survey #{survey_id}!')

print('===========================================================')
print(' ALL 5 DETAIL MODALS ACROSS ALL TABS VERIFIED 100% OPERATIONAL! ')
print('===========================================================')
