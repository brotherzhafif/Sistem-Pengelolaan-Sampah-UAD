import urllib.request
import ssl
import re
import json
import html
import http.cookiejar

ctx = ssl.create_default_context()
ctx.check_hostname = False
ctx.verify_mode = ssl.CERT_NONE

BASE_URL = "https://ps2.brotherzhafif.my.id"

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(jar),
    urllib.request.HTTPSHandler(context=ctx)
)

print(f"Connecting to {BASE_URL}/survei-kap...")
# 1. GET /survei-kap
req = urllib.request.Request(f"{BASE_URL}/survei-kap", headers={
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    'Accept': 'text/html,application/xhtml+xml',
})
resp = opener.open(req)
html_doc = resp.read().decode('utf-8')

csrf_match = re.search(r'<meta name="csrf-token" content="([^"]+)"', html_doc)
csrf_token = csrf_match.group(1) if csrf_match else ""

snap_match = re.search(r'wire:snapshot="([^"]+)"', html_doc)
assert snap_match, "Livewire component snapshot not found in HTML!"
snapshot_raw = html.unescape(snap_match.group(1))
snapshot = json.loads(snapshot_raw)

print(f"Initialized Step 1. Current step: {snapshot['data']['currentStep']}")
assert "Survei Pemilahan Sampah Kampus" in html_doc, "Survei header missing!"
assert "33 pertanyaan" in html_doc, "33 pertanyaan badge missing!"

# Helper to send Livewire update request
def send_livewire(updates, calls):
    global snapshot
    payload = {
        "_token": csrf_token,
        "components": [
            {
                "snapshot": json.dumps(snapshot),
                "updates": updates,
                "calls": calls
            }
        ]
    }
    req_post = urllib.request.Request(
        f"{BASE_URL}/livewire/update",
        data=json.dumps(payload).encode('utf-8'),
        headers={
            'Content-Type': 'application/json',
            'X-Livewire': 'true',
            'X-CSRF-TOKEN': csrf_token,
            'User-Agent': 'Mozilla/5.0',
            'Accept': 'application/json',
        }
    )
    res = opener.open(req_post)
    res_data = json.loads(res.read().decode('utf-8'))
    comp = res_data['components'][0]
    snapshot = json.loads(comp['snapshot'])
    return snapshot, comp.get('effects', {})

# Step 1 -> Step 2 (Demografi to Knowledge)
print("\n--- Testing Demografi & Transition to Step 2 ---")
snapshot, effects = send_livewire(
    updates={
        "faculty_unit": "Fakultas Teknologi Industri",
        "respondent_role": "mahasiswa",
        "gender": "Laki-laki",
        "has_attended_training": True,
        "is_willing_volunteer": True,
        "respondent_name": "Test Responden KAP"
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Demografi nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 2, f"Expected step 2, got {snapshot['data']['currentStep']}"

# Step 2 -> Step 3 (Knowledge K1..K8 to Attitude)
print("\n--- Testing Knowledge K1..K8 & Transition to Step 3 ---")
snapshot, effects = send_livewire(
    updates={
        "knowledge.1": True,
        "knowledge.2": False,
        "knowledge.3": True,
        "knowledge.4": True,
        "knowledge.5": True,
        "knowledge.6": True,
        "knowledge.7": False,
        "knowledge.8": True,
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Knowledge nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 3, f"Expected step 3, got {snapshot['data']['currentStep']}"

# Step 3 -> Step 4 (Attitude A1..A6 to Practice)
print("\n--- Testing Attitude A1..A6 & Transition to Step 4 ---")
snapshot, effects = send_livewire(
    updates={
        "attitude.1": 5,
        "attitude.2": 5,
        "attitude.3": 4,
        "attitude.4": 5,
        "attitude.5": 4,
        "attitude.6": 5,
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Attitude nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 4, f"Expected step 4, got {snapshot['data']['currentStep']}"

# Step 4 -> Step 5 (Practice P1..P6 to Satisfaction)
print("\n--- Testing Practice P1..P6 & Transition to Step 5 ---")
snapshot, effects = send_livewire(
    updates={
        "practice.1": 4,
        "practice.2": 4,
        "practice.3": 5,
        "practice.4": 4,
        "practice.5": 4,
        "practice.6": 4,
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Practice nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 5, f"Expected step 5, got {snapshot['data']['currentStep']}"

# Step 5 -> Step 6 (Satisfaction S1..S5 to Facilities & Barriers)
print("\n--- Testing Satisfaction S1..S5 & Transition to Step 6 ---")
snapshot, effects = send_livewire(
    updates={
        "satisfaction.1": 4,
        "satisfaction.2": 4,
        "satisfaction.3": 4,
        "satisfaction.4": 4,
        "satisfaction.5": 4,
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Satisfaction nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 6, f"Expected step 6, got {snapshot['data']['currentStep']}"

# Step 6 -> Step 7 (Facilities & Barriers to Review)
print("\n--- Testing Facilities/Barriers & Transition to Step 7 (Review) ---")
snapshot, effects = send_livewire(
    updates={
        "facilities": ["Tempat sampah terpilah (organik/anorganik)", "Komposter"],
        "barriers": ["Tempat sampah terpilah terlalu jauh"],
        "motivations": ["Kesadaran lingkungan", "Aturan/kebijakan kampus"],
        "feedback": "Sistem pemilahan sudah sangat baik dan informatif."
    },
    calls=[
        {"path": "", "method": "nextStep", "params": []}
    ]
)
print(f"Current step after Facilities nextStep: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 7, f"Expected step 7, got {snapshot['data']['currentStep']}"

# Step 7 -> Submit Survey (Step 8: Terima Kasih)
print("\n--- Testing Submit Survey (Review to Step 8) ---")
snapshot, effects = send_livewire(
    updates={},
    calls=[
        {"path": "", "method": "submitSurvey", "params": []}
    ]
)
print(f"Current step after submitSurvey: {snapshot['data']['currentStep']}")
assert snapshot['data']['currentStep'] == 8, f"Expected step 8, got {snapshot['data']['currentStep']}"
assert snapshot['data']['isSubmitted'] is True, "Expected isSubmitted to be True!"

# Verify toast dispatch
dispatches = effects.get('dispatches', [])
has_toast = any(d.get('name') == 'toast' for d in dispatches)
print(f"Dispatched floating toast: {has_toast}")
assert has_toast, "Floating toast notification was not dispatched on survey submit!"

html_result = effects.get('html', '')
assert "Terima Kasih Banyak" in html_result, "Thank you title missing from result HTML!"
assert "Skor KAP" in html_result, "Overall KAP score card missing from result HTML!"

print("\n[SUCCESS] ALL 8 STEPS OF THE NEW 33-QUESTION KAP SURVEY WORKFLOW PASSED 100% GREEN!")
