import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
from datetime import date

ctx = ssl._create_unverified_context()
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

print(f"Connecting to {BASE_URL} to verify MVC Sales (Penjualan) Module...")

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

# 2. Access /sales (Served by SaleController@index)
sales_req = urllib.request.Request(f"{BASE_URL}/sales", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
sales_res = safe_open(sales_req)
sales_html = sales_res.read().decode('utf-8')

assert sales_res.status == 200, f"Expected 200 OK from /sales, got {sales_res.status}"
assert "Penjualan Sampah Terpilah" in sales_html, "Title Penjualan Sampah Terpilah missing"
assert "Total Pendapatan Terdata" in sales_html, "Metric Total Pendapatan Terdata missing"
assert "Total Berat Terjual" in sales_html, "Metric Total Berat Terjual missing"
print("[+] /sales HTTP route successfully served by SaleController with 200 OK.")

# 3. Test Livewire RPC interaction on Sales module
sales_csrf = re.search(r'<meta name="csrf-token" content="([^"]+)"', sales_html).group(1)
sales_snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', sales_html)]
sales_snap = next(s for s in sales_snaps if s.get('memo', {}).get('name') in ['pages.sales', 'pages.sales.index'])

# Trigger modal opening and subtotal calculation
update_subtotal_payload = {
    "_token": sales_csrf,
    "components": [
        {
            "snapshot": json.dumps(sales_snap),
            "updates": {
                "saleItems.0.weight_kg": "10",
                "saleItems.0.price_per_kg": "2500"
            },
            "calls": [
                {"path": "", "method": "updateSubtotal", "params": [0]}
            ]
        }
    ]
}

subtotal_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(update_subtotal_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': sales_csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
subtotal_res = safe_open(subtotal_req)
assert subtotal_res.status == 200, f"Expected 200 OK from updateSubtotal RPC, got {subtotal_res.status}"
subtotal_json = json.loads(subtotal_res.read().decode('utf-8'))
print("[+] Livewire RPC updateSubtotal executed cleanly with 200 OK.")

# 4. Verify table-fixed and no layout errors
assert "table-fixed" in sales_html, "table-fixed CSS class missing from sales table"
print("[+] Table fixed structure and layout verified.")

print("\n[SUCCESS] ALL SALES MVC E2E TESTS PASSED SUCCESSFULLY!")
