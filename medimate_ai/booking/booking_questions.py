"""
medimate_ai/booking/booking_questions.py

Shared booking constants.

Contains:
    • Questions shown to the patient
    • Booking step order

No business logic should be added here.
"""

# ------------------------------------------------------------------
# Questions
# ------------------------------------------------------------------

QUESTIONS = {

    "doctor_specialty":
        "Which specialist would you like to consult?\n\n"
        "Examples:\n"
        "• Cardiologist\n"
        "• Pediatrician\n"
        "• Neurologist\n"
        "• Dermatologist",

    "doctor_name":
        "Please choose one of the doctors above.\n\n"
        "You can type:\n"
        "• Doctor number (Example: 1)\n"
        "OR\n"
        "• Doctor name (Example: Rajesh Kumar)",

    "appointment_date":
        "What date would you prefer?\n\n"
        "Example: 2026-07-15\n"
        "(YYYY-MM-DD)\n\n"
        "Appointments can only be booked within the next 30 days.",

    "appointment_time":
        "What time would you prefer?\n\n"
        "Example: 10:30:00\n"
        "(24-hour format HH:MM:SS)",

    "consultation_type":
        "How would you like to consult?\n\n"
        "• In-Person\n"
        "• Online",

    "reason":
        "Please briefly describe your main health concern.\n\n"
        "(Minimum 10 characters)",

    "symptoms":
        "Do you have any additional symptoms?\n\n"
        "If none, simply reply:\n"
        "No",
}


# ------------------------------------------------------------------
# Booking Steps
# ------------------------------------------------------------------

BOOKING_STEPS = [

    "doctor_specialty",

    "doctor_name",

    "appointment_date",

    "appointment_time",

    "consultation_type",

    "reason",

    "symptoms",

    "booking_complete",

]