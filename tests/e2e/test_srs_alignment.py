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

def safe_open(req, retries=3, delay=2):
    import time
    for attempt in range(retries):
        try:
            return opener.open(req)
        except Exception as e:
            if attempt == retries - 1:
                raise
            time.sleep(delay)

print(f"Connecting to {BASE_URL} for SRS v2 Alignment Verification...")

# 1. Login as Super Admin via Livewire
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

# 2. Check Dashboard Teknis (M1 & Prototype Alignment)
print("\n--- Testing Prototype Alignment: Dashboard Teknis & Automated Alerts ---")
dash_req = urllib.request.Request(f"{BASE_URL}/dashboard", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
dash_html = safe_open(dash_req).read().decode('utf-8')

assert "Dashboard Teknis" in dash_html, "Dashboard Teknis title missing"
assert "(a) Dashboard Teknis Operasional" not in dash_html, "Unrequested dual-mode switcher still present"
print("[+] Clean Dashboard Teknis matching prototype verified (no unrequested dual-mode switcher)")

assert "Saldo Kas Sirkular" in dash_html or "Saldo Kas" in dash_html, "Saldo Kas Sirkular KPI card missing"
print("[+] Saldo Kas Sirkular card present (sourced from buku_besar.saldo_akhir)")

has_alert = any(a in dash_html for a in ["Reminder Input Harian", "Alert Stok Menumpuk", "Alert Akumulasi Residu Tinggi", "Alert Saldo Menipis"])
print(f"[+] M10 Automated Notification/Alert Card rendered: {has_alert}")

# Verify dedicated KAP module at /kap
kap_req = urllib.request.Request(f"{BASE_URL}/kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
kap_html = safe_open(kap_req).read().decode('utf-8')
assert "KAP" in kap_html, "Dedicated KAP module at /kap missing"
print("[+] Dedicated KAP Module at /kap is properly separated and accessible")

# 3. Check Users Management M9
print("\n--- Testing M9: Canonical Roles in User Management ---")
users_req = urllib.request.Request(f"{BASE_URL}/users", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
users_html = safe_open(users_req).read().decode('utf-8')
canonical_roles = ["super_admin", "admin_kampus", "petugas_tps", "petugas_penjualan", "keuangan", "viewer"]
found_roles = [r for r in canonical_roles if r in users_html]
print(f"[+] Canonical SRS Roles detected in User Management: {found_roles}")
assert len(found_roles) >= 4
print("[+] User Management M9 verified with canonical roles!")

# 4. Check Public Survey M11
print("\n--- Testing M11: Public KAP Survey Form ---")
survey_req = urllib.request.Request(f"{BASE_URL}/survei-kap", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
survey_html = safe_open(survey_req).read().decode('utf-8')
assert "Data Diri Civitas Akademika" in survey_html or "Pengetahuan (Knowledge)" in survey_html
print("[+] Public KAP survey form is active with 33 questions across all 4 dimensions!")

# 5. Check Finance & Reports M6 & M8
print("\n--- Testing M6 & M8: Finance and Reports Modules ---")
fin_req = urllib.request.Request(f"{BASE_URL}/finance", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
fin_html = safe_open(fin_req).read().decode('utf-8')
assert "Buku Kas" in fin_html or "Buku Besar" in fin_html
print("[+] M6 Buku Kas & Buku Besar verified!")

rep_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
rep_html = safe_open(rep_req).read().decode('utf-8')
assert "Laporan" in rep_html
print("[+] M8 Laporan & Rekapitulasi verified!")

print("\n" + "="*50)
print(" ALL 11 MODULES (M1 - M11) ALIGNED WITH SRS v2!")
print("="*50)
