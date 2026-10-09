import re

path = r'resources/views/livewire/pages/dashboard/index.blade.php'
with open(path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = []
skip = False
for line in lines:
    if "@if($dashboardMode === 'kap')" in line:
        skip = True
        continue
    if skip and "@endif" in line and "dashboardMode === 'kap'" in line:
        skip = False
        continue
    if not skip:
        new_lines.append(line)

with open(path, 'w', encoding='utf-8') as f:
    f.writelines(new_lines)

print("SUCCESS: stripped KAP section")

