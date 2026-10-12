import urllib.request
import ssl
import json
import re
import html
import http.cookiejar
import sys

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE = 'https://ps2.brotherzhafif.my.id'

print("=" * 60)
print("TESTING AUTH FLOW (LOGIN & REGISTER) ON LIVE VPS")
print("Target:", BASE)
print("=" * 60)

# 1. Test Login Page Load
print("\n[1] Fetching GET /login...")
req = urllib.request.Request(f'{BASE}/login', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
res = opener.open(req)
login_html = res.read().decode('utf-8')
print("  Status:", res.status)
assert res.status == 200, f"Expected 200 from /login, got {res.status}"

# Check Form attributes
form_match = re.search(r'<form\s+([^>]+)>', login_html)
if form_match:
    form_attrs = form_match.group(1)
    print("  Form attributes:", form_attrs[:120])
    assert 'method="POST"' in form_attrs or "method='POST'" in form_attrs, "Form must have method=POST"
    assert 'action="javascript:void(0);"' in form_attrs or 'action="#"' in form_attrs, "Form must have safe action"

csrf_m = re.search(r'<meta name="csrf-token" content="([^"]+)"', login_html)
csrf = csrf_m.group(1) if csrf_m else None
snaps = [json.loads(html.unescape(s)) for s in re.findall(r'wire:snapshot="([^"]+)"', login_html)]
login_snap = next((s for s in snaps if s.get('memo', {}).get('name') == 'pages.auth.login'), None)
assert csrf is not None, "CSRF token missing"
assert login_snap is not None, "Login Livewire component snapshot missing"
print("  CSRF token found: True")
print("  Livewire snapshot found: True")

# 2. Test Livewire Login Submit
print("\n[2] Performing Livewire Login RPC...")
auth_payload = {
    '_token': csrf,
    'components': [{
        'snapshot': json.dumps(login_snap),
        'updates': {'form.email': 'superadmin@uad.ac.id', 'form.password': 'password123'},
        'calls': [{'path': '', 'method': 'login', 'params': []}]
    }]
}
update_req = urllib.request.Request(
    f'{BASE}/livewire/update',
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
res = opener.open(update_req)
update_data = json.loads(res.read().decode('utf-8'))
print("  Status:", res.status)
assert res.status == 200, f"Livewire login returned {res.status}"
effects = update_data['components'][0].get('effects', {})
redirect_target = effects.get('redirect', '')
print("  Redirect target:", redirect_target)
assert redirect_target == '/dashboard', f"Expected redirect to /dashboard, got {redirect_target}"

# 3. Test Access Protected Route (Dashboard)
print("\n[3] Accessing /dashboard after login...")
dash_req = urllib.request.Request(f'{BASE}/dashboard', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_res = opener.open(dash_req)
dash_html = dash_res.read().decode('utf-8')
print("  Status:", dash_res.status)
assert dash_res.status == 200, f"Expected 200 from /dashboard, got {dash_res.status}"
assert "Dashboard Teknis" in dash_html or "Saldo Kas" in dash_html, "Dashboard content missing"
print("  [+] Authenticated session verified on /dashboard!")

# 4. Test Register Page
print("\n[4] Fetching GET /register...")
reg_req = urllib.request.Request(f'{BASE}/register', headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
reg_res = opener.open(reg_req)
reg_html = reg_res.read().decode('utf-8')
print("  Status:", reg_res.status)
assert reg_res.status == 200, f"Expected 200 from /register, got {reg_res.status}"
reg_form_m = re.search(r'<form\s+([^>]+)>', reg_html)
if reg_form_m:
    reg_attrs = reg_form_m.group(1)
    print("  Register Form attributes:", reg_attrs[:120])
    assert 'method="POST"' in reg_attrs or "method='POST'" in reg_attrs, "Register form must have method=POST"
    assert 'action="javascript:void(0);"' in reg_attrs or 'action="#"' in reg_attrs, "Register form must have safe action"
print("  [+] Register page verified!")

print("\n" + "=" * 60)
print("ALL AUTH FLOW & SECURITY CHECKS PASSED!")
print("=" * 60)
sys.exit(0)
