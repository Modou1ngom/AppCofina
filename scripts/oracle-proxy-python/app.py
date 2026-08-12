"""
Proxy HTTP SIG → Oracle.

Séparation :
  sql/*.sql     — SELECT bruts (tables {CC}_* : SN_*, TG_*, …)
  oracle_db.py  — connexion / exécution / préfixe pays
  sig_repository.py — filtres, enrichissement, mapping API
  app.py        — routes HTTP uniquement
"""

from __future__ import annotations

from fastapi import FastAPI, HTTPException, Query, Request
from pydantic import BaseModel, Field

import oracle_db as db
import sig_repository as repo

app = FastAPI(title="AppCofina Oracle proxy", version="1.2.0")


class LookupBody(BaseModel):
    matricule: str
    pays: str | None = Field(
        default=None,
        description="Préfixe pays / environnement (SN, TG, TOGO, …)",
    )


def _pays_from_request(request: Request, explicit: str | None = None) -> str | None:
    if explicit and str(explicit).strip():
        return str(explicit).strip()
    q = request.query_params.get("pays") or request.query_params.get("environnement")
    if q and q.strip():
        return q.strip()
    h = request.headers.get("X-Oracle-Pays") or request.headers.get("X-Pays")
    if h and h.strip():
        return h.strip()
    return None


@app.get("/health")
def health():
    return {
        "status": "ok",
        "default_pays": db.normalize_pays_prefix(None),
    }


@app.post("/api/sig/lookup-personnel")
def lookup_personnel(body: LookupBody, request: Request):
    matricule = (body.matricule or "").strip()
    if not matricule:
        raise HTTPException(status_code=422, detail="matricule requis")
    pays = _pays_from_request(request, body.pays)
    return repo.lookup_personnel(matricule, pays)


@app.get("/api/sig/staff/{matricule}/personnes-liees")
def personnes_liees(
    matricule: str,
    request: Request,
    pays: str | None = Query(default=None),
):
    m = (matricule or "").strip()
    if not m:
        raise HTTPException(status_code=422, detail="matricule requis")
    return repo.personnes_liees(m, _pays_from_request(request, pays))


@app.get("/api/sig/detection-staff-clients")
def detection_staff_clients(
    request: Request,
    pays: str | None = Query(default=None),
):
    return repo.detection_staff_clients(_pays_from_request(request, pays))


@app.get("/api/sig/alertes-doublons-clients")
def alertes_doublons_clients(
    request: Request,
    pays: str | None = Query(default=None),
):
    return repo.alertes_doublons_clients(_pays_from_request(request, pays))
