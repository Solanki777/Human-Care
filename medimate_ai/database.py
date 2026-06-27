"""
medimate_ai/database.py
Reusable MySQL connection manager for MediMate AI.

All tools import this module — never hardcode credentials here.
Credentials are loaded from .env via environment variables.
"""

import os
import logging
import mysql.connector
from mysql.connector import Error, MySQLConnection
from contextlib import contextmanager
from typing import Generator

logger = logging.getLogger("medimate_ai.database")


# ---------------------------------------------------------------------------
# Connection factory
# ---------------------------------------------------------------------------

def _get_connection_config(database: str | None = None) -> dict:
    """Build MySQL connection config from environment variables."""
    return {
        "host":     os.getenv("DB_HOST", "127.0.0.1"),
        "port":     int(os.getenv("DB_PORT", 3306)),
        "user":     os.getenv("DB_USER", ""),
        "password": os.getenv("DB_PASSWORD", ""),
        "database": database or os.getenv("DB_NAME", "human_care_patients"),
        "charset":  "utf8mb4",
        # Auto-reconnect if the connection drops between requests
        "autocommit": True,
    }


@contextmanager
def get_db(database: str | None = None) -> Generator[MySQLConnection, None, None]:
    """
    Context manager that yields a MySQL connection and closes it on exit.

    Usage:
        with get_db() as conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT ...")
    """
    conn: MySQLConnection | None = None
    try:
        conn = mysql.connector.connect(
    **_get_connection_config(database)
)
        logger.debug("DB connection opened.")
        yield conn
    except Error as exc:
        logger.exception("Failed to connect to MySQL: %s", exc)
        raise RuntimeError("Database connection failed.") from exc
    finally:
        if conn and conn.is_connected():
            conn.close()
            logger.debug("DB connection closed.")


def fetch_all(
    query: str,
    params: tuple = (),
    database: str | None = None,
) -> list[dict]:
    """
    Execute a SELECT query and return all rows as a list of dicts.

    Args:
        query:  Parameterized SQL string (use %s placeholders).
        params: Tuple of values bound to the placeholders.

    Returns:
        List of row dicts (column name → value).
    """
    with get_db(database) as conn:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params)
        rows = cursor.fetchall()
        cursor.close()
        return rows


def fetch_one(
    query: str,
    params: tuple = (),
    database: str | None = None,
) -> dict | None:
    """
    Execute a SELECT query and return the first row as a dict, or None.

    Args:
        query:  Parameterized SQL string.
        params: Tuple of bound values.

    Returns:
        Single row dict or None if no match.
    """
    with get_db(database) as conn:
        cursor = conn.cursor(dictionary=True)
        cursor.execute(query, params)
        row = cursor.fetchone()
        cursor.close()
        return row
    
def execute(
    query: str,
    params: tuple = (),
    database: str | None = None,
) -> int:
    """
    Execute an INSERT, UPDATE or DELETE query.

    Args:
        query: SQL query with %s placeholders.
        params: Values for the placeholders.
        database: Optional database name.

    Returns:
        Last inserted ID if available, otherwise 0.
    """

    with get_db(database) as conn:
        cursor = conn.cursor()

        cursor.execute(query, params)

        # Save changes
        conn.commit()

        last_id = cursor.lastrowid

        cursor.close()

        return last_id