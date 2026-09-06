import re

# 🚨 Phishing keywords for email + subject detection
PHISHING_KEYWORDS = [
    "urgent", "verify", "suspended", "account", "bank",
    "login", "password", "security", "alert",
    "click", "confirm", "update", "locked"
]

def extract_features(text):
    features = {}

    text_lower = text.lower()

    # Basic text features
    features["length"] = len(text)
    features["has_at"] = 1 if "@" in text else 0
    features["has_dash"] = 1 if "-" in text else 0
    features["dot_count"] = text.count(".")
    features["has_https"] = 1 if text.startswith("https") else 0

    # 🔥 NEW: phishing keyword detection (VERY IMPORTANT)
    keyword_count = 0
    for word in PHISHING_KEYWORDS:
        if word in text_lower:
            keyword_count += 1

    features["phishing_keywords"] = keyword_count

    return features


def predict_phishing(text):
    features = extract_features(text)

    score = 0

    # URL-based scoring (for URLs)
    if features["length"] > 25:
        score += 20
    if features["has_at"]:
        score += 30
    if features["has_dash"]:
        score += 15
    if features["dot_count"] > 3:
        score += 20
    if not features["has_https"]:
        score += 15

    # 🚨 NEW: Email phishing intelligence (for subjects & emails)
    if features["phishing_keywords"] >= 3:
        score += 50
    elif features["phishing_keywords"] >= 1:
        score += 25

    # Risk classification
    if score >= 60:
        level = "High"
    elif score >= 30:
        level = "Medium"
    else:
        level = "Low"
    score = min(score, 100)
    return score, level