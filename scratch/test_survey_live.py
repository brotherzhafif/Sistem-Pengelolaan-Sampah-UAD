import urllib.request
import ssl
import re
import json
import html

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

url = 'https://ps2.brotherzhafif.my.id/survei-kap'
req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
with urllib.request.urlopen(req, context=ctx, timeout=15) as resp:
    html_content = resp.read().decode('utf-8')

print('--- STEPPER CHECKS ---')
for step in range(1, 6):
    pattern = rf'goToStep\({step}\)'
    found = bool(re.search(pattern, html_content))
    print(f'Step {step} clickable (goToStep({step})): {found}')

print('--- COLOR PALETTE CHECKS ---')
has_sky = 'sky-600' in html_content or 'sky-50' in html_content
has_indigo = 'indigo-600' in html_content or 'indigo-50' in html_content
has_emerald = 'emerald-600' in html_content
print(f'Has sky (blue) left? {has_sky}')
print(f'Has indigo left? {has_indigo}')
print(f'Has emerald-600? {has_emerald}')

matches = list(re.finditer(r'wire:snapshot="([^"]+)"', html_content))
print(f'Livewire snapshots found: {len(matches)}')
if matches:
    for idx, m in enumerate(matches):
        snap_str = html.unescape(m.group(1))
        data = json.loads(snap_str)
        name = data.get('memo', {}).get('name', '')
        print(f'Snapshot {idx}: {name}')
        if 'survey' in name or 'pages.' in name:
            print('  Current step in state:', data.get('data', {}).get('currentStep'))
            print('  Knowledge default state:', data.get('data', {}).get('knowledge'))

