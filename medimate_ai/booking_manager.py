"""
medimate_ai/booking/booking_manager.py

Booking Manager

Responsibilities
----------------
- Main entry point for booking.
- Route booking conversation to the correct module.
- Never perform validation or database operations itself.
"""

import logging

from booking import booking_state
from booking import doctor_selection
from booking import booking_validation
from booking import booking_flow

logger = logging.getLogger("medimate_ai.booking.manager")


def handle_booking(user_id: int, message: str) -> str:
    """
    Main booking router.
    """

    # ---------------------------------------------------------
    # Get/Create Booking Session
    # ---------------------------------------------------------

    state = booking_state.create(user_id)

    step = booking_state.current_step(user_id)

    logger.info(
        "Current Booking Step: %s",
        step
    )

    # ---------------------------------------------------------
    # Doctor Selection
    # ---------------------------------------------------------

    if step in [
        "doctor_specialty",
        "doctor_name",
    ]:

        return doctor_selection.handle(
            user_id=user_id,
            message=message,
        )

    # ---------------------------------------------------------
    # Validation Steps
    # ---------------------------------------------------------

    if step in [
        "appointment_date",
        "appointment_time",
        "consultation_type",
        "reason",
        "symptoms",
    ]:

        return booking_validation.handle(
            user_id=user_id,
            message=message,
        )

    # ---------------------------------------------------------
    # Booking Complete
    # ---------------------------------------------------------

    if step == "booking_complete":

        return booking_flow.complete_booking(
            user_id
        )

    # ---------------------------------------------------------
    # Unknown State
    # ---------------------------------------------------------

    logger.warning(
        "Unknown booking step: %s",
        step
    )

    booking_state.clear(user_id)

    return (
        "Something went wrong while processing your booking.\n\n"
        "Please start again by typing:\n"
        "\"Book Appointment\""
    )