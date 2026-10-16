<?php
// includes/header.php - Topbar & Head Simplifié (AGE Gabon)
require_once __DIR__ . '/../config/database.php';
$company = getCompanyConfig();
$expiringContracts = getExpiringContracts(30);
$countExpiring = count($expiringContracts);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' &mdash; AGE Gabon' : 'AGE - Application de Gestion des Employés' ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body>

<div class="app-layout">
    <?php include __DIR__ . '/sidebar.php'; ?>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle Navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="page-title-box">
                    <h1><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Tableau de bord' ?></h1>
                </div>
            </div>

            <div class="topbar-right">
                <div class="topbar-badge-company">
                    <i class="fa-solid fa-building" style="margin-right: 4px;"></i>
                    <span><?= htmlspecialchars($company['company_name'] ?? '') ?></span>
                </div>

                <a href="contracts.php?filter=expiring" class="notification-bell" title="Alertes échéances (30j)">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($countExpiring > 0): ?>
                        <span class="bell-badge"><?= $countExpiring ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <main class="content-body">
