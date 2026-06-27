"""
medimate_ai/booking/doctor_selection.py

Doctor Selection

Responsibilities
----------------
✓ Search doctors by specialty
✓ Display matching doctors
✓ Allow patient to select a doctor
✓ Save selected doctor into booking state

Does NOT
--------
✗ Validate dates
✗ Create appointments
✗ Call Appointment Tool
"""

import logging

from booking import booking_state
from booking.booking_questions import QUESTIONS
from tools import doctor_tool

logger = logging.getLogger("medimate_ai.booking.doctor_selection")


def handle(user_id: int, message: str) -> str:
    """
    Handles:
        doctor_specialty
        doctor_name
    """

    state = booking_state.get(user_id)
    step = state["step"]

    # ============================================================
    # STEP 1 : Doctor Specialty
    # ============================================================

    if step == "doctor_specialty":

        # First entry into booking:
        # If no specialty has been provided yet,
        # ask the question instead of treating the trigger message
        # as a specialty.

        if state.get("doctor_specialty") is None:

            booking_triggers = {
                "book appointment",
                "book an appointment",
                "i have to book an appointment",
                "i need an appointment",
                "schedule appointment",
                "schedule an appointment",
                "i'd like to book an appointment",
            }

            if message.strip().lower() in booking_triggers:
                return QUESTIONS["doctor_specialty"]

        specialty = message.strip()

        booking_state.save(
            user_id,
            "doctor_specialty",
            specialty
        )

        doctors = doctor_tool.find_by_specialty(
            specialty
        )
        if not doctors["success"]:
            return doctors["message"]

        # Save doctor list temporarily
        booking_state.save(
            user_id,
            "doctor_list",
            doctors["doctors"]
        )

        booking_state.set_step(
            user_id,
            "doctor_name"
        )

        reply = (
            f"I found {doctors['count']} approved doctor(s).\n\n"
        )

        for index, doctor in enumerate(
            doctors["doctors"],
            start=1
        ):

            reply += (
                f"{index}. {doctor['doctor_name']}\n"
                f"   • {doctor['specialty']}\n"
                f"   • {doctor['experience_years']} years experience\n\n"
            )

        reply += (
            "Please type:\n"
            "• Doctor number\n"
            "OR\n"
            "• Doctor name"
        )

        return reply

    # ============================================================
    # STEP 2 : Doctor Selection
    # ============================================================

    doctors = state.get("doctor_list", [])

    if not doctors:

        booking_state.set_step(
            user_id,
            "doctor_specialty"
        )

        return QUESTIONS["doctor_specialty"]

    text = message.strip()

    selected = None

    # ----------------------------------------
    # Select by Number
    # ----------------------------------------

    if text.isdigit():

        index = int(text)

        if 1 <= index <= len(doctors):

            selected = doctors[index - 1]

    # ----------------------------------------
    # Select by Name
    # ----------------------------------------

    if selected is None:

        clean = (
            text.replace(".", "")
                .replace(",", "")
                .strip()
                .lower()
        )

        for doctor in doctors:

            if clean in doctor["doctor_name"].lower():

                selected = doctor
                break

    # ----------------------------------------
    # Invalid Doctor
    # ----------------------------------------

    if selected is None:

        return (
            "I couldn't find that doctor.\n\n"
            "Please enter one of the doctor names "
            "or the doctor number."
        )

    # ----------------------------------------
    # Save Selection
    # ----------------------------------------

    booking_state.save(
        user_id,
        "doctor_id",
        selected["id"]
    )

    booking_state.save(
        user_id,
        "doctor_name",
        selected["doctor_name"]
    )

    booking_state.set_step(
        user_id,
        "appointment_date"
    )

    return QUESTIONS["appointment_date"]