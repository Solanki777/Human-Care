"""
medimate_ai/tools/patient_tool.py
Read-only patient data queries for MediMate AI.

Security contract:
  - Every query is scoped to the authenticated patient's own ID.
  - Sensitive columns (password, verification details) are never selected.
  - All queries use parameterized SQL to prevent injection.
  - Raw dicts are returned — Gemini handles formatting for the user.
"""

import logging
from database import fetch_one, fetch_all

logger = logging.getLogger("medimate_ai.tools.patient_tool")


# ---------------------------------------------------------------------------
# Columns that must NEVER be returned to any caller
# ---------------------------------------------------------------------------
_EXCLUDED_COLUMNS = {"password", "is_verified", "verification_status", "verified_by", "verified_at"}


def _safe_profile(row: dict | None) -> dict | None:
    """Strip sensitive columns from a patient row."""
    if row is None:
        return None
    return {k: v for k, v in row.items() if k not in _EXCLUDED_COLUMNS}


# ---------------------------------------------------------------------------
# Public tool functions
# ---------------------------------------------------------------------------

def get_profile(user_id: int) -> dict:
    """
    Fetch the logged-in patient's own profile.

    Args:
        user_id: The authenticated patient's primary key (from session).

    Returns:
        Dict with patient details, or an error dict if not found.
    """
    logger.info("get_profile called for user_id=%s", user_id)

    query = """
        SELECT
            id,
            first_name,
            last_name,
            email,
            phone,
            dob,
            gender,
            blood_group,
            address,
            emergency_contact,
            registered_date
        FROM patients
        WHERE id = %s
        LIMIT 1
    """

    row = fetch_one(
    query,
    (user_id,),
    database="human_care_patients",
)

    if row is None:
        logger.warning("No patient found for user_id=%s", user_id)
        return {"error": "Patient profile not found."}

    return row


def get_appointments(user_id: int) -> list[dict]:
    """
    Fetch all appointments belonging to the logged-in patient.

    Joins with human_care_doctors.doctors to surface the doctor's name.
    Falls back gracefully if the doctors table is unavailable.

    Args:
        user_id: The authenticated patient's primary key.

    Returns:
        List of appointment dicts (may be empty), or an error dict in a list.
    """
    logger.info("get_appointments called for user_id=%s", user_id)

    # Try joining with the doctors table (separate DB on same server)
    query = """
        SELECT
            pa.id,
            pa.appointment_date,
            pa.status,
            pa.reason,
            pa.created_at,
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
            d.specialty AS doctor_specialty
        FROM human_care_patients.patient_appointments pa
        LEFT JOIN human_care_doctors.doctors d
               ON d.id = pa.doctor_id
        WHERE pa.patient_id = %s
        ORDER BY pa.appointment_date DESC
    """

    try:
        rows = fetch_all(
    query,
    (user_id,),
    database="human_care_patients",
)
    except Exception as exc:
        logger.exception("Doctor join failed: %s", exc)
        # Doctors DB may not be accessible yet — fall back to appointments only
        logger.warning("Could not join doctors table; returning appointments without doctor names.")
        query_fallback = """
            SELECT
                id,
                appointment_date,
                status,
                reason,
                created_at,
                doctor_id
            FROM patient_appointments
            WHERE patient_id = %s
            ORDER BY appointment_date DESC
        """
        rows = fetch_all(
    query_fallback,
    (user_id,),
    database="human_care_patients",
)

    if not rows:
        return [{"message": "No appointments found."}]

    return _safe_profile(row)


def get_medical_history(user_id: int) -> list[dict]:
    """
    Fetch all medical history records for the logged-in patient.

    Args:
        user_id: The authenticated patient's primary key.

    Returns:
        List of medical history dicts (may be empty).
    """
    logger.info("get_medical_history called for user_id=%s", user_id)

    query = """
        SELECT
            id,
            condition_name,
            diagnosis_date,
            notes
        FROM patient_medical_history
        WHERE patient_id = %s
        ORDER BY diagnosis_date DESC
    """

    rows = fetch_all(
    query,
    (user_id,),
    database="human_care_patients",
)

    if not rows:
        return [{"message": "No medical history records found."}]

    return rows