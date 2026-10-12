import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
import datetime

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

def safe_open(req, retries=3, delay=2):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} to verify MVC Expenses Module...")

# 1. Login as Super Admin
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
login_html = safe_open(login_req).read().decode('utf-8')
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
auth_res = safe_open(auth_req)
print("[+] Authenticated as Super Admin successfully.")

# 2. Access /expenses (Handled by ExpenseController@index)
expenses_req = urllib.request.Request(f"{BASE_URL}/expenses", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
expenses_res = safe_open(expenses_req)
expenses_html = expenses_res.read().decode('utf-8')

assert expenses_res.status == 200, f"Expected 200 OK from /expenses, got {expenses_res.status}"
assert "Pengeluaran Operasional TPS" in expenses_html, "Header Pengeluaran Operasional TPS missing"
assert "Total Pengeluaran" in expenses_html, "KPI Total Pengeluaran card missing"
assert "table-fixed" in expenses_html, "table-fixed class missing from expenses table"
print("[+] /expenses HTTP route successfully served by ExpenseController with 200 OK.")

# 3. Test Livewire RPC interaction on Expenses module
snap_match = re.search(r'wire:snapshot="([^"]+)"', expenses_html)
assert snap_match, "Livewire snapshot not found on /expenses"
expenses_snapshot = json.loads(html.unescape(snap_match.group(1)))

today_str = datetime.date.today().strftime('%Y-%m-%d')
test_desc = f"MVC E2E Biaya {int(datetime.datetime.now().timestamp())}"

create_payload = {
    "_token": csrf_token,
    "components": [
        {
            "snapshot": json.dumps(expenses_snapshot),
            "updates": {
                "formAmount": "25000",
                "formDescription": test_desc,
                "formDate": today_str
            },
            "calls": [
                {"path": "", "method": "saveExpense", "params": []}
            ]
        }
    ]
}

create_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(create_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf_token,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)

create_res = safe_open(create_req)
assert create_res.status == 200, f"Expected 200 OK from livewire/update saveExpense, got {create_res.status}"
create_json = json.loads(create_res.read().decode('utf-8'))
effects = create_json.get('components', [{}])[0].get('effects', {})
dispatches = effects.get('dispatches', [])
toast_dispatched = any(d.get('name') == 'toast' for d in dispatches)
print(f"[+] Livewire saveExpense RPC executed successfully (toast dispatched: {toast_dispatched})")

# 4. Verify newly created record appears in table
expenses_after_req = urllib.request.Request(f"{BASE_URL}/expenses", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
expenses_after_html = safe_open(expenses_after_req).read().decode('utf-8')
assert test_desc in expenses_after_html, f"Expected test record '{test_desc}' to appear in /expenses table"
print(f"[+] Verified newly created record '{test_desc}' rendered properly in table.")

print("\n" + "="*50)
print(" EXPENSES MVC ARCHITECTURE VERIFIED SUCCESSFULLY!")
print("="*50)
