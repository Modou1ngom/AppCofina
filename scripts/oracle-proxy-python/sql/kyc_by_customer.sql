-- Fiche KYC client (FCUBS) — bind :matricule = CUSTOMER_NO
-- Pièce d’identité SI : UNIQUE_ID_NAME (libellé / type), UNIQUE_ID_VALUE (numéro) — colonnes STTM_CUSTOMER
-- Exécuter avec ALTER SESSION CURRENT_SCHEMA = CFSFCUBS145 (voir ORACLE_CURRENT_SCHEMA dans .env) ou noms de tables qualifiés ci-dessous
WITH kyc AS (
    SELECT
        p.CUSTOMER_NO,
        DECODE(sc.CUSTOMER_TYPE, 'C', '2', 'I', '1') AS type_client,
        sc.EXT_REF_NO AS numero_nafa,
        p.CUSTOMER_PREFIX,
        DECODE(sc.CUSTOMER_TYPE, 'C', 'ENTREPRISE', 'I', 'PARTICULIER')
            || NVL(DECODE(p.sex, 'M', 'HOMME', 'F', ' FEMME'), '') AS categorie,
        sc.CUSTOMER_TYPE,
        '1' AS identificationregister,
        '1' AS identificationregister_1,
        sc.COUNTRY,
        sc.LANGUAGE,
        DECODE(p.sex, 'M', 'HOMME', 'F', 'FEMME') AS genre,
        p.MIDDLE_NAME,
        p.FIRST_NAME,
        sc.FULL_NAME,
        p.DATE_OF_BIRTH,
        p.PLACE_OF_BIRTH,
        p.SEX,
        p.P_NATIONAL_ID,
        p.PASSPORT_NO,
        p.PPT_ISS_DATE,
        p.PPT_EXP_DATE,
        p.D_ADDRESS1,
        p.E_MAIL,
        p.MOB_ISD_NO,
        p.TELEPHONE,
        p.MOBILE_NUMBER,
        p.MOTHER_MAIDEN_NAME,
        sc.CUSTOMER_NO AS sc_customer_no,
        sc.LOCAL_BRANCH,
        b.BRANCH_NAME,
        sc.RECORD_STAT,
        sc.MAKER_ID,
        sc.CHECKER_ID,
        sc.CIF_CREATION_DATE AS date_creation,
        cat.CUST_CAT,
        cat.CUST_CAT_DESC,
        sc.UNIQUE_ID_NAME,
        sc.UNIQUE_ID_VALUE
    FROM CFSFCUBS145.STTM_CUSTOMER sc
    LEFT JOIN CFSFCUBS145.STTM_BRANCH b
        ON b.BRANCH_CODE = sc.LOCAL_BRANCH
    LEFT JOIN CFSFCUBS145.STTM_CUST_PERSONAL p
        ON sc.CUSTOMER_NO = p.CUSTOMER_NO
    LEFT JOIN CFSFCUBS145.STTM_CUST_CORPORATE c
        ON sc.CUSTOMER_NO = c.CUSTOMER_NO
    LEFT JOIN CFSFCUBS145.STTM_CUSTOMER_CAT cat
        ON cat.CUST_CAT = sc.CUSTOMER_CATEGORY
)
SELECT * FROM kyc
WHERE CUSTOMER_NO = :matricule
