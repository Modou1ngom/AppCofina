"""
Logique métier SIG : filtres, jointures logiques et mapping API.
Les fichiers sql/*.sql restent des SELECT bruts sur les tables SN_*.
"""

from __future__ import annotations

import logging
from decimal import Decimal

from fastapi import HTTPException

import oracle_db as db

_logger = logging.getLogger("oracle_proxy")


def _to_float(x) -> float | None:
    if x is None:
        return None
    if isinstance(x, Decimal):
        return float(x)
    try:
        return float(x)
    except (TypeError, ValueError):
        return None


def _s(v) -> str:
    if v is None:
        return ""
    return str(v).strip()


def customer_from_account(cust_ac_no: str) -> str:
    """CUST_AC_NO → n° client (positions 4–9)."""
    ac = _s(cust_ac_no)
    if len(ac) >= 9:
        return ac[3:9]
    return ac


def load_kyc_sql() -> str:
    return db.load_sql(
        "ORACLE_REPORT_GROUPE_LOOKUP_PERSONNEL_SQL",
        "ORACLE_LOOKUP_PERSONNEL_SQL",
        default_file="sql/kyc_by_customer.sql",
        file_env="ORACLE_LOOKUP_PERSONNEL_SQL_FILE",
    )


def load_encours_sql() -> str:
    return db.load_sql(
        "ORACLE_ENCOURS_SQL",
        "ORACLE_REPORT_GROUPE_ENCOURS_SQL",
        default_file="sql/encours_client.sql",
        file_env="ORACLE_ENCOURS_SQL_FILE",
    )


def load_detection_sql() -> str:
    return db.load_sql(
        "ORACLE_DETECTION_STAFF_CLIENTS_SQL",
        "ORACLE_REPORT_GROUPE_DETECTION_STAFF_CLIENTS_SQL",
        default_file="sql/detection_staff_clients.sql",
        file_env="ORACLE_DETECTION_STAFF_CLIENTS_SQL_FILE",
    )


def load_staff_liees_sql() -> str:
    # Même source que détection si fichier dédié absent / identique
    sql = db.load_sql(
        "ORACLE_STAFF_LIEES_SQL",
        "ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL",
        default_file="sql/staff_clients_liees.sql",
        file_env="ORACLE_STAFF_LIEES_SQL_FILE",
    )
    return sql or load_detection_sql()


def load_alertes_sql() -> str:
    return db.load_sql(
        "ORACLE_ALERTES_DOUBLONS_CLIENTS_SQL",
        "ORACLE_REPORT_GROUPE_ALERTES_DOUBLONS_CLIENTS_SQL",
        default_file="sql/alertes_doublons_clients.sql",
        file_env="ORACLE_ALERTES_DOUBLONS_CLIENTS_SQL_FILE",
    )


def _kyc_by_matricule(cur, matricule: str) -> dict | None:
    base = db.strip_sql(load_kyc_sql())
    if not base:
        return None
    sql = f"""
    SELECT * FROM (
        {base}
    ) q
    WHERE TRIM(CUSTOMER_NO) = TRIM(:matricule)
       OR TRIM(NUMERO_PIECE) = TRIM(:matricule)
    """
    db.execute(cur, sql, {"matricule": matricule})
    row = db.fetchone_dict(cur)
    if not row:
        return None
    # Alias attendus par Laravel
    if row.get("type_piece") and not row.get("unique_id_name"):
        row["unique_id_name"] = row["type_piece"]
    if row.get("numero_piece") and not row.get("unique_id_value"):
        row["unique_id_value"] = row["numero_piece"]
    return row


def _encours_by_matricule(cur, matricule: str) -> dict | None:
    base = db.strip_sql(load_encours_sql())
    if not base:
        return None
    sql = f"""
    SELECT * FROM (
        {base}
    ) q
    WHERE TRIM(CUSTOMER_ID) = TRIM(:matricule)
    """
    db.execute(cur, sql, {"matricule": matricule})
    enc = db.fetchone_dict(cur)
    if not enc:
        return None
    return {
        "matricule_client": _s(enc.get("customer_id")) or None,
        "primary_applicant_name": enc.get("primary_applicant_name"),
        "encours_total": _to_float(enc.get("encours_total_m")),
        "encours_total_m": _to_float(enc.get("encours_total_m")),
        "encours_sain": _to_float(enc.get("encours_sain_m")),
        "encours_sain_m": _to_float(enc.get("encours_sain_m")),
        "encours_impaye": _to_float(enc.get("encours_impaye_m")),
        "encours_impaye_m": _to_float(enc.get("encours_impaye_m")),
    }


def merge_encours(data: dict, enc: dict) -> None:
    et = _to_float(enc.get("encours_total"))
    if et is None:
        et = _to_float(enc.get("encours_total_m"))
    if et is not None:
        data["encours_total"] = et
    for src, dst in (
        ("encours_sain", "encours_sain"),
        ("encours_sain_m", "encours_sain"),
        ("encours_impaye", "encours_impaye"),
        ("encours_impaye_m", "encours_impaye"),
    ):
        val = _to_float(enc.get(src))
        if val is not None:
            data[dst] = val
    if enc.get("primary_applicant_name") is not None:
        data["primary_applicant_name"] = str(enc.get("primary_applicant_name"))
    if enc.get("matricule_client") is not None:
        data["matricule_client"] = str(enc.get("matricule_client"))


def lookup_personnel(matricule: str) -> dict:
    sql = load_kyc_sql()
    if not db.strip_sql(sql):
        return {
            "ok": False,
            "data": None,
            "message": "Aucun SQL fiche (env ou fichier sql/kyc_by_customer.sql).",
        }
    try:
        conn = db.connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            data = _kyc_by_matricule(cur, matricule)
            if data is None:
                return {"ok": True, "data": None}
            try:
                enc = _encours_by_matricule(cur, matricule)
                if enc:
                    merge_encours(data, enc)
            except Exception as e:
                _logger.warning(
                    "Fusion encours ignorée pour matricule=%s: %s",
                    matricule,
                    e,
                    exc_info=True,
                )
            return {"ok": True, "data": data}
    finally:
        conn.close()


def _fetch_all_detection(cur) -> list[dict]:
    base = db.strip_sql(load_staff_liees_sql() or load_detection_sql())
    if not base:
        return []
    db.execute(cur, base)
    return db.rows_as_dicts(cur)


def _kyc_map(cur, customer_nos: set[str]) -> dict[str, dict]:
    nos = {n for n in customer_nos if n}
    if not nos:
        return {}
    base = db.strip_sql(load_kyc_sql())
    if not base:
        return {}
    # Bind par lots pour éviter une clause IN trop longue
    out: dict[str, dict] = {}
    nos_list = list(nos)
    for i in range(0, len(nos_list), 500):
        chunk = nos_list[i : i + 500]
        binds = {f"c{j}": v for j, v in enumerate(chunk)}
        placeholders = ", ".join(f":c{j}" for j in range(len(chunk)))
        sql = f"""
        SELECT * FROM (
            {base}
        ) q
        WHERE TRIM(CUSTOMER_NO) IN ({placeholders})
        """
        db.execute(cur, sql, binds)
        for row in db.rows_as_dicts(cur):
            out[_s(row.get("customer_no"))] = row
    return out


def _encours_map(cur, customer_ids: set[str]) -> dict[str, dict]:
    ids = {n for n in customer_ids if n}
    if not ids:
        return {}
    base = db.strip_sql(load_encours_sql())
    if not base:
        return {}
    out: dict[str, dict] = {}
    ids_list = list(ids)
    for i in range(0, len(ids_list), 500):
        chunk = ids_list[i : i + 500]
        binds = {f"c{j}": v for j, v in enumerate(chunk)}
        placeholders = ", ".join(f":c{j}" for j in range(len(chunk)))
        sql = f"""
        SELECT * FROM (
            {base}
        ) q
        WHERE TRIM(CUSTOMER_ID) IN ({placeholders})
        """
        db.execute(cur, sql, binds)
        for row in db.rows_as_dicts(cur):
            out[_s(row.get("customer_id"))] = row
    return out


def _map_detection_row(d: dict, kyc: dict[str, dict], enc: dict[str, dict]) -> dict | None:
    staff = _s(d.get("kyc_staff"))
    client = customer_from_account(_s(d.get("cust_ac_no")))
    if not staff or not client or staff == client:
        return None
    ks = kyc.get(staff, {})
    kc = kyc.get(client, {})
    es = enc.get(staff, {})
    ec = enc.get(client, {})
    return {
        "nom_staff": _s(ks.get("full_name")) or staff,
        "encours_staff": _to_float(es.get("encours_total_m")) or 0.0,
        "matricule_staff": staff,
        "type_piece_staff": _s(ks.get("type_piece")) or "—",
        "numero_piece_staff": _s(ks.get("numero_piece")) or "—",
        "telephone_staff": _s(ks.get("mobile_number")) or _s(ks.get("telephone")) or "—",
        "nom_personne_liee": _s(kc.get("full_name")) or client,
        "matricule_personnel_lie": client,
        "encours_personne_liee": _to_float(ec.get("encours_total_m")) or 0.0,
        "type_liaison": "Détection auto",
        "detail_liaison": _s(d.get("cust_ac_no")) or None,
        "migration_date": d.get("migration_date"),
        "migration_date_minus1": d.get("migration_date_minus1"),
    }


def _map_liee_row(d: dict, kyc: dict[str, dict]) -> dict | None:
    staff = _s(d.get("kyc_staff"))
    client = customer_from_account(_s(d.get("cust_ac_no")))
    if not staff or not client or staff == client:
        return None
    cli = kyc.get(client, {})
    ctype = _s(cli.get("customer_type")).upper()
    morale = ctype == "C"
    full = _s(cli.get("full_name"))
    return {
        "numero_client": client,
        "matricule": client,
        "cust_ac_no": _s(d.get("cust_ac_no")),
        "type_relation": "Détection auto",
        "classe": 2,
        "prenom_nom": full or client,
        "customer_type": ctype or None,
        "est_personne_morale": 1 if morale else 0,
        "raison_sociale": full if morale else None,
        "piece_type": cli.get("type_piece"),
        "piece_numero": cli.get("numero_piece"),
        "telephone": _s(cli.get("mobile_number")) or _s(cli.get("telephone")) or None,
        "kyc_staff": staff,
        "migration_date": d.get("migration_date"),
        "migration_date_minus1": d.get("migration_date_minus1"),
    }


def personnes_liees(matricule: str) -> dict:
    sql = load_staff_liees_sql()
    if not db.strip_sql(sql):
        return {
            "ok": True,
            "data": [],
            "message": "Définissez ORACLE_REPORT_GROUPE_STAFF_LIEES_SQL (personnes liées).",
        }
    try:
        conn = db.connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            # Résoudre staff (n° client ou n° pièce)
            staff_row = _kyc_by_matricule(cur, matricule)
            staff_no = _s(staff_row.get("customer_no")) if staff_row else matricule

            raw = _fetch_all_detection(cur)
            raw = [r for r in raw if _s(r.get("kyc_staff")) == staff_no]
            clients = {customer_from_account(_s(r.get("cust_ac_no"))) for r in raw}
            kyc = _kyc_map(cur, clients)
            data = []
            seen = set()
            for r in raw:
                mapped = _map_liee_row(r, kyc)
                if not mapped:
                    continue
                key = mapped["numero_client"]
                if key in seen:
                    continue
                seen.add(key)
                data.append(mapped)
            return {"ok": True, "data": data}
    finally:
        conn.close()


def detection_staff_clients() -> dict:
    sql = load_detection_sql()
    if not db.strip_sql(sql):
        return {
            "ok": True,
            "data": [],
            "message": "Définissez ORACLE_DETECTION_STAFF_CLIENTS_SQL_FILE (sql/detection_staff_clients.sql).",
        }
    try:
        conn = db.connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            db.execute(cur, db.strip_sql(sql))
            raw = db.rows_as_dicts(cur)
            staffs = {_s(r.get("kyc_staff")) for r in raw}
            clients = {customer_from_account(_s(r.get("cust_ac_no"))) for r in raw}
            kyc = _kyc_map(cur, staffs | clients)
            enc = _encours_map(cur, staffs | clients)
            data = []
            seen = set()
            for r in raw:
                mapped = _map_detection_row(r, kyc, enc)
                if not mapped:
                    continue
                key = (mapped["matricule_staff"], mapped["matricule_personnel_lie"])
                if key in seen:
                    continue
                seen.add(key)
                data.append(mapped)
            data.sort(
                key=lambda x: (
                    x.get("nom_staff") or "",
                    x.get("nom_personne_liee") or "",
                    x.get("matricule_personnel_lie") or "",
                )
            )
            return {"ok": True, "data": data}
    except Exception as e:
        _logger.exception("Échec détection staff-clients: %s", e)
        raise HTTPException(status_code=502, detail=str(e)) from e
    finally:
        conn.close()


def alertes_doublons_clients() -> dict:
    sql = load_alertes_sql()
    if not db.strip_sql(sql):
        return {
            "ok": True,
            "data": [],
            "message": "Définissez ORACLE_ALERTES_DOUBLONS_CLIENTS_SQL_FILE (sql/alertes_doublons_clients.sql).",
        }
    try:
        conn = db.connect()
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=502, detail=str(e)) from e

    try:
        with conn.cursor() as cur:
            db.execute(cur, db.strip_sql(sql))
            return {"ok": True, "data": db.rows_as_dicts(cur)}
    except Exception as e:
        _logger.exception("Échec alertes doublons clients: %s", e)
        raise HTTPException(status_code=502, detail=str(e)) from e
    finally:
        conn.close()
