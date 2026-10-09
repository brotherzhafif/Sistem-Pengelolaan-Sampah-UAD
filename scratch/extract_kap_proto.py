with open('ref/PS2_UAD_Prototype_UI.html', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

idx = text.find('id="rp-kap"')
idx_end = text.find('</div>', idx + 2000)
with open('scratch/proto_kap.html', 'w', encoding='utf-8') as out:
    out.write(text[idx:idx+8000])
print("Saved to scratch/proto_kap.html")

