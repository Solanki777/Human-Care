"""
=======================================================================
 Nexora - URL Phishing Checker (standalone page)
=======================================================================
A focused Streamlit page that exposes ONLY the URL risk-scoring
feature, meant to be opened/linked from the PHP
security_dashboard.php admin panel -- same pattern as
gmail_scanner_page.py.

WHY A SEPARATE FILE / SEPARATE PORT
------------------------------------
Each Nexora feature gets its own small Streamlit page on its own
port so the PHP dashboard can link/embed them independently without
needing the full app.py (brute-force CSV monitor, email alerts,
etc.) running too.

RUNNING IT
----------
From the project folder that contains phishing_detector.py:

    streamlit run url_checker_page.py --server.port 8502

Leave it running in the background -- the PHP dashboard links to
http://localhost:8502 and will show "Offline" if it's not running.
=======================================================================
"""

import streamlit as st
from phishing_detector import predict_phishing
from decision_engine import agent_decision

st.set_page_config(page_title="Nexora URL Checker", layout="wide")

st.title("🌐 Nexora URL Phishing Checker")
st.caption("Paste a URL to get an instant AI-based risk score and recommended action")
st.divider()

url_input = st.text_input("Enter a URL to analyze", placeholder="https://example.com/login")

check_clicked = st.button("🔍 Analyze URL", type="primary")

if check_clicked and url_input.strip():
    score, level = predict_phishing(url_input.strip())
    safe_score = min(score, 100)

    col1, col2 = st.columns([1, 2])

    with col1:
        st.metric("Risk Score", f"{safe_score}/100")
        if level == "High":
            st.error(f"Risk Level: {level}")
        elif level == "Medium":
            st.warning(f"Risk Level: {level}")
        else:
            st.success(f"Risk Level: {level}")
        st.progress(safe_score / 100)

    with col2:
        decision = agent_decision(score)
        st.markdown("#### 🧠 Agent Decision")
        st.info(f"**Action:** {decision['action']}")
        st.write(decision["message"])

    st.divider()
    with st.expander("Why was this score given?"):
        st.markdown(
            "Risk is calculated from structural and lexical signals: URL length, "
            "presence of `@`, dash count, number of dots, missing HTTPS, and known "
            "phishing-related keywords. Higher counts of these signals raise the score."
        )

elif check_clicked:
    st.warning("Please enter a URL first.")
else:
    st.info("Enter a URL above and click **Analyze URL** to get a risk assessment.")