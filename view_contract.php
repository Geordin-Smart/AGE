<?php
// view_contract.php - Contrat de Travail Gabonais Imprimable
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$contractId = intval($_GET['id'] ?? 0);
if ($contractId <= 0) {
    header("Location: contracts.php");
    exit;
}

$db = getDB();
$company = getCompanyConfig();

$stmt = $db->prepare("SELECT c.*, e.matricule, e.nom, e.prenom, e.email, e.telephone, e.adresse, e.date_naissance, e.cnss_number
                      FROM contracts c 
                      JOIN employees e ON c.employee_id = e.id 
                      WHERE c.id = ? AND e.user_id = ?");
$stmt->execute([$contractId, (int)$_SESSION['user_id']]);
$contract = $stmt->fetch();

if (!$contract) {
    header("Location: contracts.php?error=not_found");
    exit;
}

$isArchived = ($contract['status'] === 'corbeille');
$pdfRender = ($_GET['download'] ?? '') === 'pdf';
$pageTitle = "Contrat " . htmlspecialchars($contract['contract_number']);
if ($pdfRender) {
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') . '</title><style>' . file_get_contents(__DIR__ . '/assets/css/style.css') . '</style><style>' . file_get_contents(__DIR__ . '/assets/css/pdf-document-overrides.css') . '</style><style>@page{size:A4 portrait;margin:25mm}body{background:#fff!important;color:#111!important;padding:0!important;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif;font-size:9pt;line-height:1}.document-container{width:100%;max-width:none;margin:0;padding:0;box-shadow:none!important;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif;font-size:9pt;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.document-header{padding-bottom:8px;margin-bottom:10px}.doc-logo-box{width:34px;height:34px}.contract-title-center{margin:10px 0}.contract-main-heading{font-size:12pt;overflow-wrap:break-word;word-wrap:break-word}.document-header,.contract-signatures{display:table;width:100%}.document-company-logo,.document-badge-ref,.sig-box{display:table-cell;vertical-align:top}.sig-box{width:50%}.contract-parties-box{padding:8px;margin-bottom:8px;font-size:9pt;line-height:1;page-break-inside:auto;overflow-wrap:break-word;word-wrap:break-word}.contract-parties-box p{margin-bottom:4px}.contract-article{margin-bottom:14px;page-break-inside:auto;overflow-wrap:break-word;word-wrap:break-word}.contract-article-title{font-size:9pt;padding-bottom:4px;margin-bottom:6px}.contract-article-content{font-size:9pt;line-height:1;overflow-wrap:break-word;word-wrap:break-word}.contract-signatures{margin-top:12px;padding-top:8px;page-break-inside:auto}.contract-signatures .sig-title{margin-bottom:20px}</style></head><body>';
} else {
    include __DIR__ . '/includes/header.php';
}
?>

<!-- Barre d'actions -->
<?php if (!$pdfRender): ?>
<div class="doc-action-bar">
    <div style="display: flex; gap: 8px; align-items: center;">
        <a href="<?= $isArchived ? 'contracts_trash.php' : 'contracts.php' ?>" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Retour
        </a>
        <span style="font-size: 0.85rem; color: var(--text-muted);">
            <?= htmlspecialchars($contract['contract_number']) ?>
        </span>
        <?php if ($isArchived): ?>
            <span class="badge badge-trash">Archivé en Corbeille</span>
        <?php else: ?>
            <span class="badge badge-cdi">Actif</span>
        <?php endif; ?>
    </div>

    <div style="display: flex; gap: 8px;">
        <?php if (!$isArchived): ?>
            <a href="edit_contract.php?id=<?= $contract['id'] ?>" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-pen-to-square"></i> Modifier (Archive l'actuel)
            </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-print"></i> Imprimer / PDF
        </button>
    </div>
</div>
<?php endif; ?>

<!-- DOCUMENT CONTRAT DE TRAVAIL GABONAIS -->
<div class="document-container">

    <!-- En-tête Gabon & Entreprise -->
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
            <div class="doc-badge-title">RÉPUBLIQUE GABONAISE</div>
            <div style="font-size: 0.72rem; color: #64748b; font-style: italic;">Union - Travail - Justice</div>
            <div class="doc-badge-val" style="margin-top: 4px;"><?= htmlspecialchars($contract['contract_number']) ?></div>
        </div>
    </div>

    <?php if ($isArchived): ?>
        <div style="background: #fee2e2; border: 1px solid #ef4444; color: #991b1b; padding: 8px 12px; border-radius: 6px; margin-bottom: 16px; font-size: 0.8rem; font-weight: 700; text-align: center;">
            DOCUMENT D'ARCHIVE &mdash; CE CONTRAT EST CLASSÉ EN CORBEILLE (Remplacé le <?= date('d/m/Y', strtotime($contract['corbeille_date'])) ?>)
        </div>
    <?php endif; ?>

    <!-- Titre -->
    <div class="contract-title-center">
        <h1 class="contract-main-heading">
            CONTRAT DE TRAVAIL À DURÉE <?= ($contract['type_contrat'] === 'CDI') ? 'INDÉTERMINÉE (CDI)' : 'DÉTERMINÉE (CDD)' ?>
        </h1>
        <div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">
            Conformément à la Loi n° 022/2021 portant Code du Travail en République Gabonaise
        </div>
    </div>

    <!-- Parties Contractantes -->
    <div class="contract-parties-box">
        <div style="font-weight: 700; color: #0f172a; margin-bottom: 6px;">ENTRE LES SOUSSIGNÉS :</div>
        <p style="margin-bottom: 8px;">
            <strong>1. L'Employeur :</strong> <strong><?= htmlspecialchars($company['company_name']) ?></strong>, 
            ayant son siège social à <?= htmlspecialchars($company['address']) ?>, 
            immatriculée au RCCM de <?= htmlspecialchars(getCompanyLocationLabel($company)) ?> sous le <?= htmlspecialchars($company['rccm'] ?? '') ?>, NIF <?= htmlspecialchars($company['nif'] ?? '') ?>,
            représentée par la Direction des Ressources Humaines.
        </p>
        <p>
            <strong>2. Le Salarié :</strong> M./Mme <strong><?= htmlspecialchars($contract['nom'] . ' ' . $contract['prenom']) ?></strong>, 
            Matricule <strong><?= htmlspecialchars($contract['matricule']) ?></strong>, 
            demeurant à <?= htmlspecialchars($contract['adresse']) ?>, 
            N° CNSS : <?= htmlspecialchars($contract['cnss_number'] ?? 'En cours d\'immatriculation') ?>, 
            Téléphone : <?= htmlspecialchars($contract['telephone'] ?? '-') ?>.
        </p>
    </div>

    <!-- Articles Simplifiés & Légaux -->
    <div class="contract-article">
        <div class="contract-article-title">ARTICLE 1 &mdash; ENGAGEMENT & FONCTION</div>
        <div class="contract-article-content">
            L'Employeur recrute le Salarié en qualité de <strong><?= htmlspecialchars($contract['poste']) ?></strong>, 
            classé dans la catégorie professionnelle <strong><?= htmlspecialchars($contract['categorie'] ?: 'À préciser') ?></strong>.
        </div>
    </div>

    <div class="contract-article">
        <div class="contract-article-title">ARTICLE 2 &mdash; DURÉE DU CONTRAT</div>
        <div class="contract-article-content">
            <?php if ($contract['type_contrat'] === 'CDI'): ?>
                Le présent contrat est conclu pour une <strong>durée indéterminée</strong> prenant effet le <strong><?= date('d/m/Y', strtotime($contract['date_debut'])) ?></strong>.
            <?php else: ?>
                Le présent contrat est conclu pour une <strong>durée déterminée</strong> prenant effet le <strong><?= date('d/m/Y', strtotime($contract['date_debut'])) ?></strong> 
                et prendra fin le <strong><?= date('d/m/Y', strtotime($contract['date_fin'])) ?></strong>.
            <?php endif; ?>
        </div>
    </div>

    <div class="contract-article">
        <div class="contract-article-title">ARTICLE 3 &mdash; RÉMUNÉRATION (FCFA)</div>
        <div class="contract-article-content">
            En contrepartie de l'exercice de ses fonctions, le Salarié percevra une rémunération brute de base fixée à :
            <div style="margin: 6px 0; padding: 8px 12px; background: #fff1f2; border-radius: 4px; font-weight: 700; color: #881337;">
                <?= formatCurrency($contract['salaire_base'], 'FCFA') ?> / <?= ($contract['type_remuneration'] === 'journalier') ? 'Jour ouvré' : 'Mois' ?>
                <span style="font-weight: 400; font-size: 0.8rem; color: #64748b; margin-left: 8px;">
                    (Soit : <?= ucfirst(nombreEnLettres($contract['salaire_base'])) ?> Francs CFA)
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

    <!-- Signatures -->
    <div class="contract-signatures">
        <div class="sig-box">
            <div class="sig-title">Pour la Société <?= htmlspecialchars($company['company_name']) ?></div>
            <div style="font-size: 0.75rem; color: #64748b;">La Direction Générale</div>
            <div class="signature-space"></div>
            <div class="sig-line">Cachet & Signature</div>
        </div>

        <div class="sig-box">
            <div class="sig-title">Le Salarié</div>
            <div style="font-size: 0.75rem; color: #64748b;">(Mention manuscrite <em>« Lu et approuvé »</em>)</div>
            <div class="signature-space"></div>
            <div class="sig-line"><?= htmlspecialchars($contract['nom'] . ' ' . $contract['prenom']) ?></div>
        </div>
    </div>

</div>

<?php if ($pdfRender): ?>
</body></html>
<?php else: ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>
<?php endif; ?>
