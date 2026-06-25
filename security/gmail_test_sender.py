import smtplib
from email.mime.text import MIMEText

# Sender Gmail
EMAIL = "solankimaheshkhash230@gmail.com"
APP_PASSWORD = "pdoqfkkkkmpdykxp"

# Receiver (your Gmail)
TO_EMAIL = "solankimaheshkhash7@gmail.com"

# Safe Email
safe_subject = "Project Meeting Schedule"
safe_body = """
Hello Mahesh,

Your project review meeting is scheduled for tomorrow at 11:00 AM.

Regards,
Team
"""

# Phishing Email
phishing_subject = "URGENT: Verify Your Bank Account Immediately"
phishing_body = """
Your account has been suspended.

Click below to verify your password immediately.

https://fake-bank-security-login.com

Failure to verify may result in account closure.
"""

emails = [
    (safe_subject, safe_body),
    (phishing_subject, phishing_body)
]

with smtplib.SMTP_SSL("smtp.gmail.com", 465) as server:
    server.login(EMAIL, APP_PASSWORD)

    for subject, body in emails:
        msg = MIMEText(body)
        msg["Subject"] = subject
        msg["From"] = EMAIL
        msg["To"] = TO_EMAIL

        server.send_message(msg)
        print(f"Sent: {subject}")

print("Done.")