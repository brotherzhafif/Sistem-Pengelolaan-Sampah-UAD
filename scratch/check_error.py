import urllib.request
import ssl
import re

ctx = ssl._create_unverified_context()
req = urllib.request.Request('https://ps2.brotherzhafif.my.id/survei-kap', headers={'User-Agent': 'Mozilla/5.0'})
try:
    urllib.request.urlopen(req, context=ctx)
    print("SUCCESS 200 OK!")
except urllib.error.HTTPError as e:
    body = e.read().decode('utf-8', errors='ignore')
    # Save to file to inspect
    with open('scratch/error.html', 'w', encoding='utf-8') as f:
        f.write(body)
    
    # Extract plain text error message
    lines = []
    # Laravel Ignition error often puts exception message in specific meta/title or json
    for line in body.splitlines():
        if any(keyword in line for keyword in ['"message":', 'class="exception_title"', 'class="exception_message"', 'ParseError', 'FatalError', 'ErrorException', 'Livewire', 'Volt']):
            lines.append(line.strip())
    print("Status:", e.code)
    for l in lines[:10]:
        print("MATCH:", l[:200])

