"""
medimate_ai/tools/availability_tool.py

Availability Tool

Responsibilities
----------------
- Check doctor exists
- Check doctor schedule
- Check doctor availability
- Check patient conflicts

This tool DOES NOT book appointments.
"""

import logging
from datetime import datetime

from database import fetch_all, fetch_one

logger = logging.getLogger("medimate_ai.tools.availability_tool")


# ----------------------------------------------------------
# Doctor Schedule
# ----------------------------------------------------------

def check_doctor_schedule(
    doctor_id: int,
    appointment_date: datetime,
):

    weekday = appointment_date.strftime("%A")

    query = """
        SELECT
            day_of_week,
            start_time,
            end_time,
            is_available

        FROM doctor_schedule

        WHERE
            doctor_id=%s
            AND day_of_week=%s
            AND is_available=1

        LIMIT 1
    """

    row = fetch_one(
        query,
        (
            doctor_id,
            weekday
        ),
        database="human_care_doctors"
    )

    if row is None:

        return {
            "success": False,
            "message":
                f"The doctor is not available on {weekday}."
        }

    return {
        "success": True,
        "schedule": row
    }


# ----------------------------------------------------------
# Doctor Conflict
# ----------------------------------------------------------

def doctor_has_conflict(
    doctor_id: int,
    appointment_date: datetime,
):

    query = """
        SELECT id

        FROM doctor_appointments

        WHERE
            doctor_id=%s
            AND appointment_date=%s
            AND status IN (
                'scheduled',
                'pending'
            )

        LIMIT 1
    """

    row = fetch_one(
        query,
        (
            doctor_id,
            appointment_date
        ),
        database="human_care_doctors"
    )

    return row is not None


# ----------------------------------------------------------
# Patient Conflict
# ----------------------------------------------------------

def patient_has_conflict(
    patient_id: int,
    appointment_date: datetime,
):

    query = """
        SELECT id

        FROM patient_appointments

        WHERE
            patient_id=%s
            AND appointment_date=%s
            AND status IN (
                'scheduled',
                'pending'
            )

        LIMIT 1
    """

    row = fetch_one(
        query,
        (
            patient_id,
            appointment_date
        )
    )

    return row is not None


# ----------------------------------------------------------
# Validate Slot
# ----------------------------------------------------------

def validate_slot(
    patient_id: int,
    doctor_id: int,
    appointment_date: datetime,
):

    logger.info(
        "Checking availability..."
    )

    schedule = check_doctor_schedule(
        doctor_id,
        appointment_date
    )

    if not schedule["success"]:

        return schedule

    if doctor_has_conflict(
        doctor_id,
        appointment_date
    ):

        return {
            "success": False,
            "message":
                "The doctor already has an appointment at that time."
        }

    if patient_has_conflict(
        patient_id,
        appointment_date
    ):

        return {
            "success": False,
            "message":
                "You already have another appointment at that time."
        }

    return {
        "success": True
    }