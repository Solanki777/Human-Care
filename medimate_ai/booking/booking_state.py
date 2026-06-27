"""
medimate_ai/booking/booking_state.py

Booking State Helper

Responsibilities
----------------
- Wrap conversation_state functions.
- Provide booking-specific helper methods.
- Keep booking modules independent of conversation_state.

This file DOES NOT contain business logic.
"""

import logging
import conversation_state

logger = logging.getLogger("medimate_ai.booking_state")


# ---------------------------------------------------------
# Session
# ---------------------------------------------------------

def get(user_id: int) -> dict:
    """
    Get current booking state.
    """
    return conversation_state.get_state(user_id)


def clear(user_id: int) -> None:
    """
    Clear booking conversation.
    """
    logger.info("Clearing booking state for patient %s", user_id)
    conversation_state.clear_state(user_id)


# ---------------------------------------------------------
# Step Management
# ---------------------------------------------------------

def current_step(user_id: int):
    """
    Return current booking step.
    """
    state = get(user_id)
    return state.get("step")


def set_step(user_id: int, step: str) -> None:
    """
    Move booking to next step.
    """
    logger.info("Booking step -> %s", step)
    conversation_state.set_step(user_id, step)


# ---------------------------------------------------------
# Data
# ---------------------------------------------------------

def save(user_id: int, field: str, value):
    """
    Save one booking field.
    """
    logger.info(
        "Saving booking field %s = %s",
        field,
        value
    )

    conversation_state.update_state(
        user_id,
        field,
        value
    )


def load(user_id: int, field: str, default=None):
    """
    Get a saved booking field.
    """
    state = get(user_id)
    return state.get(field, default)


# ---------------------------------------------------------
# Booking Session
# ---------------------------------------------------------

def create(user_id: int):

    if not conversation_state.has_session(user_id):

        conversation_state.create_booking_session(user_id)

    return conversation_state.get_state(user_id)