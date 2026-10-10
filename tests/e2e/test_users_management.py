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

print(f"Connecting to {BASE_URL} for User Management testing...")

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

# 2. Access /users
users_req = urllib.request.Request(f"{BASE_URL}/users", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
users_page = opener.open(users_req).read().decode('utf-8')

assert "Manajemen Pengguna & Hak Akses" in users_page, "Header page title missing!"
assert "Tambah Pengguna Baru" in users_page, "Create user button missing!"
print("[2] /users page loaded successfully with 200 OK.")

# Find pages.users.index snapshot
all_snaps = re.findall(r'wire:snapshot="([^"]+)"', users_page)
snapshot = None
for s_raw in all_snaps:
    s_obj = json.loads(html.unescape(s_raw))
    if s_obj.get('memo', {}).get('name') == 'pages.users.index':
        snapshot = s_obj
        break

assert snapshot is not None, "pages.users.index snapshot not found!"
csrf_token = re.search(r'<meta name="csrf-token" content="([^"]+)"', users_page).group(1)

# Helper for Livewire calls
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

# 3. Test Create User
test_email = f"test.operator.{json.loads(json.dumps(snapshot))['memo']['id'][:5]}@uad.ac.id"
print(f"[3] Creating new user: {test_email}...")

snapshot, effects = call_livewire(
    updates={
        "name": "Operator Uji Otomatis",
        "email": test_email,
        "password": "password123",
        "selectedRole": "operator_timbangan",
        "selectedCampusId": 1
    },
    calls=[
        {"path": "", "method": "saveUser", "params": []}
    ]
)

dispatches = effects.get('dispatches', [])
has_toast = any(d.get('name') == 'toast' for d in dispatches)
print(f"Created user toast dispatched: {has_toast}")
assert has_toast, "Toast notification missing after creating user!"

# 4. Search and verify created user
print(f"[4] Searching user by email {test_email}...")
snapshot, effects = call_livewire(
    updates={
        "search": test_email
    },
    calls=[]
)
search_html = effects.get('html', '')
assert "Operator Uji Otomatis" in search_html, "Newly created user missing from search results!"
print("[4] User search and display verified!")

# Extract user ID to delete
id_match = re.search(r'openEditModal\((\d+)\)', search_html)
assert id_match, "Edit user ID not found in table!"
created_user_id = int(id_match.group(1))

# 5. Test Reset Password Default
print(f"[5] Testing Reset Password for user #{created_user_id}...")
snapshot, effects = call_livewire(
    updates={},
    calls=[
        {"path": "", "method": "resetPasswordDefault", "params": [created_user_id]}
    ]
)
has_toast = any(d.get('name') == 'toast' for d in effects.get('dispatches', []))
assert has_toast, "Toast notification missing on reset password!"
print("[5] Password reset verified!")

# 6. Test Delete User
print(f"[6] Deleting test user #{created_user_id}...")
snapshot, effects = call_livewire(
    updates={
        "deleteUserId": created_user_id
    },
    calls=[
        {"path": "", "method": "deleteUser", "params": []}
    ]
)
has_toast = any(d.get('name') == 'toast' for d in effects.get('dispatches', []))
assert has_toast, "Toast notification missing on delete user!"
print("[6] User deleted cleanly.")

print("\n[SUCCESS] ALL USER MANAGEMENT (M9) WORKFLOW TESTS PASSED 100% GREEN!")

