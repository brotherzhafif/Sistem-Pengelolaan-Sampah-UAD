with open('scratch/kap_error.html', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('MethodNotFoundException')
print(text[idx-200:idx+600])

