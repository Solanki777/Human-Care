"""
medimate_ai/tools/doctor_tool.py

Doctor Tool

Responsibilities
----------------
- Find doctors by specialty
- Find doctors by name
- Get doctor by ID
- Return only approved and active doctors

This tool NEVER books appointments.
"""

import logging

from database import fetch_one, fetch_all

logger = logging.getLogger("medimate_ai.tools.doctor_tool")


# ============================================================
# Find Doctor by Specialty
# ============================================================

def find_by_specialty(specialty: str) -> dict:
    """
    Find approved doctors for a specialty.

    Example:
        Cardiologist
        Dentist
        Neurologist
    """

    logger.info("Searching doctor by specialty=%s", specialty)

    query = """
        SELECT
            id,
            CONCAT(first_name,' ',last_name) AS doctor_name,
            specialty,
            qualification,
            experience_years,
            available_days,
            available_time

        FROM human_care_doctors.doctors

        WHERE
            specialty LIKE %s
            AND verification_status='approved'
            AND is_deleted=0

        ORDER BY experience_years DESC
    """

    doctors = fetch_all(
        query,
        ("%" + specialty + "%",),
        database="human_care_doctors"
    )

    if not doctors:

        return {
            "success": False,
            "message": f"No approved doctor found for '{specialty}'."
        }

    return {
        "success": True,
        "count": len(doctors),
        "doctors": doctors
    }


# ============================================================
# Find Doctor by Name
# ============================================================

def find_by_name(name: str):

    logger.info("Searching doctor by name=%s", name)

    query = """
        SELECT
            id,
            CONCAT(first_name,' ',last_name) AS doctor_name,
            specialty,
            qualification,
            experience_years,
            available_days,
            available_time

        FROM human_care_doctors.doctors

        WHERE
            CONCAT(first_name,' ',last_name) LIKE %s
            AND verification_status='approved'
            AND is_deleted=0

        LIMIT 1
    """

    doctor = fetch_one(
        query,
        ("%" + name + "%",),
        database="human_care_doctors"
    )

    if doctor is None:

        return {
            "success": False,
            "message": "Doctor not found."
        }

    return {
        "success": True,
        "doctor": doctor
    }


# ============================================================
# Get Doctor by ID
# ============================================================

def get_doctor(doctor_id: int):

    logger.info("Loading doctor id=%s", doctor_id)

    query = """
        SELECT
            id,
            CONCAT(first_name,' ',last_name) AS doctor_name,
            specialty,
            qualification,
            experience_years,
            available_days,
            available_time

        FROM human_care_doctors.doctors

        WHERE
            id=%s
            AND verification_status='approved'
            AND is_deleted=0

        LIMIT 1
    """

    doctor = fetch_one(
        query,
        (doctor_id,),
        database="human_care_doctors"
    )

    if doctor is None:

        return {
            "success": False,
            "message": "Doctor not found."
        }

    return {
        "success": True,
        "doctor": doctor
    }


# ============================================================
# Check Doctor Exists
# ============================================================

def exists(doctor_id: int) -> bool:

    result = get_doctor(doctor_id)

    return result["success"]