"""
medimate_ai/booking/booking_manager.py

Booking Manager

Responsibilities
----------------
- Entry point for the booking conversation.
- Decide which booking module should handle the current step.
- Never performs validation or database operations itself.

Flow
----
User
    ↓
Booking Manager
    ↓
Doctor Selection
    ↓
Booking Validation
    ↓
Booking Flow
"""

import logging

import conversation_state

from booking import doctor_selection
from booking import booking_validation
from booking import booking_flow
from booking.booking_questions import QUESTIONS

logger = logging.getLogger("medimate_ai.booking_manager")


def handle_booking(user_id: int, message: str) -> str:
    """
    Main entry point for appointment booking.
    """

    state = conversation_state.get_state(user_id)
    step = state.get("step")

    logger.info("Booking Step = %s", step)

    # ---------------------------------------------------------
    # First interaction
    # ---------------------------------------------------------

    if not step:

        conversation_state.create_booking_session(user_id)

        state = conversation_state.get_state(user_id)
        step = state["step"]

        return QUESTIONS[step]

    # ---------------------------------------------------------
    # Doctor selection
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

    return booking_flow.complete_booking(user_id)