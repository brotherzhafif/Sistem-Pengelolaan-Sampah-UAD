with open('scratch/kap_error.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
print('Title:', re.findall(r'<title>(.*?)</title>', text))
print('H1:', re.findall(r'<h1[^>]*>(.*?)</h1>', text, re.DOTALL))
print('H2:', re.findall(r'<h2[^>]*>(.*?)</h2>', text, re.DOTALL))

# Find exception class or details
for m in re.finditer(r'<span class="break-words[^"]*">(.*?)</span>', text, re.DOTALL):
    print("Span break-words:", m.group(1).strip())

for m in re.finditer(r'<div class="text-[^"]*">(.*?)</div>', text, re.DOTALL):
    content = m.group(1).strip()
    if len(content) < 200 and ('Method' in content or 'Exception' in content or 'error' in content or 'call' in content):
        print("Div:", content)

