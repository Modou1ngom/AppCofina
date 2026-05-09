-- Encours (principal) par CLIENT — bind :matricule = numéro client (CUSTOMER_NO / CUSTOMER_ID selon site)
-- Date de référence : aujourd'hui (TRUNC(SYSDATE))
-- TRIM : évite les écarts CHAR / espaces FCUBS ; le IN couvre les cas où comptes et KYC ne partagent pas la même clé affichée.
SELECT
    TRIM(c.CUSTOMER_ID) AS matricule_client,
    SUM(NVL(z.AMOUNT_DUE, 0) - NVL(z.AMOUNT_SETTLED, 0)) AS encours_total,
    TO_CHAR(TRUNC(SYSDATE), 'DD/MM/YYYY') AS value_date
FROM CFSFCUBS145.CLTB_ACCOUNT_MASTER c
LEFT JOIN CFSFCUBS145.CLTB_ACCOUNT_SCHEDULES z
    ON z.account_number = c.account_number
WHERE
    c.account_status NOT IN ('L', 'V')
    AND z.COMPONENT_NAME = 'PRINCIPAL'
    AND z.SCHEDULE_DUE_DATE < TRUNC(SYSDATE)
    AND (
        TRIM(c.CUSTOMER_ID) = TRIM(:matricule)
        OR TRIM(c.CUSTOMER_ID) IN (
            SELECT TRIM(sc.CUSTOMER_NO)
            FROM CFSFCUBS145.STTM_CUSTOMER sc
            WHERE TRIM(sc.CUSTOMER_NO) = TRIM(:matricule)
        )
    )
GROUP BY c.CUSTOMER_ID
