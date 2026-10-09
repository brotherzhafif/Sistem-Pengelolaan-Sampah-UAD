with open('scratch/kap_error.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = re.findall(r'Method \[.*?\] not found.*', text)
for m in matches:
    print(m[:200])

for line in text.splitlines():
    if 'Method' in line and 'not found' in line:
        print(line.strip())

