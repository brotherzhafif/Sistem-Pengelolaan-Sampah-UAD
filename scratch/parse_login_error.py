with open('scratch/login_error.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
m = re.search(r'<title>(.*?)</title>', text)
if m:
    print('TITLE:', m.group(1))

# Search for window.data or similar ignition payload
m2 = re.search(r'window\.data\s*=\s*(\{.*?\});\s*</script>', text, re.DOTALL)
if m2:
    import json
    data = json.loads(m2.group(1))
    print('Exception class:', data.get('report', {}).get('message'))
else:
    for m in re.finditer(r'"message":\s*"([^"]+)"', text):
        print('Message match:', m.group(1)[:150])
    for m in re.finditer(r'"exception_message":\s*"([^"]+)"', text):
        print('Exception message:', m.group(1)[:150])

