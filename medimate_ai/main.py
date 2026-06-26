"""
medimate_ai/main.py
MediMate AI — FastAPI backend powered by Google Gemini.

Responsibilities:
  - Expose POST /chat endpoint
  - Forward validated messages to Gemini
  - Return structured JSON replies to chat.php

Run with:
  uvicorn main:app --host 127.0.0.1 --port 8000 --reload
"""

import os
import logging
from contextlib import asynccontextmanager

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, field_validator
import google.generativeai as genai

# ---------------------------------------------------------------------------
# Logging — structured, goes to stdout so the OS / PM2 can capture it
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("medimate_ai")

# ---------------------------------------------------------------------------
# Load environment variables from .env (must live next to this file)
# ---------------------------------------------------------------------------
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")

GEMINI_API_KEY: str = os.getenv("GEMINI_API_KEY", "")

if not GEMINI_API_KEY:
    logger.critical(
        "GEMINI_API_KEY is not set. "
        "Add it to medimate_ai/.env before starting the server."
    )
    raise RuntimeError("GEMINI_API_KEY environment variable is missing.")

# ---------------------------------------------------------------------------
# Configure Gemini client (module-level, reused across requests)
# ---------------------------------------------------------------------------
genai.configure(api_key=GEMINI_API_KEY)

# Model name — update here if Google releases a newer Flash variant
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-2.5-flash")

# System prompt — shapes the assistant's persona and scope
SYSTEM_PROMPT = """
You are MediMate AI, the official AI assistant of Human Care Hospital.

Your responsibilities:

- Answer general healthcare questions.
- Explain symptoms in simple language.
- Provide medicine information.
- Help users understand hospital services.
- Help patients book appointments.
- Help patients find doctors.

Rules:

- Never diagnose diseases.
- Never prescribe medicines.
- Never replace a real doctor.
- Always recommend consulting a healthcare professional for medical emergencies.
- Keep answers concise, polite, and easy to understand.
"""

# ---------------------------------------------------------------------------
# Lifespan: initialise / tear-down resources around the app lifecycle
# ---------------------------------------------------------------------------
@asynccontextmanager
async def lifespan(app: FastAPI):
    """Startup and shutdown logic."""
    logger.info("MediMate AI starting — model: %s", GEMINI_MODEL)
    yield
    logger.info("MediMate AI shutting down.")


# ---------------------------------------------------------------------------
# FastAPI application instance
# ---------------------------------------------------------------------------
app = FastAPI(
    title="MediMate AI",
    description="Gemini-powered AI assistant for Human Care Hospital Management System.",
    version="1.0.0",
    lifespan=lifespan,
)

# ---------------------------------------------------------------------------
# CORS — allow requests from the PHP / Apache origin.
# Replace "*" with your actual domain in production for tighter security.
# ---------------------------------------------------------------------------
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],          # tighten in production
    allow_credentials=True,
    allow_methods=["POST", "OPTIONS"],
    allow_headers=["Content-Type", "Accept"],
)

# ---------------------------------------------------------------------------
# Pydantic models
# ---------------------------------------------------------------------------

class ChatRequest(BaseModel):
    """Incoming request body from chat.php."""

    message: str

    @field_validator("message")
    @classmethod
    def message_must_not_be_blank(cls, value: str) -> str:
        stripped = value.strip()
        if not stripped:
            raise ValueError("message cannot be empty or whitespace.")
        return stripped


class ChatResponse(BaseModel):
    """Outgoing response body returned to chat.php."""

    reply: str


# ---------------------------------------------------------------------------
# Global exception handler — catches anything not handled below
# ---------------------------------------------------------------------------
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.exception("Unhandled exception for %s: %s", request.url, exc)
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={"reply": "An unexpected error occurred. Please try again later."},
    )


# ---------------------------------------------------------------------------
# Health check — useful for load balancers / monitoring
# ---------------------------------------------------------------------------
@app.get("/health", tags=["Utility"])
async def health_check():
    """Returns 200 OK when the service is running."""
    return {"status": "ok", "model": GEMINI_MODEL}


# ---------------------------------------------------------------------------
# Main chat endpoint
# ---------------------------------------------------------------------------
@app.post(
    "/chat",
    response_model=ChatResponse,
    status_code=status.HTTP_200_OK,
    tags=["Chat"],
    summary="Send a message to MediMate AI",
)
async def chat(request: ChatRequest) -> ChatResponse:
    """
    Accepts a user message, sends it to Gemini with a medical-assistant
    system prompt, and returns the AI-generated reply.

    Request body:
        { "message": "What are the symptoms of diabetes?" }

    Response body:
        { "reply": "Common symptoms include ..." }
    """
    user_message: str = request.message
    logger.info("Received message (%d chars)", len(user_message))

    try:
        # Instantiate the model (lightweight — no network call yet)
        model = genai.GenerativeModel(
            model_name=GEMINI_MODEL,
            system_instruction=SYSTEM_PROMPT,
        )

        # Send the message to Gemini (network call happens here)
        response = await model.generate_content_async(user_message)

        # Extract text from the response
        reply_text: str = response.text.strip()

        if not reply_text:
            logger.warning("Gemini returned an empty response.")
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail="The AI returned an empty response. Please try again.",
            )

        logger.info("Gemini responded successfully (%d chars)", len(reply_text))
        return ChatResponse(reply=reply_text)

    except HTTPException:
        # Re-raise FastAPI HTTP exceptions unchanged
        raise

    except genai.types.BlockedPromptException as exc:
        # Gemini refused to answer — inform the user politely
        logger.warning("Gemini blocked the prompt: %s", exc)
        return ChatResponse(
            reply=(
                "I'm sorry, I'm unable to answer that question. "
                "Please ask something related to healthcare or hospital services."
            )
        )

    except genai.types.StopCandidateException as exc:
        logger.warning("Gemini stopped early: %s", exc)
        return ChatResponse(
            reply="I wasn't able to complete that response. Please try rephrasing your question."
        )

    except Exception as exc:
        # Catch-all for Gemini API errors (network, quota, auth …)
        logger.exception("Gemini API error: %s", exc)
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="The AI service is temporarily unavailable. Please try again later.",
        ) from exc