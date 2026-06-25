#!/usr/bin/env python3
"""
=======================================================================
 Nexora - Login Threat Detector (security/login_threat_detector.py)
=======================================================================
Background analyzer for the Human Care login system.

WHAT THIS DOES
---------------
PHP (login.php + security_functions.php) already handles, in real
time, on every login attempt:
    - Brute Force Detection
    - SQL Injection Attempt Detection
    - Suspicious Login Rate Detection
    - Previously Blocked IP Detection
    - Writes every attempt to security_logs_db.login_attempts

This script (Nexora) runs SEPARATELY and PERIODICALLY (via Windows
Task Scheduler, cron, or manual run) to catch patterns that require
looking across MANY rows / accounts / IPs at once -- which is
expensive to do on every single login request:

    - Credential Stuffing Detection
        Same IP attempting logins against many DIFFERENT email
        accounts in a short window (classic stuffing behavior:
        attacker has a list of stolen email:password pairs and is
        trying them against this site).

    - Password Spraying Detection
        One IP (or a small cluster) making a LOW number of attempts
        against EACH of MANY different accounts -- "low and slow",
        designed to avoid per-account lockouts.

It writes any IPs it blocks into the SAME blocked_ips table that
login.php's is_ip_blocked() reads from, so a Nexora-issued block
takes effect on the very next login request -- no PHP changes needed.

HOW TO RUN
----------
Manually (for testing):
    python security/login_threat_detector.py

Repeatedly (recommended), via Windows Task Scheduler:
    Program: C:\\path\\to\\python.exe
    Arguments: C:\\xampp\\htdocs\\your_project\\security\\login_threat_detector.py
    Trigger: every 1-5 minutes

FAIL-SAFE
---------
If the database is unreachable or a query errors, this script logs
the error and exits without affecting login.php at all -- the PHP
side has its own independent fail-safe and never depends on this
script being run.
=======================================================================
"""

import sys
import logging
from datetime import datetime
from email_alert import send_security_alert

try:
    import mysql.connector
    from mysql.connector import Error as MySQLError
except ImportError:
    print("ERROR: mysql-connector-python is not installed.")
    print("Run: pip install -r security/requirements.txt")
    sys.exit(1)

from nexora_config import (
    DB_CONFIG,
    ANALYSIS_WINDOW_MINUTES,
    CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD,
    PASSWORD_SPRAY_MIN_ACCOUNTS,
    PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT,
    NEXORA_BLOCK_DURATION_HOURS,
)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [Nexora] %(levelname)s: %(message)s",
)
log = logging.getLogger("nexora")


# -----------------------------------------------------------------------
# DB connection (fail-safe: returns None on any error, never raises out)
# -----------------------------------------------------------------------
def get_connection():
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        return conn
    except MySQLError as e:
        log.error(f"Database connection failed: {e}")
        return None


# -----------------------------------------------------------------------
# is_ip_blocked(conn, ip) -> bool
# Mirrors the PHP version, used to avoid duplicate-blocking noise.
# -----------------------------------------------------------------------
def is_ip_blocked(conn, ip: str) -> bool:
    try:
        cur = conn.cursor()
        cur.execute(
            """
            SELECT id FROM blocked_ips
            WHERE ip_address = %s AND is_active = 1
              AND (expires_at IS NULL OR expires_at > NOW())
            LIMIT 1
            """,
            (ip,),
        )
        row = cur.fetchone()
        cur.close()
        return row is not None
    except MySQLError as e:
        log.error(f"is_ip_blocked query failed for {ip}: {e}")
        return False


# -----------------------------------------------------------------------
# block_ip(conn, ip, threat_type, label, reason) -> bool
# Writes/refreshes a row in blocked_ips (blocked_by = 'nexora') and an
# entry in threat_events. Same table PHP's is_ip_blocked() reads.
# -----------------------------------------------------------------------
def block_ip(conn, ip: str, threat_type: str, label: str, reason: str) -> bool:
    try:
        cur = conn.cursor()

        if NEXORA_BLOCK_DURATION_HOURS is not None:
            expires_clause = "DATE_ADD(NOW(), INTERVAL %s HOUR)"
            params = (ip, threat_type, reason, NEXORA_BLOCK_DURATION_HOURS)
            query = f"""
                INSERT INTO blocked_ips
                    (ip_address, threat_type, reason, blocked_by, blocked_at, expires_at, is_active)
                VALUES (%s, %s, %s, 'nexora', NOW(), {expires_clause}, 1)
                ON DUPLICATE KEY UPDATE
                    threat_type = VALUES(threat_type),
                    reason      = VALUES(reason),
                    blocked_by  = 'nexora',
                    blocked_at  = NOW(),
                    expires_at  = VALUES(expires_at),
                    is_active   = 1
            """
        else:
            params = (ip, threat_type, reason)
            query = """
                INSERT INTO blocked_ips
                    (ip_address, threat_type, reason, blocked_by, blocked_at, expires_at, is_active)
                VALUES (%s, %s, %s, 'nexora', NOW(), NULL, 1)
                ON DUPLICATE KEY UPDATE
                    threat_type = VALUES(threat_type),
                    reason      = VALUES(reason),
                    blocked_by  = 'nexora',
                    blocked_at  = NOW(),
                    expires_at  = NULL,
                    is_active   = 1
            """

        cur.execute(query, params)
        conn.commit()
        cur.close()

        log_threat_event(conn, ip, None, threat_type, reason, "ip_blocked")

        # Send email notification
        send_security_alert(
            ip=ip,
            threat_type=label,
            reason=reason
        )

        log.warning(f"BLOCKED {ip} -> {label}: {reason}")
        
        return True
    except MySQLError as e:
        log.error(f"block_ip failed for {ip}: {e}")
        return False


# -----------------------------------------------------------------------
# log_threat_event(...) -> audit trail row in threat_events
# -----------------------------------------------------------------------
def log_threat_event(conn, ip, email, threat_type, reason, action_taken=None):
    try:
        cur = conn.cursor()
        cur.execute(
            """
            INSERT INTO threat_events
                (ip_address, email, threat_type, reason, detected_by, detected_at, action_taken)
            VALUES (%s, %s, %s, %s, 'nexora', NOW(), %s)
            """,
            (ip, email, threat_type, reason, action_taken),
        )
        conn.commit()
        cur.close()
    except MySQLError as e:
        log.error(f"log_threat_event failed for {ip}: {e}")


# -----------------------------------------------------------------------
# detect_credential_stuffing(conn)
# Same IP, many DISTINCT emails attempted within the analysis window.
# -----------------------------------------------------------------------
def detect_credential_stuffing(conn):
    try:
        cur = conn.cursor(dictionary=True)
        cur.execute(
            """
            SELECT ip_address, COUNT(DISTINCT email) AS distinct_emails
            FROM login_attempts
            WHERE attempted_at >= (NOW() - INTERVAL %s MINUTE)
              AND email IS NOT NULL AND email != ''
            GROUP BY ip_address
            HAVING distinct_emails >= %s
            """,
            (ANALYSIS_WINDOW_MINUTES, CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD),
        )
        rows = cur.fetchall()
        cur.close()

        for row in rows:
            ip = row["ip_address"]
            if is_ip_blocked(conn, ip):
                continue
            reason = (
                f"IP {ip} attempted logins with {row['distinct_emails']} different "
                f"email accounts within {ANALYSIS_WINDOW_MINUTES} minutes."
            )
            block_ip(conn, ip, "credential_stuffing", "Credential Stuffing Attack", reason)

    except MySQLError as e:
        log.error(f"detect_credential_stuffing query failed: {e}")


# -----------------------------------------------------------------------
# detect_password_spraying(conn)
# A small set of IPs making LOW attempts per account but spread across
# MANY different accounts -- "low and slow" pattern.
# -----------------------------------------------------------------------
def detect_password_spraying(conn):
    try:
        cur = conn.cursor(dictionary=True)
        # Attempts grouped by IP + email, counted, within window.
        cur.execute(
            """
            SELECT ip_address, email, COUNT(*) AS attempts
            FROM login_attempts
            WHERE attempted_at >= (NOW() - INTERVAL %s MINUTE)
              AND email IS NOT NULL AND email != ''
              AND status = 'failed'
            GROUP BY ip_address, email
            """,
            (ANALYSIS_WINDOW_MINUTES,),
        )
        rows = cur.fetchall()
        cur.close()

        # Aggregate per IP: how many distinct accounts, all with low
        # attempt counts (<= threshold)?
        per_ip = {}
        for row in rows:
            ip = row["ip_address"]
            per_ip.setdefault(ip, [])
            per_ip[ip].append(row["attempts"])

        for ip, attempt_counts in per_ip.items():
            distinct_accounts = len(attempt_counts)
            low_and_slow = all(
                a <= PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT for a in attempt_counts
            )

            if distinct_accounts >= PASSWORD_SPRAY_MIN_ACCOUNTS and low_and_slow:
                if is_ip_blocked(conn, ip):
                    continue
                reason = (
                    f"IP {ip} attempted logins against {distinct_accounts} different "
                    f"accounts (<= {PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT} attempts each) "
                    f"within {ANALYSIS_WINDOW_MINUTES} minutes -- pattern consistent with "
                    f"password spraying."
                )
                block_ip(conn, ip, "password_spraying", "Password Spraying Attack", reason)

    except MySQLError as e:
        log.error(f"detect_password_spraying query failed: {e}")


# -----------------------------------------------------------------------
# main()
# -----------------------------------------------------------------------
def main():
    log.info("Nexora analysis run starting...")

    conn = get_connection()
    if conn is None:
        log.error("Could not connect to security_logs_db. Aborting this run "
                   "(login.php is unaffected and continues operating normally).")
        sys.exit(1)

    try:
        detect_credential_stuffing(conn)
        detect_password_spraying(conn)
        log.info("Nexora analysis run complete.")
    finally:
        conn.close()


if __name__ == "__main__":
    main()