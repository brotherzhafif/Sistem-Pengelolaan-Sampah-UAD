import urllib.request, ssl, re

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

headers = {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Accept': '*/*',
}

url = 'https://ps2.brotherzhafif.my.id/survei-kap'
req = urllib.request.Request(url, headers=headers)
content = urllib.request.urlopen(req, context=ctx).read().decode('utf-8')
css_match = re.search(r'href="([^"]+app[^"]*\.css)"', content)
if css_match:
    css_url = css_match.group(1)
    print('CSS URL:', css_url)
    req_css = urllib.request.Request(css_url, headers=headers)
    css_content = urllib.request.urlopen(req_css, context=ctx).read().decode('utf-8')
    print('CSS length:', len(css_content))
    print('Has peer-checked in CSS?', 'peer-checked' in css_content)
    print('Has :checked in CSS?', ':checked' in css_content)
    # search for peer-checked rules
    matches = re.findall(r'[^{}]*peer-checked[^{}]*\{[^{}]*\}', css_content)
    print('Sample peer-checked rules count:', len(matches))
    if matches:
        print('Sample rule:', matches[0])
else:
    print('No CSS link found')

