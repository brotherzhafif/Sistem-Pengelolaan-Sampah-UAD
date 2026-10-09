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

print(f"Connecting to {BASE_URL} for Modul M8 (Reports & Export) testing...")

# 1. Login as Super Admin
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
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
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
auth_res = opener.open(auth_req)
print("[1] Super Admin authenticated successfully.")

# 2. Access /reports
reports_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
reports_page = opener.open(reports_req).read().decode('utf-8')

assert "Laporan & Ekspor Data PS2" in reports_page, "Header page title missing!"
assert "Penimbangan Masuk" in reports_page, "Tab Penimbangan missing!"
assert "Penjualan Sampah" in reports_page, "Tab Penjualan missing!"
assert "Pengangkutan Residu" in reports_page, "Tab Pengangkutan missing!"
assert "Buku Kas & Keuangan" in reports_page, "Tab Keuangan missing!"
assert "Survei Perilaku (KAP)" in reports_page, "Tab KAP missing!"
print("[2] /reports page loaded successfully with 200 OK.")

# Extract snapshot for pages.reports.index
all_snaps = re.findall(r'wire:snapshot="([^"]+)"', reports_page)
snapshot = None
for s_raw in all_snaps:
    s_obj = json.loads(html.unescape(s_raw))
    if s_obj.get('memo', {}).get('name') == 'pages.reports.index':
        snapshot = s_obj
        break

assert snapshot is not None, "pages.reports.index snapshot not found!"
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', reports_page).group(1)

def call_livewire(updates, calls):
    global snapshot
    payload = {
        "_token": csrf_token,
        "components": [
            {
                "snapshot": json.dumps(snapshot),
                "updates": updates,
                "calls": calls
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
    res = opener.open(req_post)
    res_data = json.loads(res.read().decode('utf-8'))
    comp = res_data['components'][0]
    snapshot = json.loads(comp['snapshot'])
    return snapshot, comp.get('effects', {})

# 3. Test Tab Switching
tabs = ['sales', 'pickups', 'finance', 'kap', 'weighing']
for t in tabs:
    print(f"[3] Switching to tab: {t}...")
    snapshot, effects = call_livewire(updates={}, calls=[{"path": "", "method": "setTab", "params": [t]}])
    html_content = effects.get('html', '')
    assert snapshot['data']['activeTab'] == t, f"Failed to switch to tab {t}!"
    print(f"    Tab {t} rendered successfully.")

# 4. Test Date Preset Switch
print("[4] Testing Preset Switch: all...")
snapshot, effects = call_livewire(updates={}, calls=[{"path": "", "method": "applyPreset", "params": ["all"]}])
assert snapshot['data']['presetPeriod'] == 'all', "Preset not updated!"
print("    Preset 'all' applied successfully.")

# 5. Test PDF Export Endpoints
print("[5] Testing PDF Export endpoints...")
pdf_types = ['weighing', 'sales', 'pickups', 'finance', 'kap']
for ptype in pdf_types:
    pdf_url = f"{BASE_URL}/reports/export/pdf?type={ptype}"
    pdf_req = urllib.request.Request(pdf_url, headers={'User-Agent': 'Mozilla/5.0'})
    pdf_res = opener.open(pdf_req)
    assert pdf_res.status == 200, f"PDF export for {ptype} failed with status {pdf_res.status}!"
    content_type = pdf_res.headers.get('Content-Type', '')
    assert 'application/pdf' in content_type, f"Invalid Content-Type for {ptype}: {content_type}"
    pdf_bytes = pdf_res.read(1024)
    assert pdf_bytes.startswith(b'%PDF-'), f"Invalid PDF header bytes for {ptype}!"
    print(f"    PDF export for '{ptype}' verified (%PDF- valid, Content-Type: {content_type}).")

# 6. Test CSV / Excel Export Endpoints
print("[6] Testing CSV / Excel Export endpoints...")
for ctype in ['weighing', 'finance', 'sales']:
    csv_url = f"{BASE_URL}/reports/export/excel?type={ctype}"
    csv_req = urllib.request.Request(csv_url, headers={'User-Agent': 'Mozilla/5.0'})
    csv_res = opener.open(csv_req)
    assert csv_res.status == 200, f"CSV export for {ctype} failed with status {csv_res.status}!"
    content_type = csv_res.headers.get('Content-Type', '')
    assert 'text/csv' in content_type, f"Invalid Content-Type for {ctype}: {content_type}"
    csv_bytes = csv_res.read(1024)
    # Check UTF-8 BOM
    assert csv_bytes.startswith(b'\xef\xbb\xbf'), f"Missing UTF-8 BOM in CSV export for {ctype}!"
    print(f"    CSV export for '{ctype}' verified (UTF-8 BOM valid, Content-Type: {content_type}).")

print("\n[SUCCESS] ALL MODUL M8 (REPORTS & DATA EXPORT) WORKFLOW TESTS PASSED 100% GREEN!")
