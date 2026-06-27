"""
medimate_ai/conversation_state.py

Temporary booking conversation memory.

This module stores the current booking session for each
logged-in patient.

The session is cleared once the booking finishes or is cancelled.

NOTE:
This is NOT chat history.
"""

import logging
from booking.booking_questions import BOOKING_STEPS

logger = logging.getLogger("medimate_ai.conversation_state")

# ------------------------------------------------------------
# Temporary Session Store
# ------------------------------------------------------------

_sessions: dict[int, dict] = {}


# ------------------------------------------------------------
# Default Booking State
# ------------------------------------------------------------

def _default_state() -> dict:

    return {

        # Booking Progress
        "step": BOOKING_STEPS[0],

        # Doctor Information
        "doctor_specialty": None,
        "doctor_id": None,
        "doctor_name": None,
        "doctor_list": [],

        # Appointment Information
        "appointment_date": None,
        "appointment_time": None,

        # Consultation
        "consultation_type": None,

        # Medical Information
        "reason": None,
        "symptoms": None,

    }


# ------------------------------------------------------------
# Get State
# ------------------------------------------------------------

def get_state(user_id: int) -> dict | None:
    """
    Return the booking state if it exists.
    Does NOT create a new session.
    """
    return _sessions.get(user_id)

# ------------------------------------------------------------
# Create Booking Session
# ------------------------------------------------------------

def create_booking_session(user_id: int) -> dict:
    """
    Create a booking session only if one does not already exist.
    """

    if user_id not in _sessions:

        _sessions[user_id] = _default_state()

        logger.info(
            "Created booking session for patient %s",
            user_id
        )

    return _sessions[user_id]


# ------------------------------------------------------------
# Check Session
# ------------------------------------------------------------

def has_session(user_id: int) -> bool:

    return user_id in _sessions
# ------------------------------------------------------------
# Update One Field
# ------------------------------------------------------------

def update_state(
    user_id: int,
    field: str,
    value,
):

    state = get_state(user_id)

    if state is None:
        raise RuntimeError("Booking session does not exist.")

    state[field] = value

    logger.info(
        "Updated %s = %s for patient %s",
        field,
        value,
        user_id
    )
# ------------------------------------------------------------
# Change Conversation Step
# ------------------------------------------------------------

def set_step(
    user_id: int,
    step: str,
):
    state = get_state(user_id)

    if state is None:
        raise RuntimeError("Booking session does not exist.")

    state["step"] = step
    logger.info(
    "Patient %s moved to step %s",
    user_id,
    step
)


# ------------------------------------------------------------
# Current Step
# ------------------------------------------------------------

def current_step(
    user_id: int,
):

    state = get_state(user_id)

    if state is None:
        return None

    return state.get("step")

# ------------------------------------------------------------
# Clear Booking
# ------------------------------------------------------------

def clear_state(user_id: int):

    if user_id in _sessions:

        del _sessions[user_id]

        logger.info(
            "Booking session cleared for %s",
            user_id
        )