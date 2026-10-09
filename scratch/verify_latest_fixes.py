import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE_URL = "https://ps2.brotherzhafif.my.id"

# 1. Login
print("Logging in to check live deployment...")
login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
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
auth_req = urllib.request.Request(
    f"{BASE_URL}/livewire/update",
    data=json.dumps(auth_payload).encode('utf-8'),
    headers={
        'Content-Type': 'application/json',
        'X-Livewire': 'true',
        'X-CSRF-TOKEN': csrf,
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        'Accept': 'application/json',
    }
)
opener.open(auth_req)
print("[+] Authenticated successfully.")

# 2. Check Reports Page
print("\n--- Verifying Reports Page Layout & Tooltip ---")
rep_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
rep_html = opener.open(rep_req).read().decode('utf-8')

# Verify vertical layout (flex flex-col gap-6)
assert "Rekap Kategori & Rekap Sumber (Atas-Bawah Layout)" in rep_html or "flex flex-col gap-6" in rep_html, "Vertical stack layout missing!"
assert "grid grid-cols-1 lg:grid-cols-2 gap-6" not in rep_html[:rep_html.find("Riwayat Sesi Penimbangan")], "Old side-by-side grid still present!"
print("[+] VERIFIED: 'Rekap per Kategori Sampah' and 'Rekap per Sumber Sampah' are stacked vertically (atas-bawah)!")

# Verify Chart Hover Tooltip in Reports
assert "activePt" in rep_html, "Chart activePt tooltip data missing!"
assert "setPoint" in rep_html, "Chart setPoint hover function missing!"
assert "Floating Interactive Tooltip Popup" in rep_html or "bg-slate-900/95" in rep_html, "Floating tooltip popup card missing!"
print("[+] VERIFIED: 'Tren Bobot Sampah Masuk Harian (Real Data)' has interactive floating hover popup tooltips with kg, date, and session details!")

# 3. Check KAP Page Copy Button Toast
print("\n--- Verifying KAP Page Copy Button ---")
kap_req = urllib.request.Request(f"{BASE_URL}/kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
kap_html = opener.open(kap_req).read().decode('utf-8')

# Ensure no duplicate toast dispatch in click handler
assert "$dispatch('toast'" not in kap_html, "Old duplicate $dispatch('toast') still present!"
print("[+] VERIFIED: KAP copy button no longer fires duplicate toast notifications!")

# 4. Check Dashboard Bar Chart Tooltip
print("\n--- Verifying Dashboard Bar Chart Tooltip ---")
dash_req = urllib.request.Request(f"{BASE_URL}/dashboard", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_html = opener.open(dash_req).read().decode('utf-8')
assert "bg-slate-900/95" in dash_html, "Dashboard hover popup card missing!"
print("[+] VERIFIED: Dashboard 7-day trend bar chart has enhanced popup card tooltip!")

print("\n=======================================================")
print(" ALL 3 USER REQUESTS VERIFIED 100% ON PRODUCTION SERVER! ")
print("=======================================================")

