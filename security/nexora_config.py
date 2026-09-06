"""
Nexora configuration.

Edit these values if your MySQL setup differs from the defaults used
elsewhere in the Human Care project (login.php uses the same
host/user/password).
"""

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",            # blank, matching login.php
    "database": "security_logs_db",
    "port": 3306,
}

# How far back (minutes) Nexora looks when analyzing each run.
ANALYSIS_WINDOW_MINUTES = 15

# Credential Stuffing: same IP, many DIFFERENT emails attempted.
CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD = 5

# Password Spraying: same-ish timeframe, ONE IP (or small IP set)
# attempting logins across many different accounts with low attempts
# per account (the "low and slow" pattern vs brute force's "many
# attempts on one account").
PASSWORD_SPRAY_MIN_ACCOUNTS = 5
PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT = 3

# How long automated Nexora blocks last before expiring (hours).
# Set to None for permanent blocks.
NEXORA_BLOCK_DURATION_HOURS = 24