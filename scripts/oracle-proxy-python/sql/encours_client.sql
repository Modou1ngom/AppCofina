-- Encours (principal) par CLIENT — bind : matricule = CUSTOMER_ID
-- Colonnes : encours_total (= ENCOURS_TOTAL_M), encours_sain, encours_impaye
WITH ENCOURS_client AS (
    SELECT
        w.CUSTOMER_ID,
        w.PRIMARY_APPLICANT_NAME,
        SUM(NVL(z.AMOUNT_DUE, 0) - NVL(z.AMOUNT_SETTLED, 0)) AS ENCOURS_TOTAL_M,
        SUM(
            CASE
                WHEN w.USER_DEFINED_STATUS IN ('NORM', 'IMPA')
                THEN (NVL(z.AMOUNT_DUE, 0) - NVL(z.AMOUNT_SETTLED, 0))
                ELSE 0
            END
        ) AS ENCOURS_SAIN_M,
        SUM(
            CASE
                WHEN w.USER_DEFINED_STATUS NOT IN ('NORM', 'IMPA')
                THEN (NVL(z.AMOUNT_DUE, 0) - NVL(z.AMOUNT_SETTLED, 0))
                ELSE 0
            END
        ) AS ENCOURS_IMPAYE_M
    FROM CFSFCUBS145.CLTB_ACCOUNT_MASTER w
    LEFT JOIN CFSFCUBS145.CLTB_ACCOUNT_SCHEDULES z
        ON z.ACCOUNT_NUMBER = w.ACCOUNT_NUMBER
    WHERE
        w.ACCOUNT_STATUS NOT IN ('L', 'V')
        AND z.COMPONENT_NAME = 'PRINCIPAL'
    GROUP BY
        w.CUSTOMER_ID,
        w.PRIMARY_APPLICANT_NAME
)
SELECT
    TRIM(CUSTOMER_ID) AS matricule_client,
    PRIMARY_APPLICANT_NAME AS primary_applicant_name,
    ENCOURS_TOTAL_M AS encours_total,
    ENCOURS_TOTAL_M AS encours_total_m,
    ENCOURS_SAIN_M AS encours_sain,
    ENCOURS_SAIN_M AS encours_sain_m,
    ENCOURS_IMPAYE_M AS encours_impaye,
    ENCOURS_IMPAYE_M AS encours_impaye_m
FROM ENCOURS_client
WHERE TRIM(CUSTOMER_ID) = TRIM(:matricule)
