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

login_req = urllib.request.Request(f"{BASE_URL}/login", headers={'User-Agent': 'Mozilla/5.0'})
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
        'User-Agent': 'Mozilla/5.0',
        'Accept': 'application/json',
    }
)
opener.open(auth_req)
print("Logged in!")

try:
    reports_req = urllib.request.Request(f"{BASE_URL}/reports", headers={'User-Agent': 'Mozilla/5.0'})
    res = opener.open(reports_req)
    print("Reports status:", res.status)
except urllib.error.HTTPError as e:
    content = e.read().decode('utf-8', errors='ignore')
    with open("scratch/reports_error2.html", "w", encoding="utf-8") as f:
        f.write(content)
    
    # Extract exception class and message using regex
    m_class = re.search(r'<h1[^>]*>([^<]+)</h1>', content)
    m_msg = re.search(r'<p class="text-xl[^>]*>([^<]+)</p>', content) or re.search(r'<h2[^>]*>([^<]+)</h2>', content)
    print("Exception Class:", m_class.group(1).strip() if m_class else "Unknown")
    print("Message:", m_msg.group(1).strip() if m_msg else "Unknown")
    
    # Find file and line
    files = re.findall(r'<span class="text-neutral-500">([^<]+)</span>', content)
    print("File references:", files[:5])
    
    # Search for lines containing .php
    php_lines = [l.strip() for l in content.split('\n') if '.php' in l and ('app/' in l or 'resources/' in l or 'routes/' in l)]
    for l in php_lines[:10]:
        print("  ->", l[:120])

