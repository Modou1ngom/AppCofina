"""
Proxy HTTP minimal pour REPORT_GROUPE : Laravel appelle ce service, Python se connecte à Oracle (oracledb).

Fichiers SQL (bind : matricule) : sql/kyc_by_customer.sql, sql/encours_client.sql

Variables d'environnement :
  ORACLE_REPORT_GROUPE_HOST, PORT, SERVICE_NAME, USERNAME, PASSWORD
  ORACLE_CURRENT_SCHEMA — ex. CFSFCUBS145 (exécute ALTER SESSION)
  ORACLE_REPORT_GROUPE_LOOKUP_PERSONNEL_SQL | ORACLE_LOOKUP_PERSONNEL_SQL — texte SQL, ou
  ORACLE_LOOKUP_PERSONNEL_SQL_FILE — chemin relatif à ce dossier, défaut sql/kyc_by_customer.sql
  ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL, ORACLE_STAFF_LIEES_SQL, ORACLE_STAFF_LIEES_SQL_FILE
  ORACLE_ENCOURS_SQL, ORACLE_ENCOURS_SQL_FILE (optionnel) — 2e requête, fusionnée si une ligne
"""

from __future__ import annotations

import logging
import re
import os
from decimal import Decimal
from pathlib import Path

_logger = logging.getLogger("oracle_proxy")

try:
    from dotenv import load_dotenv
except ImportError:
    load_dotenv = None  # type: ignore[misc, assignment]

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

# Charge le .env du projet Laravel (deux niveaux au-dessus)
_env = Path(__file__).resolve().parent.parent.parent / ".env"
if _env.is_file():
    if load_dotenv is not None:
        load_dotenv(_env)
    else:
        # Sans python-dotenv : lecture minimale des lignes KEY=VALUE
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

_ROOT = Path(__file__).resolve().parent


def _get(name: str, default: str = "") -> str:
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


def _dsn() -> str:
    host = _get("ORACLE_REPORT_GROUPE_HOST")
    port = _get("ORACLE_REPORT_GROUPE_PORT", "1521")
    service = _get("ORACLE_REPORT_GROUPE_SERVICE_NAME")
    if not host or not service:
        return ""
    return f"{host}:{port}/{service}"


def _read_sql_file(rel: str) -> str:
    """Relatif au dossier du proxy (scripts/oracle-proxy-python)."""
    if not rel or not rel.strip():
        return ""
    p = (_ROOT / rel.strip()).resolve()
    if not str(p).startswith(str(_ROOT.resolve())):
        return ""
    if p.is_file():
        return p.read_text(encoding="utf-8")
    return ""


def _load_lookup_sql() -> str:
    for k in (
        "ORACLE_REPORT_GROUPE_LOOKUP_PERSONNEL_SQL",
        "ORACLE_LOOKUP_PERSONNEL_SQL",
    ):
        v = os.getenv(k, "").strip()
        if v:
            return v
    rel = os.getenv("ORACLE_LOOKUP_PERSONNEL_SQL_FILE", "").strip()
    if rel:
        t = _read_sql_file(rel)
        if t:
            return t
    t = _read_sql_file("sql/kyc_by_customer.sql")
    return t or ""


def _load_encours_sql() -> str:
    for k in (
        "ORACLE_ENCOURS_SQL",
        "ORACLE_REPORT_GROUPE_ENCOURS_SQL",
    ):
        v = os.getenv(k, "").strip()
        if v:
            return v
    rel = os.getenv("ORACLE_ENCOURS_SQL_FILE", "").strip()
    if rel:
        t = _read_sql_file(rel)
        if t:
            return t
    t = _read_sql_file("sql/encours_client.sql")
    return t or ""


def _load_staff_liees_sql() -> str:
    for k in (
        "ORACLE_STAFF_LIEES_SQL",
        "ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL",
    ):
        v = os.getenv(k, "").strip()
        if v:
            return v
    rel = os.getenv("ORACLE_STAFF_LIEES_SQL_FILE", "").strip()
    if rel:
        return _read_sql_file(rel)
    return ""


def _set_current_schema_if_needed(cur) -> None:
    schema = os.getenv("ORACLE_CURRENT_SCHEMA", "").strip()
    if not schema:
        return
    if not re.fullmatch(r"[A-Za-z][A-Za-z0-9_]{0,127}", schema):
        return
    cur.execute(f"ALTER SESSION SET CURRENT_SCHEMA = {schema}")


def _connect():
    import oracledb

    dsn = _dsn()
    user = _get("ORACLE_REPORT_GROUPE_USERNAME")
    password = _get("ORACLE_REPORT_GROUPE_PASSWORD")
    if not dsn or not user:
        raise HTTPException(
            status_code=503,
            detail="Configuration Oracle incomplète (HOST, SERVICE_NAME, USERNAME).",
        )
    return oracledb.connect(user=user, password=password, dsn=dsn)


def _row_to_dict(cur) -> dict | None:
    row = cur.fetchone()
    if row is None:
        return None
    cols = [d[0].lower() for d in cur.description]
    return dict(zip(cols, row))


def _to_float(x) -> float | None:
    if x is None:
        return None
    if isinstance(x, Decimal):
        return float(x)
    try:
        return float(x)
    except (TypeError, ValueError):
        return None


def _merge_encours(data: dict, enc: dict) -> None:
    et = _to_float(enc.get("encours_total"))
    if et is None:
        for k in ("encours", "total_encours", "encours_balance", "sum_encours"):
            et = _to_float(enc.get(k))
            if et is not None:
                break
    if et is not None:
        data["encours_total"] = et
    if enc.get("value_date"):
        data["value_date"] = enc.get("value_date")
    if enc.get("matricule_client") is not None:
        data["matricule_client"] = str(enc.get("matricule_client"))


app = FastAPI(title="AppCofina Oracle proxy", version="1.0.0")


class LookupBody(BaseModel):
    matricule: str


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/api/sig/lookup-personnel")
def lookup_personnel(body: LookupBody):
    matricule = (body.matricule or "").strip()
    if not matricule:
        raise HTTPException(status_code=422, detail="matricule requis")

    sql = _load_lookup_sql()
    if not sql:
        return {
            "ok": False,
            "data": None,
            "message": "Aucun SQL fiche (env ou fichier sql/kyc_by_customer.sql).",
        }

    encours_sql = _load_encours_sql()

    try:
        conn = _connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            _set_current_schema_if_needed(cur)
            cur.execute(sql, {"matricule": matricule})
            data = _row_to_dict(cur)
            if data is None:
                return {"ok": True, "data": None}

            if encours_sql.strip():
                try:
                    _set_current_schema_if_needed(cur)
                    cur.execute(encours_sql, {"matricule": matricule})
                    enc = _row_to_dict(cur)
                    if enc:
                        _merge_encours(data, enc)
                except Exception as e:
                    _logger.warning("Fusion encours ignorée pour matricule=%s: %s", matricule, e, exc_info=True)
            return {"ok": True, "data": data}
    finally:
        conn.close()


@app.get("/api/sig/staff/{matricule}/personnes-liees")
def personnes_liees(matricule: str):
    m = (matricule or "").strip()
    if not m:
        raise HTTPException(status_code=422, detail="matricule requis")

    sql = _load_staff_liees_sql()

    if not sql:
        return {"ok": True, "data": [], "message": "Définissez ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL (personnes liées)."}

    try:
        conn = _connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            _set_current_schema_if_needed(cur)
            cur.execute(sql, {"matricule": m})
            rows = cur.fetchall()
            if not rows:
                return {"ok": True, "data": []}
            cols = [d[0].lower() for d in cur.description]
            return {"ok": True, "data": [dict(zip(cols, r)) for r in rows]}
    finally:
        conn.close()
