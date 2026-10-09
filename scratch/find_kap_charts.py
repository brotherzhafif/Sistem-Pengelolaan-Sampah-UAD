with open('scratch/kap_page.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
charts = re.findall(r'<svg[^>]*>.*?</svg>', text, re.DOTALL)
print("SVG charts in /kap:", len(charts))
for i, c in enumerate(charts):
    print(f"Chart {i}: length {len(c)}")
    print(c[:300])

# Check what other charts or canvas exist
canvases = re.findall(r'<canvas[^>]*>.*?</canvas>', text, re.DOTALL)
print("Canvas in /kap:", len(canvases))

