<?php
// includes/sidebar.php - Navigation Latérale Épurée (AGE Gabon)
require_once __DIR__ . '/../config/database.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$company = getCompanyConfig();

$expiringContracts = getExpiringContracts(30);
$countExpiring = count($expiringContracts);

$db = getDB();
purgeExpiredTrashContracts();
$stmtTrash = $db->prepare("SELECT COUNT(*) FROM contracts c JOIN employees e ON e.id = c.employee_id WHERE c.status = 'corbeille' AND e.user_id = ?");
$stmtTrash->execute([(int)$_SESSION['user_id']]);
$countTrash = $stmtTrash->fetchColumn();
?>

<aside class="sidebar" id="sidebar">
    <!-- Logo AGE Latéral -->
    <a href="dashboard.php" class="sidebar-brand">
        <div class="logo-badge">AGE</div>
        <div class="brand-text">
            <span class="brand-title">AGE</span>
            <span class="brand-subtitle">Gabon RH</span>
        </div>
    </a>

    <!-- Boutons Latéraux Simplifiés -->
    <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-link <?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>Tableau de bord</span>
        </a>

        <a href="employees.php" class="nav-link <?= ($currentPage == 'employees.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-users"></i>
            <span>Employés</span>
        </a>

        <a href="contracts.php" class="nav-link <?= (in_array($currentPage, ['contracts.php', 'view_contract.php', 'edit_contract.php'])) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-signature"></i>
            <span>Contrats</span>
            <?php if ($countExpiring > 0): ?>
                <span class="badge-nav" title="<?= $countExpiring ?> contrat(s) CDD expire(nt) bientôt"><?= $countExpiring ?></span>
            <?php endif; ?>
        </a>

        <a href="payslips.php" class="nav-link <?= (in_array($currentPage, ['payslips.php', 'view_payslip.php', 'generate_payslip.php'])) ? 'active' : '' ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Fiches de Paie</span>
        </a>

        <a href="contracts_trash.php" class="nav-link <?= ($currentPage == 'contracts_trash.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-trash-can-arrow-up"></i>
            <span>Corbeille</span>
            <?php if ($countTrash > 0): ?>
                <span style="margin-left: auto; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; background: rgba(255,255,255,0.08); color: #cbd5e1;"><?= $countTrash ?></span>
            <?php endif; ?>
        </a>

        <a href="settings.php" class="nav-link <?= ($currentPage == 'settings.php') ? 'active' : '' ?>">
            <i class="fa-solid fa-gear"></i>
            <span>Configuration</span>
        </a>

        <a href="logout.php" class="nav-link" style="color: #fca5a5; margin-top: auto;">
            <i class="fa-solid fa-arrow-right-from-bracket" style="color: #f87171;"></i>
            <span>Déconnexion</span>
        </a>
    </div>

    <div class="sidebar-footer">
        <div style="font-weight: 600; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
            <?= htmlspecialchars($company['company_name'] ?? '') ?>
        </div>
        <div style="color: var(--text-muted); font-size: 0.7rem;"><?= htmlspecialchars($company['address'] ?? '') ?></div>
    </div>
</aside>
