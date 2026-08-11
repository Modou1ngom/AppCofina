"""
Proxy HTTP SIG → Oracle.

Séparation :
  sql/*.sql     — SELECT bruts (tables SN_*)
  oracle_db.py  — connexion / exécution
  sig_repository.py — filtres, enrichissement, mapping API
  app.py        — routes HTTP uniquement
"""

from __future__ import annotations

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel

import sig_repository as repo

app = FastAPI(title="AppCofina Oracle proxy", version="1.1.0")


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
    return repo.lookup_personnel(matricule)


@app.get("/api/sig/staff/{matricule}/personnes-liees")
def personnes_liees(matricule: str):
    m = (matricule or "").strip()
    if not m:
        raise HTTPException(status_code=422, detail="matricule requis")
    return repo.personnes_liees(m)


@app.get("/api/sig/detection-staff-clients")
def detection_staff_clients():
    return repo.detection_staff_clients()


@app.get("/api/sig/alertes-doublons-clients")
def alertes_doublons_clients():
    return repo.alertes_doublons_clients()
