"""
medimate_ai/tools/appointment_tool.py

Appointment Tool

This tool NEVER talks directly to MySQL.

Instead it calls the PHP AppointmentService
through the AI booking API.

This guarantees the AI uses exactly the same
booking workflow as the Human Care website.
"""

import logging
import requests

logger = logging.getLogger("medimate_ai.tools.appointment_tool")


# ----------------------------------------------------------
# PHP Endpoint
# ----------------------------------------------------------

BOOKING_API = "http://127.0.0.1/vscode/api/ai_book_appointment.php"


# ----------------------------------------------------------
# Book Appointment
# ----------------------------------------------------------

def book(
    patient_id: int,
    doctor_id: int,
    appointment_date: str,
    appointment_time: str,
    consultation_type: str,
    reason: str,
    symptoms: str = ""
):
    """
    Submit appointment request through PHP.

    Parameters
    ----------
    patient_id : int
    doctor_id : int
    appointment_date : YYYY-MM-DD
    appointment_time : HH:MM:SS
    consultation_type : In-Person / Online
    reason : str
    symptoms : str
    """

    payload = {

        "patient_id": patient_id,

        "doctor_id": doctor_id,

        "appointment_date": appointment_date,

        "appointment_time": appointment_time,

        "consultation_type": consultation_type,

        "reason": reason,

        "symptoms": symptoms

    }

    logger.info(
        "Submitting appointment request..."
    )

    try:

        response = requests.post(

            BOOKING_API,

            json=payload,

            timeout=30

        )

    except requests.exceptions.RequestException as e:

        logger.exception(e)

        return {

            "success": False,

            "message":
                "Unable to connect to booking service."

        }

    if response.status_code != 200:

        logger.error(response.text)

        return {

            "success": False,

            "message":
                "Booking service returned an error."

        }

    try:

        result = response.json()

    except Exception:

        logger.error(response.text)

        return {

            "success": False,

            "message":
                "Invalid response from booking service."

        }

    logger.info(result)

    return result


# ----------------------------------------------------------
# Cancel Appointment
# ----------------------------------------------------------

def cancel(
    appointment_id: int
):
    """
    Placeholder.

    Will be implemented later.
    """

    return {

        "success": False,

        "message":
            "Cancel appointment will be implemented later."

    }


# ----------------------------------------------------------
# Reschedule Appointment
# ----------------------------------------------------------

def reschedule(
    appointment_id: int
):
    """
    Placeholder.

    Will be implemented later.
    """

    return {

        "success": False,

        "message":
            "Reschedule appointment will be implemented later."

    }