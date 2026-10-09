with open('scratch/kap_live_tab.html', 'r', encoding='utf-8') as f:
    text = f.read()

import re
idx = text.find('Indeks per Konstruk Perilaku')
idx2 = text.find('Tingkat Ketepatan Jawaban', idx)
print(text[idx:idx2])

