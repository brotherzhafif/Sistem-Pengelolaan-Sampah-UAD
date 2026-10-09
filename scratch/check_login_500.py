import urllib.request
import ssl
import re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

req = urllib.request.Request(
    'https://ps2.brotherzhafif.my.id/login',
    headers={'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'}
)

try:
    resp = urllib.request.urlopen(req, context=ctx)
    print("Login status:", resp.status)
except urllib.error.HTTPError as e:
    err = e.read().decode('utf-8')
    with open('scratch/login_500.html', 'w', encoding='utf-8') as f:
        f.write(err)
    title = re.search(r'<title>(.*?)</title>', err)
    if title:
        print("Title:", title.group(1))
    # Look for exception or error
    for m in re.finditer(r'<span class="text-red-500[^>]*>(.*?)</span>', err):
        print("Red span:", m.group(1))
    for m in re.finditer(r'class="text-xl font-bold[^>]*>(.*?)</h2>', err):
        print("Error header:", m.group(1))
    for m in re.finditer(r'<h1[^>]*>(.*?)</h1>', err):
        print("H1:", m.group(1))

