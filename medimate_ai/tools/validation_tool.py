"""
medimate_ai/tools/validation_tool.py

Validation Tool

Responsible for validating booking information
before any database operation is performed.

It DOES NOT:
- Read the database
- Book appointments
- Check doctor availability

Those responsibilities belong to other tools.
"""

from datetime import datetime, timedelta

# ---------------------------------------------------
# Allowed consultation types
# ---------------------------------------------------

CONSULTATION_TYPES = {
    "in-person",
    "online",
}

# ---------------------------------------------------
# Validate Consultation Type
# ---------------------------------------------------

def validate_consultation_type(value: str):

    value = value.strip().lower()

    if value not in CONSULTATION_TYPES:

        return False, (
            "Consultation type must be "
            "'In-Person' or 'Online'."
        )

    return True, ""


# ---------------------------------------------------
# Validate Date
# ---------------------------------------------------

def validate_date(date_obj: datetime):

    today = datetime.today()

    max_date = today + timedelta(days=30)

    if date_obj.date() < today.date():

        return False, "Appointment date cannot be in the past."

    if date_obj.date() > max_date.date():

        return False, (
            "Appointments can only be booked "
            "within the next 30 days."
        )

    return True, ""


# ---------------------------------------------------
# Validate Time
# ---------------------------------------------------

def validate_time(time_str: str):

    try:

        datetime.strptime(time_str, "%H:%M")

        return True, ""

    except ValueError:

        return False, (
            "Invalid time format. "
            "Use HH:MM (24-hour format)."
        )


# ---------------------------------------------------
# Validate Reason
# ---------------------------------------------------

def validate_reason(reason: str):

    reason = reason.strip()

    if len(reason) < 10:

        return False, (
            "Reason should contain at least "
            "10 characters."
        )

    if len(reason) > 500:

        return False, (
            "Reason cannot exceed "
            "500 characters."
        )

    return True, ""


# ---------------------------------------------------
# Validate Symptoms
# ---------------------------------------------------

def validate_symptoms(symptoms: str):

    if symptoms is None:

        return True, ""

    if len(symptoms.strip()) > 500:

        return False, (
            "Additional symptoms cannot exceed "
            "500 characters."
        )

    return True, ""


# ---------------------------------------------------
# Validate Complete Booking
# ---------------------------------------------------

def validate_booking(state: dict):
    """
    Validate all booking information.

    Returns

    {
        "success": True
    }

    OR

    {
        "success": False,
        "message": "..."
    }
    """

    required = [
        "doctor",
        "date",
        "time",
        "consultation_type",
        "reason",
    ]

    for field in required:

        if not state.get(field):

            return {
                "success": False,
                "message": f"Missing required field: {field}"
            }

    ok, msg = validate_consultation_type(
        state["consultation_type"]
    )

    if not ok:

        return {
            "success": False,
            "message": msg
        }

    ok, msg = validate_reason(
        state["reason"]
    )

    if not ok:

        return {
            "success": False,
            "message": msg
        }

    ok, msg = validate_symptoms(
        state.get("symptoms")
    )

    if not ok:

        return {
            "success": False,
            "message": msg
        }

    return {
        "success": True
    }