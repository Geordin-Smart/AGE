<?php
// edit_contract.php - Modification de Contrat & Archivage en Corbeille (Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$db = getDB();
$company = getCompanyConfig();

$contractId = intval($_GET['id'] ?? 0);
if ($contractId <= 0) {
    header("Location: contracts.php");
    exit;
}

$stmt = $db->prepare("SELECT c.*, e.matricule, e.nom, e.prenom, e.email, e.telephone
                      FROM contracts c 
                      JOIN employees e ON c.employee_id = e.id 
                      WHERE c.id = ? AND e.user_id = ?");
$stmt->execute([$contractId, (int)$_SESSION['user_id']]);
$currentContract = $stmt->fetch();

if (!$currentContract) {
    header("Location: contracts.php?error=not_found");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'modify_contract') {
    $poste = trim($_POST['poste'] ?? '');
    $categorie = trim($_POST['categorie'] ?? 'Ouvrier');
    $type_contrat = trim($_POST['type_contrat'] ?? 'CDI');
    $type_remuneration = trim($_POST['type_remuneration'] ?? 'mensuel');
    $salaire_base = floatval($_POST['salaire_base'] ?? 0);
    $date_debut = !empty($_POST['date_debut']) ? $_POST['date_debut'] : date('Y-m-d');
    $date_fin = ($type_contrat === 'CDD' && !empty($_POST['date_fin'])) ? $_POST['date_fin'] : null;
    $motif = trim($_POST['motif_modification'] ?? 'Avenant au contrat');
    $clauses = trim($_POST['clauses_notes'] ?? 'Contrat régi par la Loi n° 022/2021 portant Code du Travail au Gabon.');

    if (empty($poste) || $salaire_base <= 0) {
        $error = "Veuillez renseigner le poste et un salaire valide.";
    } elseif ($type_contrat === 'CDD' && empty($date_fin)) {
        $error = "Pour un contrat CDD, la date de fin est obligatoire.";
    } else {
        try {
            $db->beginTransaction();

            // 1. Déplacer l'ancien contrat dans la corbeille
                $db->prepare("UPDATE contracts SET status = 'corbeille', corbeille_date = NOW(), corbeille_reason = ? WHERE id = ? AND employee_id IN (SELECT id FROM employees WHERE user_id = ?)")
                    ->execute([$motif, $contractId, (int)$_SESSION['user_id']]);

            // 2. Nouveau numéro de version
            $newVersion = intval($currentContract['version']) + 1;
            $newContractNumber = sprintf("CTR-%s-V%d", $currentContract['matricule'], $newVersion);

            // 3. Insérer le nouveau contrat actif
            $stmtNew = $db->prepare("INSERT INTO contracts 
                (employee_id, contract_number, version, type_contrat, poste, categorie, type_remuneration, salaire_base, date_debut, date_fin, clauses_notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
            $stmtNew->execute([
                $currentContract['employee_id'], $newContractNumber, $newVersion, $type_contrat,
                $poste, $categorie, $type_remuneration, $salaire_base, $date_debut, $date_fin, $clauses
            ]);
            $newContractId = $db->lastInsertId();

            // 4. Mettre à jour l'employé
            $db->prepare("UPDATE employees SET poste = ?, categorie = ?, type_remuneration = ?, salaire_base = ?, type_contrat = ?, date_debut_contrat = ?, date_fin_contrat = ? WHERE id = ?")
               ->execute([$poste, $categorie, $type_remuneration, $salaire_base, $type_contrat, $date_debut, $date_fin, $currentContract['employee_id']]);

            $db->commit();
            header("Location: view_contract.php?id=" . $newContractId);
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

$pageTitle = "Modifier Contrat";
include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Modifier le Contrat</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);">
            L'ancien contrat (<?= htmlspecialchars($currentContract['contract_number']) ?>) sera automatiquement placé en corbeille
        </p>
    </div>
    <a href="contracts.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Annuler
    </a>
</div>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card-glass">
    <form method="POST" action="edit_contract.php?id=<?= $contractId ?>">
        <input type="hidden" name="action" value="modify_contract">

        <div class="form-group">
            <label class="form-label" for="motif_modification">Motif de la Modification (Justification en Corbeille) <span class="required">*</span></label>
            <input type="text" id="motif_modification" name="motif_modification" class="form-control" 
                   value="Renouvellement et réévaluation des termes" required>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="poste">Nouveau Poste <span class="required">*</span></label>
                <input type="text" id="poste" name="poste" class="form-control" value="<?= htmlspecialchars($currentContract['poste']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="categorie">Catégorie <span class="required">*</span></label>
                <select id="categorie" name="categorie" class="form-select" required>
                    <option value="Ouvrier" <?= ($currentContract['categorie'] === 'Ouvrier') ? 'selected' : '' ?>>Ouvrier</option>
                    <option value="Agent de maîtrise" <?= ($currentContract['categorie'] === 'Agent de maîtrise') ? 'selected' : '' ?>>Agent de maîtrise</option>
                    <option value="Cadre" <?= ($currentContract['categorie'] === 'Cadre') ? 'selected' : '' ?>>Cadre</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="type_contrat">Type de Contrat <span class="required">*</span></label>
                <select id="type_contrat" name="type_contrat" class="form-select" required>
                    <option value="CDI" <?= ($currentContract['type_contrat'] === 'CDI') ? 'selected' : '' ?>>CDI (Durée Indéterminée)</option>
                    <option value="CDD" <?= ($currentContract['type_contrat'] === 'CDD') ? 'selected' : '' ?>>CDD (Durée Déterminée)</option>
                </select>
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="type_remuneration">Mode de Paie</label>
                <select id="type_remuneration" name="type_remuneration" class="form-select" required>
                    <option value="mensuel" <?= ($currentContract['type_remuneration'] === 'mensuel') ? 'selected' : '' ?>>Mensuelle</option>
                    <option value="journalier" <?= ($currentContract['type_remuneration'] === 'journalier') ? 'selected' : '' ?>>Journalière</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" id="salaire_label" for="salaire_base">Salaire Base (FCFA) <span class="required">*</span></label>
                <input type="number" step="1" id="salaire_base" name="salaire_base" class="form-control" value="<?= intval($currentContract['salaire_base']) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="date_debut">Date d'Effet</label>
                <input type="date" id="date_debut" name="date_debut" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
        </div>

        <div class="form-group" id="dateFinGroup" style="<?= ($currentContract['type_contrat'] === 'CDD') ? 'display: block;' : 'display: none;' ?>">
            <label class="form-label" for="date_fin_contrat">Date de Fin CDD <span class="required">* (Alerte 30j)</span></label>
            <input type="date" id="date_fin_contrat" name="date_fin" class="form-control" value="<?= htmlspecialchars($currentContract['date_fin'] ?? '') ?>">
        </div>

        <div style="text-align: right; margin-top: 10px;">
            <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Confirmez-vous le remplacement de l\'ancien contrat ? Celui-ci sera envoyé en corbeille.');">
                <i class="fa-solid fa-check"></i> Valider & Déplacer l'Ancien en Corbeille
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
