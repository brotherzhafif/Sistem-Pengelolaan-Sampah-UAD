with open('ref/PS2_UAD_Prototype_UI.html', 'r', encoding='utf-8', errors='ignore') as f:
    text = f.read()

start = text.find('id="v-laporan"')
end = text.find('id="v-', start + 20)
if end == -1:
    end = start + 15000

print("Length of v-laporan:", end - start)
with open('scratch/v_laporan_proto.html', 'w', encoding='utf-8') as out:
    out.write(text[start:end])
print("Saved to scratch/v_laporan_proto.html")

