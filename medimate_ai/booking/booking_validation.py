"""
medimate_ai/booking/booking_validation.py

Booking Validation

Responsibilities
----------------
✓ Validate appointment date
✓ Validate appointment time
✓ Validate consultation type
✓ Validate reason
✓ Validate symptoms
✓ Save valid data
✓ Move to next booking step

Does NOT
--------
✗ Search doctors
✗ Check availability
✗ Book appointments
"""

import logging
from datetime import datetime, timedelta

from booking import booking_state
from booking.booking_questions import QUESTIONS

logger = logging.getLogger("medimate_ai.booking.validation")


STEP_ORDER = [
    "appointment_date",
    "appointment_time",
    "consultation_type",
    "reason",
    "symptoms",
]


def handle(user_id: int, message: str) -> str:

    state = booking_state.get(user_id)
    step = state["step"]

    value = message.strip()

    # ======================================================
    # Appointment Date
    # ======================================================

    if step == "appointment_date":

        try:
            appointment_date = datetime.strptime(
                value,
                "%Y-%m-%d"
            ).date()

        except ValueError:

            return (
                "❌ Invalid date format.\n\n"
                "Please enter the date like:\n"
                "2026-07-15"
            )

        today = datetime.today().date()

        if appointment_date < today:

            return (
                "❌ Appointment date cannot be in the past."
            )

        if appointment_date > today + timedelta(days=30):

            return (
                "❌ Appointments can only be booked within the next 30 days."
            )

    # ======================================================
    # Appointment Time
    # ======================================================

    elif step == "appointment_time":

        try:

            datetime.strptime(
                value,
                "%H:%M:%S"
            )

        except ValueError:

            return (
                "❌ Invalid time format.\n\n"
                "Example:\n"
                "10:30:00"
            )

    # ======================================================
    # Consultation Type
    # ======================================================

    elif step == "consultation_type":

        normalized = value.lower().replace("-", " ").strip()

        if normalized not in [
            "in person",
            "online",
        ]:

            return (
                "❌ Please choose:\n\n"
                "• In-Person\n"
                "• Online"
            )

        if normalized == "in person":
            value = "In-Person"
        else:
            value = "Online"

    # ======================================================
    # Reason
    # ======================================================

    elif step == "reason":

        if len(value) < 10:

            return (
                "❌ Please describe your health concern "
                "(minimum 10 characters)."
            )

        if len(value) > 500:

            return (
                "❌ Reason is too long "
                "(maximum 500 characters)."
            )

    # ======================================================
    # Symptoms
    # ======================================================

    elif step == "symptoms":

        if value.lower() == "no":
            value = ""

    # ======================================================
    # Save Current Field
    # ======================================================

    booking_state.save(
        user_id,
        step,
        value
    )

    # ======================================================
    # Move to Next Step
    # ======================================================

    current = STEP_ORDER.index(step)

    if current + 1 < len(STEP_ORDER):

        next_step = STEP_ORDER[current + 1]

        booking_state.set_step(
            user_id,
            next_step
        )

        return QUESTIONS[next_step]

    # ======================================================
    # Validation Complete
    # ======================================================

    booking_state.set_step(
        user_id,
        "booking_complete"
    )

    return (
        "✅ Thank you.\n\n"
        "Your appointment information has been collected.\n"
        "Now checking doctor availability..."
    )