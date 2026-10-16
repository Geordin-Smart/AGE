<?php
// contracts_trash.php - Corbeille des Contrats Simplifiée (Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Corbeille Contrats";
$db = getDB();
$company = getCompanyConfig();

$success = '';
$error = '';

// Suppression définitive volontaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_contract') {
    requireValidCsrfToken();
    $deleteId = intval($_POST['contract_id'] ?? 0);
    if ($deleteId > 0) {
        $stmt = $db->prepare("DELETE c FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.id = ? AND c.status = 'corbeille' AND e.user_id = ?");
        $stmt->execute([$deleteId, (int)$_SESSION['user_id']]);
        $success = $stmt->rowCount() > 0
            ? 'Le contrat a été supprimé définitivement.'
            : 'Le contrat est introuvable ou n’est plus dans la corbeille.';
    }
}

// Nettoyage automatique des contrats archivés depuis plus de 60 jours.
$autoDeletedCount = purgeExpiredTrashContracts(60);
if ($autoDeletedCount > 0) {
    $success = $autoDeletedCount . ' contrat(s) archivé(s) depuis plus de 60 jours ont été supprimé(s) automatiquement.';
}

// Restauration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'restore_contract') {
    requireValidCsrfToken();
    $restoreId = (int)($_POST['contract_id'] ?? 0);
    if ($restoreId <= 0) {
        http_response_code(400);
        exit('Contrat invalide.');
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT c.* FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.id = ? AND c.status = 'corbeille' AND e.user_id = ?");
        $stmt->execute([$restoreId, (int)$_SESSION['user_id']]);
        $ctr = $stmt->fetch();

        if ($ctr) {
            $db->prepare("UPDATE contracts SET status = 'corbeille', corbeille_date = NOW(), corbeille_reason = 'Remplacé par restauration' WHERE employee_id = ? AND status = 'actif'")
               ->execute([$ctr['employee_id']]);

            $db->prepare("UPDATE contracts SET status = 'actif', corbeille_date = NULL, corbeille_reason = NULL WHERE id = ?")
               ->execute([$restoreId]);

            $db->prepare("UPDATE employees SET poste = ?, categorie = ?, type_remuneration = ?, salaire_base = ?, type_contrat = ?, date_debut_contrat = ?, date_fin_contrat = ? WHERE id = ?")
               ->execute([$ctr['poste'], $ctr['categorie'], $ctr['type_remuneration'], $ctr['salaire_base'], $ctr['type_contrat'], $ctr['date_debut'], $ctr['date_fin'], $ctr['employee_id']]);

            $db->commit();
            $success = "Contrat <strong>" . htmlspecialchars($ctr['contract_number']) . "</strong> restauré comme contrat actif.";
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Erreur : " . $e->getMessage();
    }
}

$stmt = $db->prepare("SELECT c.*, e.matricule, e.nom, e.prenom
                    FROM contracts c
                    JOIN employees e ON c.employee_id = e.id
                    WHERE c.status = 'corbeille' AND e.user_id = ?
                    ORDER BY c.corbeille_date DESC, c.id DESC");
$stmt->execute([(int)$_SESSION['user_id']]);
$trashContracts = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Corbeille des Contrats Remplacés</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);">Historique des anciens contrats archivés automatiquement lors des modifications</p>
    </div>
    <a href="contracts.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Contrats Actifs
    </a>
</div>

<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-check"></i> <?= $success ?>
    </div>
<?php endif; ?>

<div class="card-glass">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Date Archivage</th>
                    <th>Motif du Remplacement</th>
                    <th>N° Contrat</th>
                    <th>Salarié</th>
                    <th>Poste Archivé</th>
                    <th>Salaire Antérieur</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($trashContracts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            La corbeille des contrats est vide.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($trashContracts as $tc): ?>
                        <tr>
                            <td><?= !empty($tc['corbeille_date']) ? date('d/m/Y H:i', strtotime($tc['corbeille_date'])) : 'Archivé' ?></td>
                            <td><?= htmlspecialchars($tc['corbeille_reason'] ?? 'Remplacé par nouvelle version') ?></td>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #cbd5e1;">
                                    <?= htmlspecialchars($tc['contract_number']) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($tc['nom'] . ' ' . $tc['prenom']) ?></strong>
                                <div style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($tc['matricule']) ?></div>
                            </td>
                            <td>
                                <?= htmlspecialchars($tc['poste']) ?>
                                <span class="badge badge-trash"><?= htmlspecialchars($tc['categorie']) ?></span>
                            </td>
                            <td><?= formatCurrency($tc['salaire_base'], 'FCFA') ?></td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="view_contract.php?id=<?= $tc['id'] ?>" class="btn btn-secondary btn-sm" title="Voir">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <form method="POST" action="contracts_trash.php" style="display: inline;">
                                    <input type="hidden" name="action" value="restore_contract">
                                    <input type="hidden" name="contract_id" value="<?= (int)$tc['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Restaurer cette version en contrat actif ?');">
                                        <i class="fa-solid fa-rotate-left"></i> Restaurer
                                    </button>
                                </form>
                                <form method="POST" action="contracts_trash.php" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_contract">
                                    <input type="hidden" name="contract_id" value="<?= $tc['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer définitivement ce contrat ? Cette action est irréversible.');" title="Supprimer définitivement">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
