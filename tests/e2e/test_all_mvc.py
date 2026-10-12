import subprocess
import sys
import os

TEST_FILES = [
    "test_auth_flow.py",
    "test_dashboard_mvc.py",
    "test_weighing_mvc.py",
    "test_sales_mvc.py",
    "test_pickups_mvc.py",
    "test_expenses_mvc.py",
    "test_finance_mvc.py",
    "test_master_mvc.py",
    "test_users_mvc.py",
    "test_kap_mvc.py",
    "test_reports_mvc.py",
    "test_notifications_mvc.py",
    "verify_all_pages.py",
]

script_dir = os.path.dirname(os.path.abspath(__file__))

print("=" * 70)
print("EXECUTING FULL MVC REFACTOR TEST SUITE ACROSS ALL SYSTEM MODULES")
print("Target: https://ps2.brotherzhafif.my.id")
print("=" * 70)

failures = []

for idx, test_file in enumerate(TEST_FILES, start=1):
    test_path = os.path.join(script_dir, test_file)
    print(f"\n[{idx}/{len(TEST_FILES)}] Running {test_file}...")
    result = subprocess.run([sys.executable, test_path], capture_output=True, text=True)
    if result.returncode == 0:
        print(f"  >>> PASS: {test_file}")
    else:
        print(f"  >>> FAIL: {test_file}")
        print(result.stderr or result.stdout)
        failures.append(test_file)

print("\n" + "=" * 70)
if not failures:
    print(f"ALL {len(TEST_FILES)} MVC MODULE TEST SUITES PASSED FLAWLESSLY ON LIVE VPS!")
    print("=" * 70)
    sys.exit(0)
else:
    print(f"FAILED TESTS ({len(failures)}): {failures}")
    print("=" * 70)
    sys.exit(1)

