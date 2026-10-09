with open('scratch/kap_report_rendered.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
active_tabs = re.findall(r'bg-emerald-600 text-white[^>]*>.*?<span>(.*?)</span>', text, re.DOTALL)
print("Active Tab button:", [t.strip() for t in active_tabs])

# Check what H3 tags exist in text
h3s = re.findall(r'<h3[^>]*>(.*?)</h3>', text, re.DOTALL)
print("H3 titles found:")
for h in h3s:
    print(" -", h.strip())

