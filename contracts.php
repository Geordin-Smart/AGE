<?php
// contracts.php - Registre Épuré des Contrats & Alertes 30j (Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Contrats de Travail";
$db = getDB();
$company = getCompanyConfig();

$filter = $_GET['filter'] ?? 'all';
$typeFilter = $_GET['type'] ?? '';

$sql = "SELECT c.*, e.matricule, e.nom, e.prenom, e.email, e.telephone,
        DATEDIFF(c.date_fin, CURDATE()) as jours_restants
        FROM contracts c
        JOIN employees e ON c.employee_id = e.id
        WHERE c.status = 'actif' AND e.user_id = ?";

    $params = [(int)$_SESSION['user_id']];
if ($filter === 'expiring') {
    $sql .= " AND c.type_contrat = 'CDD' AND c.date_fin IS NOT NULL AND DATEDIFF(c.date_fin, CURDATE()) <= 30";
}
if (!empty($typeFilter)) {
    $sql .= " AND c.type_contrat = ?";
    $params[] = $typeFilter;
}
$sql .= " ORDER BY (CASE WHEN c.date_fin IS NOT NULL THEN c.date_fin ELSE '9999-12-31' END) ASC, c.id DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$contracts = $stmt->fetchAll();

$countExpiring = count(getExpiringContracts(30));
include __DIR__ . '/includes/header.php';
?>

<!-- En-tête -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Contrats de Travail Actifs</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);">Suivi des contrats et gestion des échéances de fin de mission</p>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="employees.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-plus"></i> Nouveau Salarié
        </a>
        <a href="contracts_trash.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-trash-can-arrow-up"></i> Corbeille Contrats
        </a>
    </div>
</div>

<!-- Filtres Rapides Épurés -->
<div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
    <a href="contracts.php" class="btn <?= ($filter === 'all' && empty($typeFilter)) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        Tous les Actifs
    </a>
    <a href="contracts.php?type=CDI" class="btn <?= ($typeFilter === 'CDI') ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        CDI
    </a>
    <a href="contracts.php?type=CDD" class="btn <?= ($typeFilter === 'CDD' && $filter !== 'expiring') ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        CDD
    </a>
    <a href="contracts.php?filter=expiring" class="btn <?= ($filter === 'expiring') ? 'btn-primary' : 'btn-secondary' ?> btn-sm">
        <i class="fa-solid fa-bell"></i> Échéance < 30 Jours (<?= $countExpiring ?>)
    </a>
</div>

<!-- Tableau Épuré -->
<div class="card-glass">
    <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th>N° Contrat</th>
                    <th>Salarié & Matricule</th>
                    <th>Poste & Catégorie</th>
                    <th>Type</th>
                    <th>Salaire Base</th>
                    <th>Période & Échéance</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($contracts)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">
                            Aucun contrat actif correspondant.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($contracts as $ctr): ?>
                        <tr>
                            <td>
                                <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #fda4af;">
                                    <?= htmlspecialchars($ctr['contract_number']) ?>
                                </span>
                            </td>
                            <td>
                                <strong style="color: #ffffff;"><?= htmlspecialchars($ctr['nom'] . ' ' . $ctr['prenom']) ?></strong>
                                <div style="font-size: 0.72rem; color: var(--text-muted); font-family: 'JetBrains Mono', monospace;">
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
                                <span style="font-size: 0.72rem; color: var(--text-muted);">/ <?= $ctr['type_remuneration'] ?></span>
                            </td>
                            <td>
                                <?php if ($ctr['type_contrat'] === 'CDD' && !empty($ctr['date_fin'])): ?>
                                    <div style="font-size: 0.8rem;">Fin : <?= date('d/m/Y', strtotime($ctr['date_fin'])) ?></div>
                                    <?php if ($ctr['jours_restants'] <= 15): ?>
                                        <span class="badge badge-urgent">Expire dans <?= $ctr['jours_restants'] ?>j</span>
                                    <?php elseif ($ctr['jours_restants'] <= 30): ?>
                                        <span class="badge badge-cdd">Expire dans <?= $ctr['jours_restants'] ?>j</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.72rem; color: var(--text-muted);"><?= $ctr['jours_restants'] ?>j restants</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="font-size: 0.78rem; color: #34d399;">Début : <?= date('d/m/Y', strtotime($ctr['date_debut'])) ?> (CDI)</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <a href="view_contract.php?id=<?= $ctr['id'] ?>" class="btn btn-secondary btn-sm" title="Imprimer le contrat">
                                    <i class="fa-solid fa-eye"></i> Voir
                                </a>
                                <a href="edit_contract.php?id=<?= $ctr['id'] ?>" class="btn btn-primary btn-sm" title="Modifier le contrat (envoie l'ancien en corbeille)">
                                    <i class="fa-solid fa-pen-to-square"></i> Modifier
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
