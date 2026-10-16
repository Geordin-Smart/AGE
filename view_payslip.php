<?php
// view_payslip.php - Bulletin de Paie Gabonais Imprimable
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$payslipId = intval($_GET['id'] ?? 0);
if ($payslipId <= 0) {
    header("Location: payslips.php");
    exit;
}

$db = getDB();
$company = getCompanyConfig();

$stmt = $db->prepare("SELECT p.*, e.matricule, e.nom, e.prenom, e.email, e.telephone, e.adresse, e.poste, e.categorie, e.date_embauche, e.cnss_number, e.cnamgs_number
                      FROM payslips p 
                      JOIN employees e ON p.employee_id = e.id 
                      WHERE p.id = ? AND e.user_id = ?");
$stmt->execute([$payslipId, (int)$_SESSION['user_id']]);
$slip = $stmt->fetch();

if (!$slip) {
    header("Location: payslips.php?error=not_found");
    exit;
}

$totalRetenues = $slip['cotisations_sociales'] + $slip['retenues_fiscales'] + $slip['autres_retenues'];
$pdfRender = ($_GET['download'] ?? '') === 'pdf';

$pageTitle = "Bulletin " . htmlspecialchars($slip['slip_number']);
if ($pdfRender) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</title><style>' . file_get_contents(__DIR__ . '/assets/css/style.css') . '</style><style>' . file_get_contents(__DIR__ . '/assets/css/pdf-document-overrides.css') . '</style><style>@page{size:A4 portrait;margin:25mm}body{background:#fff!important;color:#111!important;padding:0!important;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif;font-size:9pt;line-height:1}.document-container{width:100%;max-width:none;margin:0;padding:0;box-shadow:none!important;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif;font-size:9pt;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.document-header{display:table;width:100%;padding-bottom:8px;margin-bottom:10px}.doc-logo-box{width:34px;height:34px}.document-company-logo,.document-badge-ref{display:table-cell;vertical-align:top}.document-container>div[style*="display: grid"]{display:table;width:100%;table-layout:fixed;gap:8px;margin-bottom:10px}.document-container>div[style*="display: grid"]>div{display:table-cell;width:50%;vertical-align:top}.contract-parties-box{padding:8px;margin-bottom:8px;font-size:9pt;line-height:1;page-break-inside:auto;overflow-wrap:break-word;word-wrap:break-word}.payslip-table{width:100%;border-collapse:collapse;margin:8px 0;page-break-inside:auto;table-layout:fixed;font-size:9pt;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.payslip-table th,.payslip-table td{padding:5px 7px;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.document-container>div[style*="margin: 16px 0"]{padding:7px 9px;margin:8px 0;font-size:9pt;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.contract-signatures{display:table;width:100%;margin-top:12px;padding-top:8px;page-break-inside:auto}.sig-box{display:table-cell;width:50%;vertical-align:top}.contract-signatures .sig-title{margin-bottom:20px}</style></head><body>';
} else {
    include __DIR__ . '/includes/header.php';
}
?>

<!-- Barre d'actions -->
<?php if (!$pdfRender): ?>
<div class="doc-action-bar">
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="payslips.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Retour
        </a>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            Bulletin : <strong style="color: #ffffff;"><?= htmlspecialchars($slip['slip_number']) ?></strong>
        </span>
    </div>

    <div style="display: flex; gap: 8px;">
        <a href="generate_payslip.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-plus"></i> Nouveau Bulletin
        </a>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-print"></i> Imprimer / PDF
        </button>
    </div>
</div>
<?php endif; ?>

<!-- BULLETIN DE PAIE GABON -->
<div class="document-container">

    <!-- En-tête Entreprise Gabonaise -->
    <div class="document-header">
        <div class="document-company-logo">
            <div class="doc-logo-box">AGE</div>
            <div>
                <div class="doc-company-name"><?= htmlspecialchars($company['company_name']) ?></div>
                <div class="doc-company-info">
                    <?= htmlspecialchars($company['address'] ?? '') ?><br>
                    Tél : <?= htmlspecialchars($company['phone'] ?? '') ?> &bull; NIF : <?= htmlspecialchars($company['nif'] ?? '') ?> &bull; RCCM : <?= htmlspecialchars($company['rccm'] ?? '') ?><br>
                    N° Employeur CNSS : <?= htmlspecialchars($company['cnss_employer'] ?? '') ?>
                </div>
            </div>
        </div>

        <div class="document-badge-ref">
            <div class="doc-badge-title">BULLETIN DE PAIE</div>
            <div class="doc-badge-val"><?= htmlspecialchars($slip['period_label']) ?></div>
            <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">
                N° : <?= htmlspecialchars($slip['slip_number']) ?> &bull; <?= htmlspecialchars(getCompanyLocationLabel($company)) ?>
            </div>
        </div>
    </div>

    <!-- Données Salarié Gabonais -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
        <div class="contract-parties-box" style="margin-bottom: 0;">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">
                SALARIÉ
            </div>
            <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                <?= htmlspecialchars($slip['nom'] . ' ' . $slip['prenom']) ?>
            </div>
            <div style="font-size: 0.82rem; color: #334155; margin-top: 4px;">
                <strong>Matricule :</strong> <?= htmlspecialchars($slip['matricule']) ?><br>
                <strong>Poste :</strong> <?= htmlspecialchars($slip['poste']) ?> (<?= htmlspecialchars($slip['categorie']) ?>)<br>
                <strong>N° CNSS :</strong> <?= htmlspecialchars($slip['cnss_number'] ?? 'En cours') ?> &bull; <strong>CNAMGS :</strong> <?= htmlspecialchars($slip['cnamgs_number'] ?? 'Assuré') ?>
            </div>
        </div>

        <div class="contract-parties-box" style="margin-bottom: 0;">
            <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">
                MODALITÉS DE PAIE
            </div>
            <div style="font-size: 0.82rem; color: #334155; line-height: 1.6;">
                <strong>Période :</strong> <?= htmlspecialchars($slip['period_label']) ?><br>
                <strong>Mode :</strong> Paie <?= htmlspecialchars($slip['type_remuneration']) ?> (<?= number_format($slip['nb_jours_travailles'], 1) ?> j comptabilisés)<br>
                <strong>Règlement :</strong> <?= htmlspecialchars($slip['mode_paiement']) ?><br>
                <strong>Date valeur :</strong> <?= date('d/m/Y', strtotime($slip['generated_at'])) ?>
            </div>
        </div>
    </div>

    <!-- Tableau de Salaire Conforme Gabon -->
    <table class="payslip-table">
        <thead>
            <tr>
                <th style="width: 50%;">Désignation des Rubriques</th>
                <th style="width: 25%; text-align: right;">Gains (FCFA)</th>
                <th style="width: 25%; text-align: right;">Retenues (FCFA)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Salaire de Base -->
            <tr>
                <td>
                    <strong>Salaire de Base</strong>
                    <span style="font-size: 0.72rem; color: #64748b; display: block;">
                        <?= ($slip['type_remuneration'] === 'journalier') ? number_format($slip['nb_jours_travailles'], 1) . ' jours à ' . number_format($slip['taux_journalier_ou_base'], 0, ',', ' ') . ' FCFA' : 'Base mensuelle conventionnelle' ?>
                    </span>
                </td>
                <td style="text-align: right; font-weight: 600;">
                    <?php 
                        $baseLigne = ($slip['type_remuneration'] === 'journalier') ? ($slip['taux_journalier_ou_base'] * $slip['nb_jours_travailles']) : $slip['taux_journalier_ou_base'];
                        echo number_format($baseLigne, 0, ',', ' ');
                    ?>
                </td>
                <td style="text-align: right; color: #64748b;">&mdash;</td>
            </tr>

            <!-- Primes -->
            <?php if ($slip['primes'] > 0): ?>
                <tr>
                    <td>Primes & Sursalaire</td>
                    <td style="text-align: right; font-weight: 600;"><?= number_format($slip['primes'], 0, ',', ' ') ?></td>
                    <td style="text-align: right; color: #64748b;">&mdash;</td>
                </tr>
            <?php endif; ?>

            <!-- Indemnité Transport -->
            <?php if ($slip['indemnites'] > 0): ?>
                <tr>
                    <td>Indemnité Légale de Transport (Gabon)</td>
                    <td style="text-align: right; font-weight: 600;"><?= number_format($slip['indemnites'], 0, ',', ' ') ?></td>
                    <td style="text-align: right; color: #64748b;">&mdash;</td>
                </tr>
            <?php endif; ?>

            <!-- Sous-total Brut -->
            <tr style="background: #f8fafc; font-weight: 700;">
                <td>TOTAL SALAIRE BRUT</td>
                <td style="text-align: right; color: #0f172a;"><?= number_format($slip['salaire_brut'], 0, ',', ' ') ?></td>
                <td style="text-align: right; color: #64748b;">&mdash;</td>
            </tr>

            <!-- Cotisations Sociales Gabon (CNSS 2.5% + CNAMGS 1%) -->
            <?php if ($slip['cotisations_sociales'] > 0): ?>
                <tr>
                    <td>
                        Cotisations Sociales Salariales
                        <span style="font-size: 0.72rem; color: #64748b; display: block;">CNSS (2,5%) + CNAMGS (1%)</span>
                    </td>
                    <td style="text-align: right; color: #64748b;">&mdash;</td>
                    <td style="text-align: right; color: #dc2626; font-weight: 600;">
                        <?= number_format($slip['cotisations_sociales'], 0, ',', ' ') ?>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- Retenues Fiscales Gabon (IRPP / TCS) -->
            <?php if ($slip['retenues_fiscales'] > 0): ?>
                <tr>
                    <td>
                        Retenues Fiscales Légales
                        <span style="font-size: 0.72rem; color: #64748b; display: block;">IRPP & Taxe Complémentaire sur Salaire (TCS)</span>
                    </td>
                    <td style="text-align: right; color: #64748b;">&mdash;</td>
                    <td style="text-align: right; color: #dc2626; font-weight: 600;">
                        <?= number_format($slip['retenues_fiscales'], 0, ',', ' ') ?>
                    </td>
                </tr>
            <?php endif; ?>

            <!-- Total Retenues -->
            <tr style="background: #f8fafc; font-weight: 700;">
                <td>TOTAL DES RETENUES LÉGALES</td>
                <td style="text-align: right; color: #64748b;">&mdash;</td>
                <td style="text-align: right; color: #dc2626;"><?= number_format($totalRetenues, 0, ',', ' ') ?></td>
            </tr>

            <!-- NET À PAYER -->
            <tr class="payslip-total-row">
                <td style="font-size: 1rem; font-weight: 800; text-transform: uppercase;">
                    NET À PAYER (FCFA)
                </td>
                <td colspan="2" style="text-align: right; font-size: 1.25rem; font-weight: 800; color: #881337;">
                    <?= formatCurrency($slip['salaire_net'], 'FCFA') ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Montant en Lettres -->
    <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; padding: 10px 14px; margin: 16px 0; font-size: 0.85rem; color: #881337;">
        <strong>Net arrêté à la somme de :</strong> 
        <span style="font-style: italic; font-weight: 700;">
            <?= ucfirst(nombreEnLettres($slip['salaire_net'])) ?> Francs CFA.
        </span>
    </div>

    <!-- Signatures -->
    <div class="contract-signatures" style="margin-top: 28px; padding-top: 16px;">
        <div class="sig-box">
            <div class="sig-title">Cachet & Signature de l'Employeur</div>
            <div class="signature-space"></div>
            <div class="sig-line">Direction des Ressources Humaines &bull; <?= htmlspecialchars(getCompanyLocationLabel($company)) ?></div>
        </div>

        <div class="sig-box">
            <div class="sig-title">Émargement du Salarié</div>
            <div class="signature-space"></div>
            <div class="sig-line"><?= htmlspecialchars($slip['nom'] . ' ' . $slip['prenom']) ?></div>
        </div>
    </div>

</div>

<?php if ($pdfRender): ?>
</body></html>
<?php else: ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
