"""Connexion Oracle et exécution SQL — sans logique métier SIG."""

from __future__ import annotations

import os
import re
from pathlib import Path

from fastapi import HTTPException

_ROOT = Path(__file__).resolve().parent

# .env Laravel (chargé une fois au premier import si besoin)
_env = _ROOT.parent.parent / ".env"


def _bootstrap_dotenv() -> None:
    if not _env.is_file():
        return
    try:
        from dotenv import load_dotenv
    except ImportError:
        load_dotenv = None
    if load_dotenv is not None:
        load_dotenv(_env)
        return
    for line in _env.read_text(encoding="utf-8", errors="replace").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, val = line.partition("=")
        key = key.strip()
        if not key or key in os.environ:
            continue
        val = val.strip()
        if (val.startswith('"') and val.endswith('"')) or (val.startswith("'") and val.endswith("'")):
            val = val[1:-1]
        os.environ[key] = val


_bootstrap_dotenv()


def get_env(name: str, default: str = "") -> str:
    v = os.getenv(name)
    if v is not None and v != "":
        return v
    alt = {
        "ORACLE_REPORT_GROUPE_HOST": "ORACLE_COFINA_HOST",
        "ORACLE_REPORT_GROUPE_PORT": "ORACLE_COFINA_PORT",
        "ORACLE_REPORT_GROUPE_SERVICE_NAME": "ORACLE_COFINA_SERVICE_NAME",
        "ORACLE_REPORT_GROUPE_USERNAME": "ORACLE_COFINA_USERNAME",
        "ORACLE_REPORT_GROUPE_PASSWORD": "ORACLE_COFINA_PASSWORD",
    }.get(name)
    if alt:
        return os.getenv(alt, default) or default
    return default


def dsn() -> str:
    host = get_env("ORACLE_REPORT_GROUPE_HOST")
    port = get_env("ORACLE_REPORT_GROUPE_PORT", "1522")
    service = get_env("ORACLE_REPORT_GROUPE_SERVICE_NAME")
    if not host or not service:
        return ""
    return f"{host}:{port}/{service}"


def read_sql_file(rel: str) -> str:
    if not rel or not rel.strip():
        return ""
    p = (_ROOT / rel.strip()).resolve()
    if not str(p).startswith(str(_ROOT.resolve())):
        return ""
    if p.is_file():
        return p.read_text(encoding="utf-8")
    return ""


def load_sql(*env_keys: str, default_file: str, file_env: str | None = None) -> str:
    for k in env_keys:
        v = os.getenv(k, "").strip()
        if v:
            return v
    if file_env:
        rel = os.getenv(file_env, "").strip()
        if rel:
            t = read_sql_file(rel)
            if t:
                return t
    return read_sql_file(default_file) or ""


def connect():
    import oracledb

    d = dsn()
    user = get_env("ORACLE_REPORT_GROUPE_USERNAME")
    password = get_env("ORACLE_REPORT_GROUPE_PASSWORD")
    if not d or not user:
        raise HTTPException(
            status_code=503,
            detail="Configuration Oracle incomplète (HOST, SERVICE_NAME, USERNAME).",
        )
    return oracledb.connect(user=user, password=password, dsn=d)


def set_current_schema_if_needed(cur) -> None:
    schema = os.getenv("ORACLE_CURRENT_SCHEMA", "").strip()
    if not schema:
        return
    if not re.fullmatch(r"[A-Za-z][A-Za-z0-9_]{0,127}", schema):
        return
    cur.execute(f"ALTER SESSION SET CURRENT_SCHEMA = {schema}")


def rows_as_dicts(cur) -> list[dict]:
    rows = cur.fetchall()
    if not rows:
        return []
    cols = [d[0].lower() for d in cur.description]
    return [dict(zip(cols, r)) for r in rows]


def fetchone_dict(cur) -> dict | None:
    row = cur.fetchone()
    if row is None:
        return None
    cols = [d[0].lower() for d in cur.description]
    return dict(zip(cols, row))


def execute(cur, sql: str, binds: dict | None = None) -> None:
    set_current_schema_if_needed(cur)
    if binds:
        cur.execute(sql, binds)
    else:
        cur.execute(sql)


def strip_sql(sql: str) -> str:
    return sql.strip().rstrip(";").strip()
