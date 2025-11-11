<form action="{{ route('profils.store') }}" method="POST">
    @csrf
    <label>Nom :</label>
    <input type="text" name="nom" required>

    <label>Prénom :</label>
    <input type="text" name="prenom" required>

    <label>Matricule :</label>
    <input type="text" name="matricule" required>

    <label>Fonction :</label>
    <input type="text" name="fonction" required>

    <label>Département :</label>
    <input type="text" name="departement" required>

    <label>Supérieur hiérarchique (N+1) :</label>
    <input type="text" name="superieur_hierarchique" required>

    <label>Type de demande :</label>
    <select name="type_demande" required>
        <option value="creation">Création</option>
        <option value="modification">Modification</option>
        <option value="suppression">Suppression</option>
        <option value="transfert">Transfert</option>
    </select>

    <label>Type de contrat :</label>
    <select name="type_contrat">
        <option value="">Sélectionner un type</option>
        <option value="CDI">CDI</option>
        <option value="CDD">CDD</option>
        <option value="Stagiaire">Stagiaire</option>
        <option value="Autre">Autre</option>
    </select>

    <label>Durée (si CDD ou intérim) :</label>
    <input type="text" name="duree">

    <button type="submit">Créer</button>
</form>
