"""
=======================================================================
 Nexora - Gmail Threat Scanner (standalone page)
=======================================================================
This is a focused Streamlit page that exposes ONLY the Gmail
inbox/spam scan feature, meant to be opened/linked from the PHP
security_dashboard.php admin panel.

WHY A SEPARATE FILE
--------------------
The original app.py mixes brute-force CSV monitoring, a URL phishing
checker, and the Gmail scanner into one big page. Since the dashboard
only needs the Gmail scan button + results, this file keeps that
scope tight so the embedded/linked view stays clean and loads fast.

RUNNING IT
----------
From the project folder that contains gmail_service.py,
phishing_detector.py, credentials.json, and (after first run)
token.json:

    streamlit run gmail_scanner_page.py --server.port 8501

Leave it running in the background (or as a Windows service/Task
Scheduler entry) -- the PHP dashboard links to
http://localhost:8501 and will show "Offline" if it's not running.

FIRST-TIME GMAIL AUTH
----------------------
The first time this runs, get_gmail_service() will pop open a
browser window for you to authorize the Gmail account. After that,
token.json is saved and it won't ask again until the token expires
or is revoked.
=======================================================================
"""

import os
import datetime
import streamlit as st
import traceback

from gmail_service import (
    get_gmail_service,
    fetch_unread_emails,
    get_email_details,
    move_to_spam,
    get_or_create_label,
)
from phishing_detector import predict_phishing

# -----------------------------------------------------------------------
# Streamlit Cloud bootstrap: materialize credentials.json / token.json
# from Streamlit secrets (Settings -> Secrets) so gmail_service.py's
# local-file-based auth works unchanged. No-op if the files already
# exist locally (e.g. when running on your own machine).
# -----------------------------------------------------------------------
if "gcp_credentials_json" in st.secrets and not os.path.exists("credentials.json"):
    with open("credentials.json", "w") as f:
        f.write(st.secrets["gcp_credentials_json"])
if "gcp_token_json" in st.secrets and not os.path.exists("token.json"):
    with open("token.json", "w") as f:
        f.write(st.secrets["gcp_token_json"])

# -----------------------------------------------------------------------
# Page setup
# -----------------------------------------------------------------------
st.set_page_config(page_title="Nexora Gmail Scanner", layout="wide")

st.title("📧 Nexora Gmail Threat Scanner")
st.caption("Autonomous phishing detection for your Inbox + Spam folders")
st.divider()

# Senders that always skip phishing scoring (your own verified contacts/services)
TRUSTED_SENDERS = [
    "linkedin.com",
    "google.com",
    "github.com",
]

LOG_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), "security_logs")
MALICIOUS_LOG = os.path.join(LOG_DIR, "malicious_senders.txt")

# -----------------------------------------------------------------------
# Session state
# -----------------------------------------------------------------------
if "email_threats" not in st.session_state:
    st.session_state.email_threats = []
if "last_scan_time" not in st.session_state:
    st.session_state.last_scan_time = None


def run_scan():
    """Runs the Gmail scan and stores results in session state."""
    service = get_gmail_service()
    nexora_label_id = get_or_create_label(service)
    emails = fetch_unread_emails(service)

    MAX_EMAILS = 20
    emails = emails[:MAX_EMAILS]

    detected = []
    scanned_count = 0

    progress = st.progress(
    0,
    text=f"Scanning latest {len(emails)} emails..."
)

    for i, email in enumerate(emails):
        msg_id = email["id"]
        subject, sender = get_email_details(service, msg_id)
        sender_lower = sender.lower()
        scanned_count += 1

        full_msg = service.users().messages().get(userId="me", id=msg_id).execute()
        labels = full_msg.get("labelIds", [])
        if "SPAM" in labels:
            source_folder = "Spam"
        elif "INBOX" in labels:
            source_folder = "Inbox"
        else:
            source_folder = "Other"

        if any(trusted in sender_lower for trusted in TRUSTED_SENDERS):
            service.users().messages().modify(
                userId="me",
                id=msg_id,
                body={"removeLabelIds": ["UNREAD"], "addLabelIds": [nexora_label_id]},
            ).execute()
            progress.progress((i + 1) / max(len(emails), 1))
            continue

        score, level = predict_phishing(subject)

        detected.append(
            {
                "Sender": sender,
                "Folder": source_folder,
                "Subject": subject,
                "Risk Score": score,
                "Risk Level": level,
            }
        )

        if level == "High":
            move_to_spam(service, msg_id)
            os.makedirs(LOG_DIR, exist_ok=True)
            with open(MALICIOUS_LOG, "a", encoding="utf-8") as f:
                f.write(f"{datetime.datetime.now()} | {sender} | {subject}\n")
        else:
            service.users().messages().modify(
                userId="me",
                id=msg_id,
                body={"removeLabelIds": ["UNREAD"], "addLabelIds": [nexora_label_id]},
            ).execute()

        progress.progress((i + 1) / max(len(emails), 1))

    progress.empty()
    st.session_state.email_threats = detected
    st.session_state.last_scan_time = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    return scanned_count


# -----------------------------------------------------------------------
# Scan trigger
# -----------------------------------------------------------------------
col1, col2 = st.columns([1, 3])
with col1:
    scan_clicked = st.button("📨 Start Email Scan", type="primary", use_container_width=True)
with col2:
    if st.session_state.last_scan_time:
        st.caption(f"Last scan: {st.session_state.last_scan_time}")

if scan_clicked:
    try:
        with st.spinner("Connecting to Gmail..."):
            count = run_scan()
        st.success(f"Scan complete — {count} unread emails checked.")
    except FileNotFoundError as e:
        st.error(
            "Missing credentials.json. Place your Gmail OAuth client "
            f"secret file next to this script. ({e})"
        )
    except Exception as e:
        st.error(f"Gmail scan failed: {e}")
        st.code(traceback.format_exc())

st.divider()

# -----------------------------------------------------------------------
# Results
# -----------------------------------------------------------------------
threats = st.session_state.email_threats

if threats:
    high_risk = [t for t in threats if t["Risk Level"] == "High"]
    medium_risk = [t for t in threats if t["Risk Level"] == "Medium"]

    m1, m2, m3 = st.columns(3)
    m1.metric("📬 Emails Scored", len(threats))
    m2.metric("🔴 High Risk", len(high_risk))
    m3.metric("🟡 Medium Risk", len(medium_risk))

    st.subheader("Scan Results")

    import pandas as pd

    df = pd.DataFrame(threats)

    def highlight_risk(row):
        color = ""
        if row["Risk Level"] == "High":
            color = "background-color: rgba(255,77,109,0.18)"
        elif row["Risk Level"] == "Medium":
            color = "background-color: rgba(245,197,66,0.15)"
        return [color] * len(row)

    st.dataframe(df.style.apply(highlight_risk, axis=1), use_container_width=True)

    if high_risk:
        st.warning(
            f"{len(high_risk)} high-risk email(s) were automatically moved to Spam."
        )
else:
    st.info("No scan run yet this session. Click **Start Email Scan** above.")