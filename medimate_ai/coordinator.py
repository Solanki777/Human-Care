"""
medimate_ai/coordinator.py
Central coordinator for MediMate AI.

Responsibilities:
  1. Receive the user's message and their authenticated user_id.
  2. Ask Gemini ONLY to classify the intent (no data returned yet).
  3. Route to the correct tool based on intent.
  4. Pass the tool's raw data back to Gemini for natural-language formatting.
  5. Return the formatted reply.

Gemini never touches the database.
The coordinator never answers health questions directly.
"""

import os
import json
import logging
import re

import google.generativeai as genai
from tools import patient_tool
from tools import appointment_tool

logger = logging.getLogger("medimate_ai.coordinator")

# ---------------------------------------------------------------------------
# Gemini model (shared with main.py — configured once at startup)
# ---------------------------------------------------------------------------
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-2.5-flash")

# ---------------------------------------------------------------------------
# Supported intents for Phase 3 — Step 1 (Patient Tool only)
# ---------------------------------------------------------------------------
SUPPORTED_INTENTS = {
    "patient_profile",
    "patient_appointments",
    "patient_medical_history",

    "appointment_book",

    "general_health_question",
}

# ---------------------------------------------------------------------------
# Prompt: intent classification only
# ---------------------------------------------------------------------------
INTENT_PROMPT = """
You are an intent classifier for a hospital AI assistant.

The user has sent the following message:
"{message}"

Classify the intent into EXACTLY ONE of these options:

- patient_profile
- patient_appointments
- patient_medical_history
- appointment_book
- general_health_question

Rules:

- patient_profile
  User asks about their own profile, personal details or identity.

- patient_appointments
  User asks to view, list or check appointments.

- patient_medical_history
  User asks about diagnoses, conditions or medical history.

- appointment_book
  User wants to create a new appointment.
  Examples:
  • Book an appointment
  • Schedule an appointment
  • I need to see a doctor
  • Book appointment tomorrow
  • Can you book an appointment for me?
  • I want to consult a doctor

- general_health_question
  Symptoms, medicines, diseases, health advice or anything not matching the above.

Respond with ONLY a valid JSON object. No explanation. No markdown. No extra text.
Example: {{"intent": "patient_profile"}}
"""

# ---------------------------------------------------------------------------
# Prompt: format tool data into a natural reply
# ---------------------------------------------------------------------------
FORMAT_PROMPT = """
You are MediMate AI, the official AI assistant of Human Care Hospital.

The patient asked: "{message}"

Here is the data retrieved from the database:
{data}

Format this into a clear, friendly, and concise reply for the patient.

Rules:
- Use simple language.
- Never mention database, SQL, or technical details.
- Never reveal passwords, verification status, or admin fields.
- If the data contains a "message" key saying no records were found, tell the patient politely.
- Keep the tone warm and professional.
"""

# ---------------------------------------------------------------------------
# System prompt for general health questions (mirrors main.py)
# ---------------------------------------------------------------------------
HEALTH_SYSTEM_PROMPT = """
You are MediMate AI, the official AI assistant of Human Care Hospital.
Answer general healthcare questions clearly and concisely.
Never diagnose diseases, never prescribe medicines, and always recommend
consulting a healthcare professional for medical emergencies.
"""


# ---------------------------------------------------------------------------
# Core coordinator function
# ---------------------------------------------------------------------------

async def handle(message: str, user_id: int) -> str:
    """
    Process a user message end-to-end.

    Args:
        message: The raw text from the patient.
        user_id: The authenticated patient's ID (from PHP session).

    Returns:
        A natural-language reply string for the frontend.
    """
    logger.info("Coordinator received message for user_id=%s", user_id)

    # ── Step 1: Ask Gemini to classify intent ─────────────────────────────
    intent = await _classify_intent(message)
    logger.info("Detected intent: %s", intent)

    # ── Step 2: Route to the correct tool ─────────────────────────────────
    if intent == "patient_profile":
        data = patient_tool.get_profile(user_id)

    elif intent == "patient_appointments":
        data = appointment_tool.show(user_id)

    elif intent == "appointment_book":

        result = appointment_tool.book(
            user_id=user_id,
            doctor_id=1,
            appointment_date="2026-06-30 10:00:00",
            reason="Booked via MediMate AI"
        )

        return await _format_response(message, result)





    elif intent == "patient_medical_history":
        data = patient_tool.get_medical_history(user_id)

    elif intent == "general_health_question":
        # Let Gemini answer directly — no DB query needed
        return await _general_health_answer(message)

    else:
        # Unknown intent — fall back to general answer
        logger.warning("Unknown intent '%s', falling back to general answer.", intent)
        return await _general_health_answer(message)

    # ── Step 3: Ask Gemini to format the data into a natural reply ─────────
    return await _format_response(message, data)


# ---------------------------------------------------------------------------
# Private helpers
# ---------------------------------------------------------------------------

async def _classify_intent(message: str) -> str:
    """
    Send the message to Gemini and parse the returned JSON intent.
    Falls back to 'general_health_question' on any error.
    """
    prompt = INTENT_PROMPT.format(message=message)

    try:
        model = genai.GenerativeModel(model_name=GEMINI_MODEL)
        response = await model.generate_content_async(prompt)
        raw = response.text.strip()

        # Strip markdown code fences if Gemini adds them
        raw = re.sub(r"```(?:json)?|```", "", raw).strip()

        parsed = json.loads(raw)
        intent = parsed.get("intent", "general_health_question")

        if intent not in SUPPORTED_INTENTS:
            logger.warning("Gemini returned unsupported intent '%s'.", intent)
            return "general_health_question"

        return intent

    except (json.JSONDecodeError, AttributeError) as exc:
        logger.warning("Failed to parse intent JSON: %s — raw: %s", exc, raw if 'raw' in dir() else '?')
        return "general_health_question"

    except Exception as exc:
        logger.exception("Intent classification error: %s", exc)
        return "general_health_question"


async def _format_response(message: str, data: dict | list) -> str:
    """
    Ask Gemini to turn raw tool data into a friendly patient-facing reply.
    Falls back to a JSON dump if Gemini fails.
    """
    prompt = FORMAT_PROMPT.format(
        message=message,
        data=json.dumps(data, default=str, indent=2),
    )

    try:
        model = genai.GenerativeModel(model_name=GEMINI_MODEL)
        response = await model.generate_content_async(prompt)
        return response.text.strip()

    except Exception as exc:
        logger.exception("Response formatting error: %s", exc)
        # Safe fallback — never expose raw SQL or schema
        return "I found your information but had trouble formatting it. Please try again."


async def _general_health_answer(message: str) -> str:
    """
    Let Gemini answer a general health question directly (no DB query).
    """
    try:
        model = genai.GenerativeModel(
            model_name=GEMINI_MODEL,
            system_instruction=HEALTH_SYSTEM_PROMPT,
        )
        response = await model.generate_content_async(message)
        return response.text.strip()

    except Exception as exc:
        logger.exception("General health answer error: %s", exc)
        return "I'm sorry, I couldn't process that question. Please try again."