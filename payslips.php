<?php
// payslips.php - Registre Simplifié des Bulletins de Paie (Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Fiches de Paie";
$db = getDB();
$company = getCompanyConfig();

$search = trim($_GET['search'] ?? '');
$sql = "SELECT p.*, e.matricule, e.nom, e.prenom, e.poste, e.categorie
        FROM payslips p
        JOIN employees e ON p.employee_id = e.id
        WHERE e.user_id = ?";
    $params = [(int)$_SESSION['user_id']];
if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR p.slip_number LIKE ?)";
    $t = "%$search%";
    array_push($params, $t, $t, $t, $t);
}
$sql .= " ORDER BY p.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$payslips = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Bulletins de Salaire</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);">Gestion des fiches de paie conformes aux cotisations CNSS & CNAMGS</p>
    </div>
    <a href="generate_payslip.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-file-circle-plus"></i> Nouveau Bulletin
    </a>
</div>

<div class="card-glass">
    <div class="card-header-flex">
        <h3 class="card-title">
            <i class="fa-solid fa-receipt"></i> Registre des Bulletins Émis
        </h3>
        <form method="GET" action="payslips.php" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" style="width: 200px; padding: 6px 10px;" placeholder="Recherche..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>N° Bulletin</th>
                    <th>Salarié</th>
                    <th>Catégorie</th>
                    <th>Période</th>
                    <th>Salaire Brut</th>
                    <th>Cotisations</th>
                    <th>Net à Payer</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payslips)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Aucun bulletin enregistré.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payslips as $ps): ?>
                        <tr>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #fda4af;">
                                    <?= htmlspecialchars($ps['slip_number']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #ffffff;"><?= htmlspecialchars($ps['nom'] . ' ' . $ps['prenom']) ?></strong>
                                <div style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($ps['matricule']) ?></div>
                            </td>
                            <td>
                                <span class="badge <?= ($ps['categorie'] === 'Cadre') ? 'badge-cat-cadre' : (($ps['categorie'] === 'Agent de maîtrise') ? 'badge-cat-maitrise' : 'badge-cat-ouvrier') ?>">
                                    <?= htmlspecialchars($ps['categorie']) ?>
                                </span>
                            </td>
                            <td><strong><?= htmlspecialchars($ps['period_label']) ?></strong></td>
                            <td><?= formatCurrency($ps['salaire_brut'], 'FCFA') ?></td>
                            <td style="color: #fca5a5;">- <?= formatCurrency($ps['cotisations_sociales'] + $ps['retenues_fiscales'], 'FCFA') ?></td>
                            <td>
                                <strong style="color: #34d399;"><?= formatCurrency($ps['salaire_net'], 'FCFA') ?></strong>
                            </td>
                            <td style="text-align: right;">
                                <a href="view_payslip.php?id=<?= $ps['id'] ?>" class="btn btn-secondary btn-sm" title="Imprimer le bulletin">
                                    <i class="fa-solid fa-print"></i> Imprimer
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
