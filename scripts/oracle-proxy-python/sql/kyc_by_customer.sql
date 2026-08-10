-- Fiche KYC client (FCUBS) — bind :matricule = CUSTOMER_NO
-- Pièce : TYPE_PIECE / NUMERO_PIECE (= UNIQUE_ID_NAME / UNIQUE_ID_VALUE)
WITH KYC AS (
    SELECT
        sc.CUSTOMER_NO,
        DECODE(sc.CUSTOMER_TYPE, 'C', '2', 'I', '1') AS type_client,
        sc.EXT_REF_NO AS NUMERO_NAFA,
        p.CUSTOMER_PREFIX,
        DECODE(sc.CUSTOMER_TYPE, 'C', 'ENTREPRISE', 'I', 'PARTICULIER')
            || NVL(DECODE(p.SEX, 'M', 'HOMME', 'F', ' FEMME'), '') AS CATEGORIE,
        sc.CUSTOMER_TYPE,
        DECODE(p.SEX, 'M', 'HOMME', 'F', 'FEMME') AS GENRE,
        p.FIRST_NAME,
        p.MIDDLE_NAME,
        sc.FULL_NAME,
        p.DATE_OF_BIRTH,
        p.PLACE_OF_BIRTH,
        p.SEX,
        p.P_NATIONAL_ID,
        p.PASSPORT_NO,
        sc.UNIQUE_ID_NAME AS TYPE_PIECE,
        sc.UNIQUE_ID_VALUE AS NUMERO_PIECE,
        sc.UNIQUE_ID_NAME AS unique_id_name,
        sc.UNIQUE_ID_VALUE AS unique_id_value,
        p.PPT_ISS_DATE,
        p.PPT_EXP_DATE,
        p.D_ADDRESS1,
        p.E_MAIL,
        p.MOB_ISD_NO,
        p.TELEPHONE,
        p.MOBILE_NUMBER,
        p.MOTHER_MAIDEN_NAME,
        sc.CUSTOMER_NO AS SC_CUSTOMER_NO,
        sc.LOCAL_BRANCH,
        b.BRANCH_NAME,
        sc.RECORD_STAT,
        sc.MAKER_ID,
        sc.CHECKER_ID,
        sc.CIF_CREATION_DATE AS DATE_CREATION,
        cat.CUST_CAT,
        cat.CUST_CAT_DESC
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
SELECT *
FROM KYC
WHERE TRIM(CUSTOMER_NO) = TRIM(:matricule)
   OR TRIM(UNIQUE_ID_VALUE) = TRIM(:matricule)
