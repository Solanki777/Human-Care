"""
medimate_ai/coordinator.py

Central coordinator for MediMate AI.

Responsibilities
----------------
1. Receive patient message.
2. Detect user intent using Gemini.
3. Route request to the correct tool.
4. Continue booking conversations.
5. Format tool responses.
"""

import os
import json
import logging
import re

import google.generativeai as genai

import conversation_state

from booking import booking_state
from booking.booking_manager import handle_booking

from tools import patient_tool
from tools import appointment_tool

logger = logging.getLogger("medimate_ai.coordinator")


# ---------------------------------------------------------
# Gemini Model
# ---------------------------------------------------------

GEMINI_MODEL = os.getenv(
    "GEMINI_MODEL",
    "gemini-2.5-flash"
)


# ---------------------------------------------------------
# Supported Intents
# ---------------------------------------------------------

SUPPORTED_INTENTS = {

    "patient_profile",

    "patient_appointments",

    "patient_medical_history",

    "appointment_book",

    "general_health_question",

}


# ---------------------------------------------------------
# Intent Classification Prompt
# ---------------------------------------------------------

INTENT_PROMPT = """
You are an intent classifier for Human Care Hospital.

Message:
"{message}"

Return ONLY JSON.

Possible intents:

patient_profile
patient_appointments
patient_medical_history
appointment_book
general_health_question

Examples

Book an appointment
Schedule appointment
I need a doctor
Consult a cardiologist
→ appointment_book

Who am I?
My profile
My details
→ patient_profile

Show appointments
Upcoming appointments
My bookings
→ patient_appointments

Medical history
Previous diagnosis
My conditions
→ patient_medical_history

Everything else
→ general_health_question

Example response:

{{"intent":"appointment_book"}}
"""


# ---------------------------------------------------------
# Response Formatter Prompt
# ---------------------------------------------------------

FORMAT_PROMPT = """
You are MediMate AI.

Patient Message:

{message}

Database Result:

{data}

Create a friendly patient response.

Never mention SQL.
Never mention database.
Never expose passwords.
Never expose admin information.
"""


# ---------------------------------------------------------
# Health Assistant Prompt
# ---------------------------------------------------------

HEALTH_SYSTEM_PROMPT = """
You are MediMate AI.

Answer health questions briefly.

Never diagnose diseases.

Never prescribe medicines.

Always recommend visiting a doctor when appropriate.
"""

# ---------------------------------------------------------
# Main Coordinator
# ---------------------------------------------------------

async def handle(
    message: str,
    user_id: int
) -> str:
    """
    Main request router.
    """

    logger.info(
        "Coordinator received message for user_id=%s",
        user_id
    )

    # -------------------------------------------------
    # Continue Existing Booking Conversation
    # -------------------------------------------------

    if conversation_state.has_session(user_id):

        logger.info(
            "Continuing existing booking conversation."
        )

        return handle_booking(
            user_id=user_id,
            message=message
        )

    # -------------------------------------------------
    # Detect Intent
    # -------------------------------------------------

    intent = await _classify_intent(message)

    logger.info(
        "Detected intent: %s",
        intent
    )

    # -------------------------------------------------
    # Patient Profile
    # -------------------------------------------------

    if intent == "patient_profile":

        data = patient_tool.get_profile(
            user_id
        )

        return await _format_response(
            message,
            data
        )

    # -------------------------------------------------
    # Patient Appointments
    # -------------------------------------------------

    elif intent == "patient_appointments":

        data = patient_tool.get_appointments(
            user_id
        )

        return await _format_response(
            message,
            data
        )

    # -------------------------------------------------
    # Patient Medical History
    # -------------------------------------------------

    elif intent == "patient_medical_history":

        data = patient_tool.get_medical_history(
            user_id
        )

        return await _format_response(
            message,
            data
        )

    # -------------------------------------------------
    # Appointment Booking
    # -------------------------------------------------


    elif intent == "appointment_book":
        
        logger.info(
            "Starting new booking session."
        )

        booking_state.create(user_id)

        return handle_booking(
            user_id=user_id,
            message=""
        )

    # -------------------------------------------------
    # General Health Question
    # -------------------------------------------------

    elif intent == "general_health_question":

        return await _general_health_answer(
            message
        )

    # -------------------------------------------------
    # Unknown Intent
    # -------------------------------------------------

    logger.warning(
        "Unknown intent: %s",
        intent
    )

    return await _general_health_answer(
        message
    )


# ---------------------------------------------------------
# Intent Classifier
# ---------------------------------------------------------

async def _classify_intent(
    message: str
) -> str:

    prompt = INTENT_PROMPT.format(
        message=message
    )

    try:

        model = genai.GenerativeModel(
            model_name=GEMINI_MODEL
        )

        response = await model.generate_content_async(
            prompt
        )

        raw = response.text.strip()

        raw = re.sub(
            r"```(?:json)?|```",
            "",
            raw
        ).strip()

        result = json.loads(raw)

        intent = result.get(
            "intent",
            "general_health_question"
        )

        if intent not in SUPPORTED_INTENTS:

            logger.warning(
                "Unsupported intent: %s",
                intent
            )

            return "general_health_question"

        return intent

    except Exception as e:

        logger.exception(
            "Intent classification failed: %s",
            e
        )

        return "general_health_question"


# ---------------------------------------------------------
# Response Formatter
# ---------------------------------------------------------

async def _format_response(
    message: str,
    data: dict | list
) -> str:

    prompt = FORMAT_PROMPT.format(

        message=message,

        data=json.dumps(
            data,
            indent=2,
            default=str
        )

    )

    try:

        model = genai.GenerativeModel(
            model_name=GEMINI_MODEL
        )

        response = await model.generate_content_async(
            prompt
        )

        return response.text.strip()

    except Exception as e:

        logger.exception(
            "Formatter failed: %s",
            e
        )

        if isinstance(data, dict):

            return data.get(
                "message",
                "Unable to process your request."
            )

        return "Unable to process your request."


# ---------------------------------------------------------
# General Health Assistant
# ---------------------------------------------------------

async def _general_health_answer(
    message: str
) -> str:

    try:

        model = genai.GenerativeModel(

            model_name=GEMINI_MODEL,

            system_instruction=HEALTH_SYSTEM_PROMPT

        )

        response = await model.generate_content_async(
            message
        )

        return response.text.strip()

    except Exception as e:

        logger.exception(
            "Health assistant failed: %s",
            e
        )

        return (
            "I'm sorry, I couldn't answer your question right now."
        )