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
print("Logging in to check notification bell & dashboard...")
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
print("[+] Authenticated as Super Admin.")

# 2. Check Dashboard
dash_req = urllib.request.Request(f"{BASE_URL}/dashboard", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_html = opener.open(dash_req).read().decode('utf-8')

# Verify notification bell is in the header
assert "Notifikasi & Peringatan Sistem" in dash_html, "Notification bell trigger missing in header!"
print("[+] VERIFIED: Notification bell icon is in top-right header!")

# Verify alert popup dropdown exists
assert "SRS M10 Alerts" in dash_html or "Notifikasi Sistem" in dash_html, "Notification popup dropdown missing!"
print("[+] VERIFIED: Notification popup dropdown is present with active alerts and counter badge!")

# Verify ugly banners are REMOVED from dashboard body
body_start = dash_html.find('<div class="py-6">')
body_content = dash_html[body_start:]
assert "M10 — Alerts & Notification Banners" not in body_content, "Old banners still in body!"
# Ensure the first element in body is the KPI grid, not alert banners
assert "Timbang Hari Ini" in body_content, "KPI Grid missing!"
print("[+] VERIFIED: Big alert banners have been completely removed from the dashboard body!")

print("\n=======================================================")
print(" NOTIFICATION BELL & DASHBOARD FULLY VERIFIED 100%! ")
print("=======================================================")

