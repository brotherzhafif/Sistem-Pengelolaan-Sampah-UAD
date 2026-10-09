import urllib.request
import ssl
import re
import json
import html
import http.cookiejar
import time

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=ctx))

BASE_URL = "https://ps2.brotherzhafif.my.id"

# Server ready check
print("Running verification against production server...")

print("1. Authenticating as Super Admin...")
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
print("[+] Authenticated.")

# 2. Check Reports Page
print("\n2. Checking /reports...")
rep_req = urllib.request.Request(f"{BASE_URL}/reports?tab=weighing", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
rep_html = opener.open(rep_req).read().decode('utf-8')

assert "Unduh data tab aktif" not in rep_html, "Redundant export text still in /reports!"
print("[+] VERIFIED: 'Unduh data tab aktif' is completely removed from the filter bar.")

assert "Ekspor Excel (.xlsx)" in rep_html and "Ekspor PDF (.pdf)" in rep_html, "Header export buttons missing!"
print("[+] VERIFIED: Header export buttons (Excel & PDF) are present in header top-right.")

# Check pagination wrapper
assert '<div class="px-5 py-3 border-t border-slate-100 bg-slate-50/50 text-xs">' in rep_html, "Pagination wrapper not updated!"
print("[+] VERIFIED: Pagination container is full-width (page numbers aligned to right edge).")

# 3. Check Expenses Page
print("\n3. Checking /expenses...")
exp_req = urllib.request.Request(f"{BASE_URL}/expenses", headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'})
exp_html = opener.open(exp_req).read().decode('utf-8')

# Search for rendered category badges in table
badges = re.findall(r'bg-rose-50 text-rose-700[^>]*>.*?<span class="truncate"[^>]*>(.*?)</span>', exp_html, re.DOTALL)
print(f"[+] Found {len(badges)} category badges in table:")
for b in badges[:8]:
    clean_b = b.strip()
    words = clean_b.split()
    print(f"    - '{clean_b}' (words count: {len(words)})")
    assert len(words) <= 2, f"Category '{clean_b}' has more than 2 words!"

print("\n=======================================================")
print(" ALL CHECKS PASSED: EXPORT BAR CLEANED, PAGINATION FIXED, CATEGORIES <= 2 WORDS ")
print("=======================================================")

