<?php
// dashboard.php - Tableau de bord épuré AGE (Édition Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Tableau de Bord";
$db = getDB();
$company = getCompanyConfig();

// Métriques clés
$userId = (int)$_SESSION['user_id'];
$stmtMetric = $db->prepare("SELECT COUNT(*) FROM employees WHERE statut = 'actif' AND user_id = ?");
$stmtMetric->execute([$userId]);
$totalEmployees = $stmtMetric->fetchColumn();
$stmtMetric = $db->prepare("SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.status = 'actif' AND e.user_id = ?");
$stmtMetric->execute([$userId]);
$activeContracts = $stmtMetric->fetchColumn();
$stmtMetric = $db->prepare("SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.status = 'actif' AND c.type_contrat = 'CDI' AND e.user_id = ?");
$stmtMetric->execute([$userId]);
$cdiContracts = $stmtMetric->fetchColumn();
$stmtMetric = $db->prepare("SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.status = 'actif' AND c.type_contrat = 'CDD' AND e.user_id = ?");
$stmtMetric->execute([$userId]);
$cddContracts = $stmtMetric->fetchColumn();
$totalPayroll = getTotalPayrollEstimate();
$expiringContracts = getExpiringContracts(30);
$countExpiring = count($expiringContracts);

// Derniers contrats actifs avec alertes
$stmtRecent = $db->prepare("SELECT c.*, e.matricule, e.nom, e.prenom, DATEDIFF(c.date_fin, CURDATE()) as jours_restants
                          FROM contracts c 
                          JOIN employees e ON c.employee_id = e.id 
                          WHERE c.status = 'actif' AND e.user_id = ?
                          ORDER BY (CASE WHEN c.date_fin IS NOT NULL THEN c.date_fin ELSE '9999-12-31' END) ASC, c.id DESC
                          LIMIT 6");
$stmtRecent->execute([$userId]);
$recentContracts = $stmtRecent->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- En-tête simplifié avec actions rapides -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Aperçu Général RH</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);"><?= htmlspecialchars($company['company_name']) ?> &bull; <?= htmlspecialchars(getCompanyLocationLabel($company)) ?>, Gabon</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="employees.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-user-plus"></i> Nouvel Employé
        </a>
        <a href="generate_payslip.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-file-invoice"></i> Émettre un Bulletin
        </a>
    </div>
</div>

<!-- 4 Métriques Clés (Propres et Épurées) -->
<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-label">Salariés Actifs</div>
        <div class="metric-value"><?= number_format($totalEmployees, 0, ',', ' ') ?></div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Matricules AGE attribués</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Masse Salariale Mensuelle</div>
        <div class="metric-value" style="color: #fda4af;">
            <?= formatCurrency($totalPayroll, 'FCFA') ?>
        </div>
        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">Base mensuelle et journalière</div>
    </div>

    <div class="metric-card">
        <div class="metric-label">Contrats de Travail</div>
        <div class="metric-value"><?= $activeContracts ?></div>
        <div style="font-size: 0.75rem; margin-top: 4px;">
            <span class="badge badge-cdi"><?= $cdiContracts ?> CDI</span>
            <span class="badge badge-cdd"><?= $cddContracts ?> CDD</span>
        </div>
    </div>

    <div class="metric-card" style="<?= ($countExpiring > 0) ? 'border-color: rgba(225, 29, 72, 0.5);' : '' ?>">
        <div class="metric-label">Échéances sous 30 Jours</div>
        <div class="metric-value" style="color: <?= ($countExpiring > 0) ? '#fb7185' : '#34d399' ?>;">
            <?= $countExpiring ?>
        </div>
        <div style="font-size: 0.75rem; margin-top: 4px; color: <?= ($countExpiring > 0) ? '#fca5a5' : 'var(--text-muted)' ?>;">
            <?= ($countExpiring > 0) ? 'Action requise' : 'Aucune alerte' ?>
        </div>
    </div>
</div>

<!-- Alerte 30 Jours Sobre & Directe -->
<?php if ($countExpiring > 0): ?>
    <div class="alert-banner-30days">
        <div>
            <div class="alert-title">
                <i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px; color: var(--primary-glow);"></i>
                <?= $countExpiring ?> contrat(s) CDD arrive(nt) à échéance dans les 30 prochains jours
            </div>
            <div class="alert-desc">
                Pensez à renouveler les contrats ou à préparer les avenants avant le terme légal.
            </div>
        </div>
        <a href="contracts.php?filter=expiring" class="btn btn-primary btn-sm">
            Voir les échéances
        </a>
    </div>
<?php endif; ?>

<!-- Tableau Central Simplifié : Registre des Contrats & Salariés -->
<div class="card-glass">
    <div class="card-header-flex">
        <h3 class="card-title">
            <i class="fa-solid fa-file-signature"></i> Contrats Actifs & Alertes Récentes
        </h3>
        <a href="contracts.php" class="btn btn-secondary btn-sm">Voir tout</a>
    </div>

    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>Matricule & Salarié</th>
                    <th>Poste & Catégorie</th>
                    <th>Type Contrat</th>
                    <th>Salaire Net / Base</th>
                    <th>Échéance</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentContracts as $ctr): ?>
                    <tr>
                        <td>
                            <strong style="color: #ffffff;"><?= htmlspecialchars($ctr['nom'] . ' ' . $ctr['prenom']) ?></strong>
                            <div style="font-family: 'JetBrains Mono', monospace; font-size: 0.75rem; color: #fda4af;">
                                <?= htmlspecialchars($ctr['matricule']) ?>
                            </div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($ctr['poste']) ?></div>
                            <span class="badge <?= ($ctr['categorie'] === 'Cadre') ? 'badge-cat-cadre' : (($ctr['categorie'] === 'Agent de maîtrise') ? 'badge-cat-maitrise' : 'badge-cat-ouvrier') ?>">
                                <?= htmlspecialchars($ctr['categorie']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?= ($ctr['type_contrat'] === 'CDI') ? 'badge-cdi' : 'badge-cdd' ?>">
                                <?= htmlspecialchars($ctr['type_contrat']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= formatCurrency($ctr['salaire_base'], 'FCFA') ?></strong>
                            <span style="font-size: 0.72rem; color: var(--text-muted);">/ <?= ($ctr['type_remuneration'] === 'journalier') ? 'Jour' : 'Mois' ?></span>
                        </td>
                        <td>
                            <?php if ($ctr['type_contrat'] === 'CDD' && !empty($ctr['date_fin'])): ?>
                                <?php if ($ctr['jours_restants'] <= 15): ?>
                                    <span class="badge badge-urgent">Expire dans <?= $ctr['jours_restants'] ?>j</span>
                                <?php elseif ($ctr['jours_restants'] <= 30): ?>
                                    <span class="badge badge-cdd">Expire dans <?= $ctr['jours_restants'] ?>j</span>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= date('d/m/Y', strtotime($ctr['date_fin'])) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="font-size: 0.75rem; color: #34d399;">CDI Permanent</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <a href="view_contract.php?id=<?= $ctr['id'] ?>" class="btn btn-secondary btn-sm" title="Imprimer le contrat">
                                <i class="fa-solid fa-eye"></i> Contrat
                            </a>
                            <a href="generate_payslip.php?employee_id=<?= $ctr['employee_id'] ?>" class="btn btn-secondary btn-sm" title="Générer un bulletin">
                                <i class="fa-solid fa-receipt"></i> Paie
                            </a>
                            <a href="edit_contract.php?id=<?= $ctr['id'] ?>" class="btn btn-primary btn-sm" title="Modifier le contrat (place l'ancien en corbeille)">
                                <i class="fa-solid fa-pen-to-square"></i> Modifier
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
