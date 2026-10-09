with open('ref/PS2_UAD_Prototype_UI.html', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

idx = text.find('id="mt-katbiaya"')
print("Found mt-katbiaya at:", idx)
print(text[idx:idx+1500])

