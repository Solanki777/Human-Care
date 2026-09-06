#!/usr/bin/env python3
"""
Run this ONCE, locally, after you have:
  - Your InfinityFree MySQL hostname, username, password, and DB prefix
  - Your two deployed Streamlit Cloud URLs (Gmail scanner + URL checker)
  - Your Groq API key

It rewrites every placeholder token in the project in-place. After running
this, upload the whole 'vscode' folder to InfinityFree via FTP.

Usage:
    python3 apply_config.py

You'll be prompted for each value. Nothing is sent anywhere; this only
edits files on your own disk.
"""
import os

ROOT = os.path.dirname(os.path.abspath(__file__))

print("=== Human Care / Nexora deployment config ===\n")
db_host = input("InfinityFree MySQL hostname (e.g. sql307.infinityfree.com): ").strip()
db_user = input("InfinityFree MySQL username  (e.g. if0_12345678): ").strip()
db_pass = input("InfinityFree MySQL password: ").strip()
db_prefix = input("InfinityFree DB name prefix (e.g. if0_12345678_): ").strip()
gmail_url = input("Deployed Gmail Scanner Streamlit URL (e.g. https://your-app.streamlit.app): ").strip()
url_checker_url = input("Deployed URL Checker Streamlit URL: ").strip()
groq_key = input("Groq API key: ").strip()

replacements = {
    "__DBHOST__": db_host,
    "__DBUSER__": db_user,
    "__DBPASS__": db_pass,
    "__DBPREFIX__": db_prefix,
    "__GMAIL_SCANNER_URL__": gmail_url,
    "__URL_CHECKER_URL__": url_checker_url,
    "__GROQ_API_KEY__": groq_key,
}

changed_files = 0
total_subs = 0
for dirpath, dirnames, filenames in os.walk(ROOT):
    if "vendor" in dirpath or ".git" in dirpath:
        continue
    for fn in filenames:
        if not (fn.endswith(".php") or fn.endswith(".sql")):
            continue
        fp = os.path.join(dirpath, fn)
        with open(fp, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
        orig = content
        for token, value in replacements.items():
            n = content.count(token)
            if n:
                content = content.replace(token, value)
                total_subs += n
        if content != orig:
            with open(fp, "w", encoding="utf-8") as f:
                f.write(content)
            changed_files += 1

print(f"\nDone. Updated {total_subs} placeholder(s) across {changed_files} file(s).")
print("You can now upload this folder to InfinityFree via FTP.")
print("(This script has done its job — you can delete apply_config.py before uploading.)")
