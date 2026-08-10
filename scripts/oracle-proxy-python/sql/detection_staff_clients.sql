-- Détection globale : tous les staffs (ACCOUNT_CLASS 25136) liés à des clients
-- Sources : caution STDCOLAT + cotitulaires STTM_AC_LINKED_ENTITIES
-- Aucun bind : liste complète pour le menu « Détection automatique »
WITH CAUTION AS (
    SELECT
        REGEXP_SUBSTR(t.REC_KEY, '~([^~]+)~', 1, 1, NULL, 1) AS matricule,
        t.FIELD_VAL_1 AS TYPE_DE_COLLATERALE,
        t.FIELD_VAL_2 AS TYPE_DE_PIECE_CAUTION,
        t.FIELD_VAL_3 AS NUMERO_PIECE_CAUTION,
        t.FIELD_VAL_4 AS TELEPHONE_CAUTION,
        t.FIELD_VAL_5 AS NUMERO_PRET_CAUTION,
        t.FIELD_VAL_6 AS NOM_GARANT
    FROM CFSFCUBS145.CSTM_FUNCTION_USERDEF_FIELDS t
    WHERE t.FUNCTION_ID = 'STDCOLAT'
),
staff_25136 AS (
    SELECT DISTINCT TRIM(ca.CUST_NO) AS customer_no
    FROM CFSFCUBS145.STTM_CUST_ACCOUNT ca
    WHERE ca.ACCOUNT_CLASS = '25136'
),
liens_bruts AS (
    SELECT
        TRIM(c.MATRICULE) AS client_ref,
        TRIM(c.NUMERO_PIECE_CAUTION) AS staff_key,
        'piece' AS staff_key_type,
        'Caution' AS type_liaison,
        c.TYPE_DE_COLLATERALE AS detail_liaison,
        c.TELEPHONE_CAUTION AS telephone_caution,
        c.NOM_GARANT AS nom_garant
    FROM CAUTION c
    WHERE c.NUMERO_PIECE_CAUTION IN (
        SELECT sc.UNIQUE_ID_VALUE
        FROM CFSFCUBS145.STTM_CUSTOMER sc
        WHERE TRIM(sc.CUSTOMER_NO) IN (SELECT customer_no FROM staff_25136)
          AND sc.UNIQUE_ID_VALUE IS NOT NULL
    )
    UNION ALL
    SELECT
        TRIM(e.CUST_AC_NO) AS client_ref,
        TRIM(e.JOINT_HOLDER_CODE) AS staff_key,
        'customer' AS staff_key_type,
        'Cotitulaire' AS type_liaison,
        'Compte joint / entité liée' AS detail_liaison,
        CAST(NULL AS VARCHAR2(50)) AS telephone_caution,
        CAST(NULL AS VARCHAR2(200)) AS nom_garant
    FROM CFSFCUBS145.STTM_AC_LINKED_ENTITIES e
    WHERE TRIM(e.JOINT_HOLDER_CODE) IN (SELECT customer_no FROM staff_25136)
),
liens AS (
    SELECT
        NVL(cli_by_no.CUSTOMER_NO, acc.CUST_NO) AS matricule_personnel_lie,
        CASE
            WHEN lb.staff_key_type = 'customer' THEN lb.staff_key
            ELSE staff_by_piece.CUSTOMER_NO
        END AS matricule_staff,
        CASE
            WHEN lb.staff_key_type = 'customer' THEN staff_by_no.UNIQUE_ID_NAME
            ELSE staff_by_piece.UNIQUE_ID_NAME
        END AS type_piece_staff,
        CASE
            WHEN lb.staff_key_type = 'customer' THEN staff_by_no.UNIQUE_ID_VALUE
            ELSE staff_by_piece.UNIQUE_ID_VALUE
        END AS numero_piece_staff,
        CASE
            WHEN lb.staff_key_type = 'customer' THEN staff_by_no.FULL_NAME
            ELSE NVL(staff_by_piece.FULL_NAME, lb.nom_garant)
        END AS nom_staff,
        CASE
            WHEN lb.staff_key_type = 'customer' THEN NVL(pers_staff_no.MOBILE_NUMBER, pers_staff_no.TELEPHONE)
            ELSE NVL(lb.telephone_caution, NVL(pers_staff_piece.MOBILE_NUMBER, pers_staff_piece.TELEPHONE))
        END AS telephone_staff,
        NVL(cli_by_no.FULL_NAME, cli_by_acc.FULL_NAME) AS nom_personne_liee,
        lb.type_liaison,
        lb.detail_liaison
    FROM liens_bruts lb
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER cli_by_no
        ON TRIM(cli_by_no.CUSTOMER_NO) = lb.client_ref
    LEFT JOIN CFSFCUBS145.STTM_CUST_ACCOUNT acc
        ON TRIM(acc.CUST_AC_NO) = lb.client_ref
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER cli_by_acc
        ON TRIM(cli_by_acc.CUSTOMER_NO) = TRIM(acc.CUST_NO)
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER staff_by_piece
        ON lb.staff_key_type = 'piece'
       AND TRIM(staff_by_piece.UNIQUE_ID_VALUE) = lb.staff_key
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER staff_by_no
        ON lb.staff_key_type = 'customer'
       AND TRIM(staff_by_no.CUSTOMER_NO) = lb.staff_key
    LEFT JOIN CFSFCUBS145.STTM_CUST_PERSONAL pers_staff_piece
        ON TRIM(pers_staff_piece.CUSTOMER_NO) = TRIM(staff_by_piece.CUSTOMER_NO)
    LEFT JOIN CFSFCUBS145.STTM_CUST_PERSONAL pers_staff_no
        ON TRIM(pers_staff_no.CUSTOMER_NO) = TRIM(staff_by_no.CUSTOMER_NO)
),
encours AS (
    SELECT
        TRIM(w.CUSTOMER_ID) AS customer_id,
        SUM(NVL(z.AMOUNT_DUE, 0) - NVL(z.AMOUNT_SETTLED, 0)) AS encours_total
    FROM CFSFCUBS145.CLTB_ACCOUNT_MASTER w
    LEFT JOIN CFSFCUBS145.CLTB_ACCOUNT_SCHEDULES z
        ON z.ACCOUNT_NUMBER = w.ACCOUNT_NUMBER
    WHERE w.ACCOUNT_STATUS NOT IN ('L', 'V')
      AND z.COMPONENT_NAME = 'PRINCIPAL'
    GROUP BY TRIM(w.CUSTOMER_ID)
)
SELECT DISTINCT
    l.nom_staff,
    NVL(es.encours_total, 0) AS encours_staff,
    l.matricule_staff,
    l.type_piece_staff,
    l.numero_piece_staff,
    l.telephone_staff,
    l.nom_personne_liee,
    l.matricule_personnel_lie,
    NVL(el.encours_total, 0) AS encours_personne_liee,
    l.type_liaison,
    l.detail_liaison
FROM liens l
LEFT JOIN encours es
    ON es.customer_id = TRIM(l.matricule_staff)
LEFT JOIN encours el
    ON el.customer_id = TRIM(l.matricule_personnel_lie)
WHERE l.matricule_staff IS NOT NULL
  AND l.matricule_personnel_lie IS NOT NULL
  AND TRIM(l.matricule_staff) <> TRIM(l.matricule_personnel_lie)
ORDER BY l.nom_staff, l.nom_personne_liee, l.matricule_personnel_lie
