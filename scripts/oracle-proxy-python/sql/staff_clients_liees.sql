-- Clients liés automatiquement à un staff (caution STDCOLAT + cotitulaires).
-- Bind :matricule = n° client SI du staff (CUSTOMER_NO) OU n° pièce d'identité (UNIQUE_ID_VALUE)
-- Compte staff métier : ACCOUNT_CLASS = '25136' (filtre soft via jointure staff_ref)
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
staff_ref AS (
    SELECT
        TRIM(sc.CUSTOMER_NO) AS customer_no,
        TRIM(sc.UNIQUE_ID_VALUE) AS unique_id_value,
        TRIM(sc.UNIQUE_ID_NAME) AS unique_id_name,
        sc.FULL_NAME AS staff_full_name
    FROM CFSFCUBS145.STTM_CUSTOMER sc
    WHERE (
        TRIM(sc.CUSTOMER_NO) = TRIM(:matricule)
        OR TRIM(sc.UNIQUE_ID_VALUE) = TRIM(:matricule)
    )
    AND EXISTS (
        SELECT 1
        FROM CFSFCUBS145.STTM_CUST_ACCOUNT ca
        WHERE TRIM(ca.CUST_NO) = TRIM(sc.CUSTOMER_NO)
          AND ca.ACCOUNT_CLASS = '25136'
    )
),
-- Fallback si le staff n'a pas (encore) le ACCOUNT_CLASS 25136 : match direct sur :matricule
staff_ref_any AS (
    SELECT
        TRIM(sc.CUSTOMER_NO) AS customer_no,
        TRIM(sc.UNIQUE_ID_VALUE) AS unique_id_value,
        TRIM(sc.UNIQUE_ID_NAME) AS unique_id_name,
        sc.FULL_NAME AS staff_full_name
    FROM CFSFCUBS145.STTM_CUSTOMER sc
    WHERE TRIM(sc.CUSTOMER_NO) = TRIM(:matricule)
       OR TRIM(sc.UNIQUE_ID_VALUE) = TRIM(:matricule)
),
staff AS (
    SELECT * FROM staff_ref
    UNION ALL
    SELECT a.*
    FROM staff_ref_any a
    WHERE NOT EXISTS (SELECT 1 FROM staff_ref)
),
from_caution AS (
    SELECT
        TRIM(NVL(cli.CUSTOMER_NO, acc.CUST_NO)) AS numero_client,
        'Caution' AS type_relation,
        2 AS classe,
        c.NOM_GARANT AS libelle_source,
        c.TYPE_DE_COLLATERALE AS detail_relation,
        c.TELEPHONE_CAUTION AS telephone,
        s.customer_no AS kyc_staff,
        s.unique_id_value AS kyc_staff_piece
    FROM CAUTION c
    INNER JOIN staff s
        ON TRIM(c.NUMERO_PIECE_CAUTION) = s.unique_id_value
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER cli
        ON TRIM(cli.CUSTOMER_NO) = TRIM(c.matricule)
    LEFT JOIN CFSFCUBS145.STTM_CUST_ACCOUNT acc
        ON TRIM(acc.CUST_AC_NO) = TRIM(c.matricule)
    WHERE c.MATRICULE IS NOT NULL
),
from_joint AS (
    SELECT
        TRIM(a.CUST_NO) AS numero_client,
        'Cotitulaire' AS type_relation,
        2 AS classe,
        CAST(NULL AS VARCHAR2(200)) AS libelle_source,
        'Compte joint / entité liée' AS detail_relation,
        CAST(NULL AS VARCHAR2(50)) AS telephone,
        s.customer_no AS kyc_staff,
        s.unique_id_value AS kyc_staff_piece
    FROM CFSFCUBS145.STTM_AC_LINKED_ENTITIES e
    INNER JOIN staff s
        ON TRIM(e.JOINT_HOLDER_CODE) = s.customer_no
    INNER JOIN CFSFCUBS145.STTM_CUST_ACCOUNT a
        ON TRIM(a.CUST_AC_NO) = TRIM(e.CUST_AC_NO)
)
SELECT DISTINCT
    x.numero_client,
    x.numero_client AS matricule,
    x.numero_client AS cust_ac_no,
    x.type_relation,
    x.classe,
    x.libelle_source,
    x.detail_relation,
    x.telephone,
    x.kyc_staff,
    x.kyc_staff_piece,
    cli.FULL_NAME AS prenom_nom,
    cli.CUSTOMER_TYPE AS customer_type,
    CASE WHEN cli.CUSTOMER_TYPE = 'C' THEN 1 ELSE 0 END AS est_personne_morale,
    p.FIRST_NAME AS prenom,
    NVL(p.MIDDLE_NAME, REGEXP_SUBSTR(cli.FULL_NAME, '^(\S+)')) AS nom,
    CASE WHEN cli.CUSTOMER_TYPE = 'C' THEN cli.FULL_NAME ELSE NULL END AS raison_sociale,
    cli.UNIQUE_ID_NAME AS piece_type,
    cli.UNIQUE_ID_VALUE AS piece_numero
FROM (
    SELECT * FROM from_caution
    UNION ALL
    SELECT * FROM from_joint
) x
LEFT JOIN CFSFCUBS145.STTM_CUSTOMER cli
    ON TRIM(cli.CUSTOMER_NO) = TRIM(x.numero_client)
LEFT JOIN CFSFCUBS145.STTM_CUST_PERSONAL p
    ON TRIM(p.CUSTOMER_NO) = TRIM(x.numero_client)
WHERE x.numero_client IS NOT NULL
  AND TRIM(x.numero_client) <> TRIM(x.kyc_staff)
