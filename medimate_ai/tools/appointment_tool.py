"""
medimate_ai/tools/appointment_tool.py

Appointment Tool for MediMate AI.

Responsibilities
----------------
- Show appointments for the logged-in patient.
- (Future)
    - Book appointments
    - Cancel appointments
    - Reschedule appointments

Security
--------
- Every query is filtered using the authenticated patient's user_id.
- Uses parameterized SQL queries.
- Returns raw dictionaries.
"""

import logging
from database import fetch_one, fetch_all, execute

logger = logging.getLogger("medimate_ai.tools.appointment_tool")


def show(user_id: int) -> list[dict]:
    """
    Return all appointments for the logged-in patient.

    Args:
        user_id: Logged-in patient's ID.

    Returns:
        List of appointment dictionaries.
    """

    logger.info("show() called for user_id=%s", user_id)

    query = """
        SELECT
            pa.id,
            pa.appointment_date,
            pa.status,
            pa.reason,
            pa.created_at,

            CONCAT(d.first_name,' ',d.last_name) AS doctor_name,
            d.specialization AS specialty

        FROM human_care_patients.patient_appointments pa

        LEFT JOIN human_care_doctors.doctors d
               ON pa.doctor_id = d.id

        WHERE pa.patient_id=%s

        ORDER BY pa.appointment_date DESC
    """

    try:
        appointments = fetch_all(query, (user_id,))

    except Exception as e:

        logger.warning(
            "Doctor database unavailable. Returning appointments only. %s",
            e
        )

        query = """
            SELECT
                id,
                doctor_id,
                appointment_date,
                status,
                reason,
                created_at

            FROM patient_appointments

            WHERE patient_id=%s

            ORDER BY appointment_date DESC
        """

        appointments = fetch_all(query, (user_id,))

    if not appointments:

        return [
            {
                "message": "No appointments found."
            }
        ]

    return appointments

def find_doctor_by_specialty(specialty: str) -> dict:
    """
    Find the first approved doctor for the requested specialty.

    Args:
        specialty: Example: "Cardiologist"

    Returns:
        Doctor information or an error message.
    """

    logger.info("Searching doctor with specialty=%s", specialty)

    query = """
        SELECT
            id,
            CONCAT(first_name, ' ', last_name) AS doctor_name,
            specialty,
            available_days,
            available_time,
            qualification,
            experience_years

        FROM human_care_doctors.doctors

        WHERE
            specialty LIKE %s
            AND verification_status='approved'
            AND is_deleted=0

        LIMIT 1
    """

    doctor = fetch_one(query, ("%" + specialty + "%",))

    if doctor is None:
        return {
            "success": False,
            "message": f"No approved {specialty} found."
        }

    return {
        "success": True,
        "doctor": doctor
    }

# ============================================================
# Future Functions
# ============================================================

def book(
    user_id: int,
    doctor_id: int,
    appointment_date: str,
    reason: str
) -> dict:
    """
    Book a new appointment.

    Args:
        user_id:
            Logged-in patient ID.

        doctor_id:
            Selected doctor's ID.

        appointment_date:
            MySQL DATETIME
            Example:
            2026-06-30 10:00:00

        reason:
            Reason for appointment.

    Returns:
        Dictionary indicating success or failure.
    """

    logger.info(
        "Booking appointment for patient=%s doctor=%s",
        user_id,
        doctor_id
    )

    query = """
        INSERT INTO patient_appointments
        (
            patient_id,
            doctor_id,
            appointment_date,
            status,
            reason
        )

        VALUES
        (
            %s,
            %s,
            %s,
            'scheduled',
            %s
        )
    """

    try:

        execute(
            query,
            (
                user_id,
                doctor_id,
                appointment_date,
                reason
            )
        )

        return {
            "success": True,
            "message": "Appointment booked successfully."
        }

    except Exception as e:

        logger.exception(e)

        return {
            "success": False,
            "message": "Unable to book appointment."
        }


def cancel():
    """
    Phase 4.3

    Cancel an appointment.
    """
    raise NotImplementedError


def reschedule():
    """
    Phase 4.4

    Reschedule an appointment.
    """
    raise NotImplementedError