def agent_decision(risk_score):

    if risk_score >= 90:
        return {
            "action": "Immediate IP Block + Alert",
            "message": "Critical threat detected. IP blocked and admin notified."
        }

    elif risk_score >= 60:
        return {
            "action": "Send Alert",
            "message": "Suspicious activity detected. Admin notified."
        }

    else:
        return {
            "action": "Monitor",
            "message": "Low risk activity under observation."
        }
