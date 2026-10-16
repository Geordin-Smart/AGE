<?php
// batch_print.php - Impression groupée des contrats et fiches de paie
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$db = getDB();
$company = getCompanyConfig();
$documentType = $_POST['document_type'] ?? '';
$employeeIds = array_values(array_unique(array_filter(array_map('intval', $_POST['employee_ids'] ?? []))));

if (!in_array($documentType, ['contracts', 'payslips'], true) || empty($employeeIds)) {
    header('Location: employees.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
$documents = [];
$missingEmployees = [];

if ($documentType === 'contracts') {
    $stmt = $db->prepare("SELECT c.*, e.nom, e.prenom, e.matricule, e.adresse, e.telephone, e.cnss_number
        FROM contracts c
        JOIN employees e ON e.id = c.employee_id
        WHERE c.employee_id IN ($placeholders) AND c.status = 'actif' AND e.user_id = ?
        ORDER BY e.nom, e.prenom, c.id DESC");
    $stmt->execute(array_merge($employeeIds, [(int)$_SESSION['user_id']]));
    $documents = $stmt->fetchAll();
} else {
    $stmt = $db->prepare("SELECT p.*, e.nom, e.prenom, e.matricule, e.poste, e.categorie, e.cnss_number, e.cnamgs_number
        FROM payslips p
        JOIN employees e ON e.id = p.employee_id
        LEFT JOIN payslips newer ON newer.employee_id = p.employee_id AND newer.id > p.id
        WHERE p.employee_id IN ($placeholders) AND newer.id IS NULL AND e.user_id = ?
        ORDER BY e.nom, e.prenom");
    $stmt->execute(array_merge($employeeIds, [(int)$_SESSION['user_id']]));
    $documents = $stmt->fetchAll();
}

$foundIds = array_map('intval', array_column($documents, 'employee_id'));
$missingIds = array_diff($employeeIds, $foundIds);
if (!empty($missingIds)) {
    $missingPlaceholders = implode(',', array_fill(0, count($missingIds), '?'));
    $missingStmt = $db->prepare("SELECT id, nom, prenom FROM employees WHERE id IN ($missingPlaceholders) AND user_id = ? ORDER BY nom, prenom");
    $missingStmt->execute(array_merge(array_values($missingIds), [(int)$_SESSION['user_id']]));
    $missingEmployees = $missingStmt->fetchAll();
}

$pageTitle = $documentType === 'contracts' ? 'Impression groupée des contrats' : 'Impression groupée des fiches de paie';
include __DIR__ . '/includes/header.php';
?>

<div class="doc-action-bar no-print">
    <a href="employees.php" class="btn btn-secondary btn-sm"><i class="fa-solid fa-arrow-left"></i> Retour aux employés</a>
    <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fa-solid fa-print"></i> Imprimer tout</button>
</div>

<?php if (!empty($missingEmployees)): ?>
    <div class="no-print" style="background: rgba(245, 158, 11, 0.15); border: 1px solid #f59e0b; color: #fcd34d; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        Aucun document trouvé pour :
        <?= htmlspecialchars(implode(', ', array_map(static fn($employee) => $employee['nom'] . ' ' . $employee['prenom'], $missingEmployees))) ?>.
    </div>
<?php endif; ?>

<?php foreach ($documents as $document): ?>
    <article class="document-container batch-document">
        <div class="document-header">
            <div class="document-company-logo">
                <div class="doc-logo-box">AGE</div>
                <div>
                    <div class="doc-company-name"><?= htmlspecialchars($company['company_name'] ?? '') ?></div>
                    <div class="doc-company-info">
                        <?= htmlspecialchars($company['address'] ?? '') ?><br>
                        Tél : <?= htmlspecialchars($company['phone'] ?? '') ?> &bull; NIF : <?= htmlspecialchars($company['nif'] ?? '') ?> &bull; RCCM : <?= htmlspecialchars($company['rccm'] ?? '') ?><br>
                        N° Employeur CNSS : <?= htmlspecialchars($company['cnss_employer'] ?? '') ?>
                    </div>
                </div>
            </div>
            <div class="document-badge-ref">
                <?php if ($documentType === 'contracts'): ?>
                    <div class="doc-badge-title">RÉPUBLIQUE GABONAISE</div>
                    <div style="font-size: 0.72rem; color: #64748b; font-style: italic;">Union - Travail - Justice</div>
                    <div class="doc-badge-val" style="margin-top: 4px;"><?= htmlspecialchars($document['contract_number']) ?></div>
                <?php else: ?>
                    <div class="doc-badge-title">BULLETIN DE PAIE</div>
                    <div class="doc-badge-val"><?= htmlspecialchars($document['period_label']) ?></div>
                    <div style="font-size: 0.72rem; color: #64748b; margin-top: 3px;">
                        N° : <?= htmlspecialchars($document['slip_number']) ?> &bull; <?= htmlspecialchars(getCompanyLocationLabel($company)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($documentType === 'contracts'): ?>
            <div class="contract-title-center">
                <h1 class="contract-main-heading">
                    CONTRAT DE TRAVAIL À DURÉE <?= ($document['type_contrat'] === 'CDI') ? 'INDÉTERMINÉE (CDI)' : 'DÉTERMINÉE (CDD)' ?>
                </h1>
                <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">
                    Conformément à la Loi n° 022/2021 portant Code du Travail en République Gabonaise
                </div>
            </div>

            <div class="contract-parties-box">
                <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">ENTRE LES SOUSSIGNÉS :</div>
                <p style="margin-bottom: 8px;">
                    <strong>1. L'Employeur :</strong> <strong><?= htmlspecialchars($company['company_name'] ?? '') ?></strong>,
                    ayant son siège social à <?= htmlspecialchars($company['address'] ?? '') ?>,
                    immatriculée au RCCM de <?= htmlspecialchars(getCompanyLocationLabel($company)) ?> sous le <?= htmlspecialchars($company['rccm'] ?? '') ?>, NIF <?= htmlspecialchars($company['nif'] ?? '') ?>,
                    représentée par la Direction des Ressources Humaines.
                </p>
                <p>
                    <strong>2. Le Salarié :</strong> M./Mme <strong><?= htmlspecialchars($document['nom'] . ' ' . $document['prenom']) ?></strong>,
                    Matricule <strong><?= htmlspecialchars($document['matricule']) ?></strong>,
                    demeurant à <?= htmlspecialchars($document['adresse'] ?? '') ?>,
                    N° CNSS : <?= htmlspecialchars($document['cnss_number'] ?? 'En cours d\'immatriculation') ?>,
                    Téléphone : <?= htmlspecialchars($document['telephone'] ?? '-') ?>.
                </p>
            </div>

            <div class="contract-article">
                <div class="contract-article-title">ARTICLE 1 &mdash; ENGAGEMENT & FONCTION</div>
                <div class="contract-article-content">
                    L'Employeur recrute le Salarié en qualité de <strong><?= htmlspecialchars($document['poste']) ?></strong>,
                    classé dans la catégorie professionnelle <strong><?= htmlspecialchars($document['categorie'] ?: 'À préciser') ?></strong>.
                </div>
            </div>

            <div class="contract-article">
                <div class="contract-article-title">ARTICLE 2 &mdash; DURÉE DU CONTRAT</div>
                <div class="contract-article-content">
                    <?php if ($document['type_contrat'] === 'CDI'): ?>
                        Le présent contrat est conclu pour une <strong>durée indéterminée</strong> prenant effet le <strong><?= date('d/m/Y', strtotime($document['date_debut'])) ?></strong>.
                    <?php else: ?>
                        Le présent contrat est conclu pour une <strong>durée déterminée</strong> prenant effet le <strong><?= date('d/m/Y', strtotime($document['date_debut'])) ?></strong>
                        et prendra fin le <strong><?= date('d/m/Y', strtotime($document['date_fin'])) ?></strong>.
                    <?php endif; ?>
                </div>
            </div>

            <div class="contract-article">
                <div class="contract-article-title">ARTICLE 3 &mdash; RÉMUNÉRATION (FCFA)</div>
                <div class="contract-article-content">
                    En contrepartie de l'exercice de ses fonctions, le Salarié percevra une rémunération brute de base fixée à :
                    <div style="margin: 6px 0; padding: 8px 12px; background: #fff1f2; border-radius: 4px; font-weight: 700; color: #881337;">
                        <?= formatCurrency($document['salaire_base'], 'FCFA') ?> / <?= ($document['type_remuneration'] === 'journalier') ? 'Jour ouvré' : 'Mois' ?>
                        <span style="font-weight: 400; font-size: 0.8rem; color: #64748b; margin-left: 8px;">
                            (Soit : <?= ucfirst(nombreEnLettres($document['salaire_base'])) ?> Francs CFA)
                        </span>
                    </div>
                    Cette rémunération est soumise aux cotisations légales obligatoires en vigueur en République Gabonaise (CNSS et CNAMGS).
                </div>
            </div>

            <div class="contract-article">
                <div class="contract-article-title">ARTICLE 4 &mdash; JURIDICTION COMPÉTENTE</div>
                <div class="contract-article-content">
                    Les différends individuels nés de l'exécution ou de la rupture du présent contrat relèvent de la compétence exclusive du
                    <strong>Tribunal du Travail de <?= htmlspecialchars(getCompanyLocationLabel($company)) ?></strong>, après tentative de conciliation préalable devant l'Inspection du Travail du ressort territorial.
                </div>
            </div>

            <div class="contract-signatures">
                <div class="sig-box">
                    <div class="sig-title">Pour la Société <?= htmlspecialchars($company['company_name'] ?? '') ?></div>
                    <div style="font-size: 0.75rem; color: #64748b;">La Direction Générale</div>
                    <div class="signature-space"></div>
                    <div class="sig-line">Cachet & Signature</div>
                </div>

                <div class="sig-box">
                    <div class="sig-title">Le Salarié</div>
                    <div style="font-size: 0.75rem; color: #64748b;">(Mention manuscrite <em>« Lu et approuvé »</em>)</div>
                    <div class="signature-space"></div>
                    <div class="sig-line"><?= htmlspecialchars($document['nom'] . ' ' . $document['prenom']) ?></div>
                </div>
            </div>
        <?php else: $totalRetenues = $document['cotisations_sociales'] + $document['retenues_fiscales'] + $document['autres_retenues']; ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="contract-parties-box" style="margin-bottom: 0;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">
                        SALARIÉ
                    </div>
                    <div style="font-size: 1rem; font-weight: 800; color: #0f172a;">
                        <?= htmlspecialchars($document['nom'] . ' ' . $document['prenom']) ?>
                    </div>
                    <div style="font-size: 0.82rem; color: #334155; margin-top: 4px;">
                        <strong>Matricule :</strong> <?= htmlspecialchars($document['matricule']) ?><br>
                        <strong>Poste :</strong> <?= htmlspecialchars($document['poste']) ?> (<?= htmlspecialchars($document['categorie']) ?>)<br>
                        <strong>N° CNSS :</strong> <?= htmlspecialchars($document['cnss_number'] ?? 'En cours') ?> &bull; <strong>CNAMGS :</strong> <?= htmlspecialchars($document['cnamgs_number'] ?? 'Assuré') ?>
                    </div>
                </div>

                <div class="contract-parties-box" style="margin-bottom: 0;">
                    <div style="font-size: 0.72rem; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px;">
                        MODALITÉS DE PAIE
                    </div>
                    <div style="font-size: 0.82rem; color: #334155; line-height: 1.6;">
                        <strong>Période :</strong> <?= htmlspecialchars($document['period_label']) ?><br>
                        <strong>Mode :</strong> Paie <?= htmlspecialchars($document['type_remuneration']) ?> (<?= number_format($document['nb_jours_travailles'], 1) ?> j comptabilisés)<br>
                        <strong>Règlement :</strong> <?= htmlspecialchars($document['mode_paiement']) ?><br>
                        <strong>Date valeur :</strong> <?= date('d/m/Y', strtotime($document['generated_at'])) ?>
                    </div>
                </div>
            </div>

            <table class="payslip-table">
                <thead>
                    <tr>
                        <th style="width: 50%;">Désignation des Rubriques</th>
                        <th style="width: 25%; text-align: right;">Gains (FCFA)</th>
                        <th style="width: 25%; text-align: right;">Retenues (FCFA)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <strong>Salaire de Base</strong>
                            <span style="font-size: 0.72rem; color: #64748b; display: block;">
                                <?= ($document['type_remuneration'] === 'journalier') ? number_format($document['nb_jours_travailles'], 1) . ' jours à ' . number_format($document['taux_journalier_ou_base'], 0, ',', ' ') . ' FCFA' : 'Base mensuelle conventionnelle' ?>
                            </span>
                        </td>
                        <td style="text-align: right; font-weight: 600;">
                            <?php
                                $baseLigne = ($document['type_remuneration'] === 'journalier') ? ($document['taux_journalier_ou_base'] * $document['nb_jours_travailles']) : $document['taux_journalier_ou_base'];
                                echo number_format($baseLigne, 0, ',', ' ');
                            ?>
                        </td>
                        <td style="text-align: right; color: #64748b;">&mdash;</td>
                    </tr>

                    <?php if ($document['primes'] > 0): ?>
                        <tr>
                            <td>Primes & Sursalaire</td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format($document['primes'], 0, ',', ' ') ?></td>
                            <td style="text-align: right; color: #64748b;">&mdash;</td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($document['indemnites'] > 0): ?>
                        <tr>
                            <td>Indemnité Légale de Transport (Gabon)</td>
                            <td style="text-align: right; font-weight: 600;"><?= number_format($document['indemnites'], 0, ',', ' ') ?></td>
                            <td style="text-align: right; color: #64748b;">&mdash;</td>
                        </tr>
                    <?php endif; ?>

                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td>TOTAL SALAIRE BRUT</td>
                        <td style="text-align: right; color: #0f172a;"><?= number_format($document['salaire_brut'], 0, ',', ' ') ?></td>
                        <td style="text-align: right; color: #64748b;">&mdash;</td>
                    </tr>

                    <?php if ($document['cotisations_sociales'] > 0): ?>
                        <tr>
                            <td>
                                Cotisations Sociales Salariales
                                <span style="font-size: 0.72rem; color: #64748b; display: block;">CNSS (2,5%) + CNAMGS (1%)</span>
                            </td>
                            <td style="text-align: right; color: #64748b;">&mdash;</td>
                            <td style="text-align: right; color: #dc2626; font-weight: 600;">
                                <?= number_format($document['cotisations_sociales'], 0, ',', ' ') ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($document['retenues_fiscales'] > 0): ?>
                        <tr>
                            <td>
                                Retenues Fiscales Légales
                                <span style="font-size: 0.72rem; color: #64748b; display: block;">IRPP & Taxe Complémentaire sur Salaire (TCS)</span>
                            </td>
                            <td style="text-align: right; color: #64748b;">&mdash;</td>
                            <td style="text-align: right; color: #dc2626; font-weight: 600;">
                                <?= number_format($document['retenues_fiscales'], 0, ',', ' ') ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php if ($document['autres_retenues'] > 0): ?>
                        <tr>
                            <td>Autres retenues</td>
                            <td style="text-align: right; color: #64748b;">&mdash;</td>
                            <td style="text-align: right; color: #dc2626; font-weight: 600;">
                                <?= number_format($document['autres_retenues'], 0, ',', ' ') ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr style="background: #f8fafc; font-weight: 700;">
                        <td>TOTAL DES RETENUES LÉGALES</td>
                        <td style="text-align: right; color: #64748b;">&mdash;</td>
                        <td style="text-align: right; color: #dc2626;"><?= number_format($totalRetenues, 0, ',', ' ') ?></td>
                    </tr>

                    <tr class="payslip-total-row">
                        <td style="font-size: 1rem; font-weight: 800; text-transform: uppercase;">NET À PAYER (FCFA)</td>
                        <td colspan="2" style="text-align: right; font-size: 1.25rem; font-weight: 800; color: #881337;">
                            <?= formatCurrency($document['salaire_net'], 'FCFA') ?>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 4px; padding: 10px 14px; margin: 16px 0; font-size: 0.85rem; color: #881337;">
                <strong>Net arrêté à la somme de :</strong>
                <span style="font-style: italic; font-weight: 700;">
                    <?= ucfirst(nombreEnLettres($document['salaire_net'])) ?> Francs CFA.
                </span>
            </div>

            <div class="contract-signatures" style="margin-top: 28px; padding-top: 16px;">
                <div class="sig-box">
                    <div class="sig-title">Cachet & Signature de l'Employeur</div>
                    <div class="signature-space"></div>
                    <div class="sig-line">Direction des Ressources Humaines &bull; <?= htmlspecialchars(getCompanyLocationLabel($company)) ?></div>
                </div>

                <div class="sig-box">
                    <div class="sig-title">Émargement du Salarié</div>
                    <div class="signature-space"></div>
                    <div class="sig-line"><?= htmlspecialchars($document['nom'] . ' ' . $document['prenom']) ?></div>
                </div>
            </div>
        <?php endif; ?>
    </article>
<?php endforeach; ?>

<style>
    @media print {
        .no-print, .sidebar, .topbar { display: none !important; }
        .main-wrapper { margin: 0 !important; }
        .content-body { padding: 0 !important; }
        .batch-document {
            break-after: page;
            break-inside: auto;
            page-break-inside: auto;
            box-shadow: none !important;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .batch-document:last-of-type { break-after: auto; }
    }
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>