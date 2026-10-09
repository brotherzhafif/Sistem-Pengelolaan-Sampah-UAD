import urllib.request
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE
req = urllib.request.Request('https://ps2.brotherzhafif.my.id/login', headers={'User-Agent': 'Mozilla/5.0'})
try:
    urllib.request.urlopen(req, context=ctx)
    print("Status 200 OK")
except urllib.error.HTTPError as e:
    content = e.read().decode('utf-8', errors='ignore')
    print("Status:", e.code)
    # Write to error.html to inspect
    with open("scratch/error.html", "w", encoding="utf-8") as f:
        f.write(content)
    
    # Try finding exception message
    msgs = re.findall(r'<span class="text-red-500">([^<]+)</span>', content)
    print("Red spans:", msgs)
    titles = re.findall(r'<title>([^<]+)</title>', content)
    print("Titles:", titles)
    h1s = re.findall(r'<h1[^>]*>([^<]+)</h1>', content)
    print("H1s:", h1s)
    # Search for Exception trace or class
    lines = [line.strip() for line in content.split('\n') if 'exception' in line.lower() or 'error' in line.lower() or 'line' in line.lower()]
    for l in lines[:15]:
        print("  >", l[:120])

