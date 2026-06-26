"""
medimate_ai/main.py
MediMate AI — FastAPI backend powered by Google Gemini.

Responsibilities:
  - Expose POST /chat endpoint
  - Forward validated messages + user_id to the Coordinator
  - Return structured JSON replies to chat.php

Run with:
  uvicorn main:app --host 127.0.0.1 --port 8000 --reload
"""

import os
import logging
from contextlib import asynccontextmanager
from typing import Optional

from dotenv import load_dotenv
from fastapi import FastAPI, HTTPException, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from pydantic import BaseModel, field_validator
import google.generativeai as genai

# ---------------------------------------------------------------------------
# Logging
# ---------------------------------------------------------------------------
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger("medimate_ai")

# ---------------------------------------------------------------------------
# Environment
# ---------------------------------------------------------------------------
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")

GEMINI_API_KEY: str = os.getenv("GEMINI_API_KEY", "")

if not GEMINI_API_KEY:
    logger.critical("GEMINI_API_KEY is not set.")
    raise RuntimeError("GEMINI_API_KEY environment variable is missing.")

genai.configure(api_key=GEMINI_API_KEY)
GEMINI_MODEL = os.getenv("GEMINI_MODEL", "gemini-2.5-flash")

# ---------------------------------------------------------------------------
# Import coordinator (after genai is configured)
# ---------------------------------------------------------------------------
import coordinator  # noqa: E402  (must come after genai.configure)

# ---------------------------------------------------------------------------
# Lifespan
# ---------------------------------------------------------------------------
@asynccontextmanager
async def lifespan(app: FastAPI):
    logger.info("MediMate AI starting — model: %s", GEMINI_MODEL)
    yield
    logger.info("MediMate AI shutting down.")


# ---------------------------------------------------------------------------
# FastAPI app
# ---------------------------------------------------------------------------
app = FastAPI(
    title="MediMate AI",
    description="Gemini-powered AI assistant for Human Care Hospital Management System.",
    version="1.1.0",
    lifespan=lifespan,
)

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
    user_id: Optional[int] = None   # PHP sends $_SESSION['user_id']; None = guest

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
# Global exception handler
# ---------------------------------------------------------------------------
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    logger.exception("Unhandled exception for %s: %s", request.url, exc)
    return JSONResponse(
        status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
        content={"reply": "An unexpected error occurred. Please try again later."},
    )


# ---------------------------------------------------------------------------
# Health check
# ---------------------------------------------------------------------------
@app.get("/health", tags=["Utility"])
async def health_check():
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
    Accepts a user message (and optional user_id), routes it through the
    Coordinator, and returns the AI-generated reply.

    Request body:
        { "message": "Show my appointments.", "user_id": 4 }

    Response body:
        { "reply": "Here are your upcoming appointments ..." }

    If user_id is absent or None, patient-specific intents will return a
    friendly prompt asking the user to log in.
    """
    user_message: str = request.message
    user_id: Optional[int] = request.user_id
    logger.info("Received message (%d chars) user_id=%s", len(user_message), user_id)

    try:
        # If user is not authenticated and tries a patient query, the
        # coordinator will detect the intent and patient_tool will find
        # no row for user_id=None → graceful empty response from Gemini.
        reply_text = await coordinator.handle(
            message=user_message,
            user_id=user_id,
        )

        if not reply_text:
            logger.warning("Coordinator returned an empty response.")
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail="The AI returned an empty response. Please try again.",
            )

        logger.info("Response ready (%d chars)", len(reply_text))
        return ChatResponse(reply=reply_text)

    except HTTPException:
        raise

    except genai.types.BlockedPromptException as exc:
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
        logger.exception("Unexpected error in /chat: %s", exc)
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="The AI service is temporarily unavailable. Please try again later.",
        ) from exc