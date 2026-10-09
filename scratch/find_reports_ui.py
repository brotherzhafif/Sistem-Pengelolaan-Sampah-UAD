with open('ref/PS2_UAD_Prototype_UI.html', encoding='utf-8') as f:
    lines = f.readlines()

out = []
for i, line in enumerate(lines):
    l = line.lower()
    if 'view' in l and ('id=' in l or 'class="view' in l):
        out.append(f"Line {i+1}: {line.strip()}")
    if any(k in l for k in ['laporan', 'rekapitulasi', 'ekspor', 'report', 'view-']):
        out.append(f"Match {i+1}: {line.strip()[:120]}")

with open('scratch/reports_matches.txt', 'w', encoding='utf-8') as f:
    f.write("\n".join(out))
print(f"Written {len(out)} matches to scratch/reports_matches.txt")

