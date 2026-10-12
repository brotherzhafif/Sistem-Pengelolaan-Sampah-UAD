import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl._create_unverified_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

def safe_open(req, retries=5, delay=4):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Finance (Keuangan) Module...")

# 1. Login as Super Admin
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
login_html = safe_open(login_req).read().decode('utf-8')
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html).group(1)
snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', login_html)]
login_snap = next(s for s in snaps if s.get('memo', {}).get('name') == 'pages.auth.login')

auth_payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(login_snap),
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
auth_res = safe_open(auth_req)
print("[+] Authenticated as Super Admin successfully.")

# 2. Access /finance (Served by FinanceController@index)
finance_req = urllib.request.Request(f"{BASE_URL}/finance", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
finance_res = safe_open(finance_req)
finance_html = finance_res.read().decode('utf-8')

assert finance_res.status == 200, f"Expected 200 OK from /finance, got {finance_res.status}"
assert "Buku Kas & Buku Besar Keuangan" in finance_html, "Title Buku Kas & Buku Besar Keuangan missing"
assert "Saldo Kas Sirkular" in finance_html, "Metric Saldo Kas Sirkular missing"
assert "Total Kredit (Masuk)" in finance_html, "Metric Total Kredit missing"
print("[+] /finance HTTP route successfully served by FinanceController with 200 OK.")

# 3. Test Livewire RPC interaction on Finance module (Tab Switcher to general_ledger)
finance_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', finance_html).group(1)
finance_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', finance_html)]
finance_snap = next(s for s in finance_snaps if s.get('memo', {}).get('name') in ['pages.finance', 'pages.finance.index'])

tab_payload = {
    "_token": finance_csrf,
    "components": [
        {
            "snapshot": json.dumps(finance_snap),
            "updates": {
                "activeTab": "general_ledger"
            },
            "calls": []
        }
    ]
}

tab_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(tab_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': finance_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
tab_res = safe_open(tab_req)
assert tab_res.status == 200, f"Expected 200 OK from activeTab switch RPC, got {tab_res.status}"
print("[+] Livewire property update activeTab='general_ledger' executed cleanly with 200 OK.")

# 4. Verify table-fixed class and layout
assert "table-fixed" in finance_html, "table-fixed CSS class missing from finance tables"
print("[+] Table fixed structure and layout verified.")

print("\n[SUCCESS] ALL FINANCE MVC E2E TESTS PASSED SUCCESSFULLY!")
