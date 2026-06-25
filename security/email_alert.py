import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

SENDER_EMAIL = "solankimaheshkhash230@gmail.com"
APP_PASSWORD = "pdoqfkkkkmpdykxp"

ADMIN_EMAIL = "solankimaheshkhash7@gmail.com"

def send_security_alert(ip, attack_type, details=""):
    try:
        msg = MIMEMultipart()

        msg["From"] = SENDER_EMAIL
        msg["To"] = ADMIN_EMAIL
        msg["Subject"] = "🚨 Nexora Security Alert"

        body = f"""
Nexora Security Notification

Attack Type: {attack_type}

Blocked IP: {ip}

Details:
{details}

Action Taken:
IP blocked automatically by Nexora.

Regards,
Nexora Security Agent
"""

        msg.attach(MIMEText(body, "plain"))

        server = smtplib.SMTP("smtp.gmail.com", 587)
        server.starttls()
        server.login(SENDER_EMAIL, APP_PASSWORD)

        server.send_message(msg)
        server.quit()

        print("Alert email sent.")

    except Exception as e:
        print("Email Error:", e)