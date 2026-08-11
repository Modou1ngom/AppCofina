/** Types de relation réglementaires (personnes liées / suivi signature). */
export const SIG_TYPES_RELATION = [
    'Époux',
    'Épouse',
    'Père',
    'Mère',
    'Fils',
    'Fille',
    'Beau-père',
    'Belle-mère',
    'Beau-fils',
    'Belle-fille',
    'Associé',
    'Société de personne associé',
    'Personne morale contrôlée individuellement ou collectivement',
    'Personne morale contrôlée individuellement ou collectivement par le conjoint',
    'Personne morale contrôlée individuellement ou collectivement par le père, mère, fils, fille',
    'Détention de 10 % des droits de vote ou action',
] as const;

export type SigTypeRelation = (typeof SIG_TYPES_RELATION)[number];

const selectClass =
    'border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm';

export const sigTypeRelationSelectClass = selectClass;
