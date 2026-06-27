"""
medimate_ai/booking/booking_flow.py

Booking Flow

Responsibilities
----------------
✓ Read completed booking information
✓ Check doctor availability
✓ Create appointment request
✓ Clear booking session
✓ Return final response

Does NOT
--------
✗ Ask questions
✗ Validate input
✗ Search doctors
"""

import logging

from booking import booking_state
from tools import availability_tool
from tools import appointment_tool

logger = logging.getLogger("medimate_ai.booking.flow")


def complete_booking(user_id: int) -> str:
    """
    Final booking step.
    """

    state = booking_state.get(user_id)

    logger.info(
        "Starting booking process for patient %s",
        user_id
    )

    # ----------------------------------------------------
    # Required Fields Check
    # ----------------------------------------------------

    required_fields = [
        "doctor_id",
        "appointment_date",
        "appointment_time",
        "consultation_type",
        "reason",
        "symptoms",
    ]

    missing = [
        field
        for field in required_fields
        if not state.get(field)
    ]

    if missing:

        logger.error(
            "Missing booking fields: %s",
            missing
        )

        booking_state.clear(user_id)

        return (
            "❌ Booking session became invalid.\n\n"
            "Please start again."
        )

    # ----------------------------------------------------
    # Check Availability
    # ----------------------------------------------------

    logger.info(
        "Checking doctor availability..."
    )

    availability = availability_tool.validate_slot(

        patient_id=user_id,

        doctor_id=state["doctor_id"],

        appointment_date=state["appointment_date"],

        appointment_time=state["appointment_time"]

    )

    if not availability["success"]:

        logger.warning(
            "Availability failed."
        )

        booking_state.clear(user_id)

        return availability["message"]

    # ----------------------------------------------------
    # Create Appointment
    # ----------------------------------------------------

    logger.info(
        "Creating appointment..."
    )

    result = appointment_tool.book(

        patient_id=user_id,

        doctor_id=state["doctor_id"],

        appointment_date=state["appointment_date"],

        appointment_time=state["appointment_time"],

        consultation_type=state["consultation_type"],

        reason=state["reason"],

        symptoms=state["symptoms"]

    )

    # ----------------------------------------------------
    # Success
    # ----------------------------------------------------

    if result["success"]:

        logger.info(
            "Appointment created successfully."
        )

        booking_state.clear(user_id)

        return (
            "🎉 " +
            result["message"]
        )

    # ----------------------------------------------------
    # Failure
    # ----------------------------------------------------

    logger.error(
        "Appointment creation failed."
    )

    booking_state.clear(user_id)

    return (
        "❌ " +
        result["message"]
    )