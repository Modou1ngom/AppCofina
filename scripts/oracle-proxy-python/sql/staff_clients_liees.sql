-- Réutilise la même source que detection_staff_clients.sql
-- (filtre staff appliqué côté proxy).
SELECT
    CUST_AC_NO,
    KYC_STAFF,
    MIGRATION_DATE,
    MIGRATION_DATE_MINUS1
FROM {CC}_DETECTION_AUTOMATIQUE
