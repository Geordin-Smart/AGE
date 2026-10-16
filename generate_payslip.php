<?php
// generate_payslip.php - Générateur Épuré de Bulletins de Paie (Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$db = getDB();
$company = getCompanyConfig();

$stmtEmployees = $db->prepare("SELECT * FROM employees WHERE statut = 'actif' AND user_id = ? ORDER BY nom ASC, prenom ASC");
$stmtEmployees->execute([(int)$_SESSION['user_id']]);
$employees = $stmtEmployees->fetchAll();

$selectedEmpId = intval($_GET['employee_id'] ?? 0);
if ($selectedEmpId === 0 && !empty($employees)) {
    $selectedEmpId = $employees[0]['id'];
}

$currentEmp = null;
foreach ($employees as $e) {
    if ($e['id'] == $selectedEmpId) {
        $currentEmp = $e;
        break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_payslip') {
    $empId = intval($_POST['employee_id'] ?? 0);
    $periodMonth = intval($_POST['period_month'] ?? date('m'));
    $periodYear = intval($_POST['period_year'] ?? date('Y'));
    
    $monthNames = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $periodLabel = ($monthNames[$periodMonth] ?? 'Mois') . ' ' . $periodYear;

    $tauxBase = floatval($_POST['taux_journalier_ou_base'] ?? 0);
    $nbJours = floatval($_POST['nb_jours_travailles'] ?? 30);
    $primes = floatval($_POST['primes'] ?? 0);
    $indemnites = floatval($_POST['indemnites'] ?? 0);
    $retenuesFiscales = floatval($_POST['retenues_fiscales'] ?? 0);
    $autresRetenues = floatval($_POST['autres_retenues'] ?? 0);
    $modePaiement = trim($_POST['mode_paiement'] ?? 'Virement bancaire (BGFI Bank)');
    $commentaires = trim($_POST['commentaires'] ?? '');

    $ownerCheck = $db->prepare("SELECT type_remuneration FROM employees WHERE id = ? AND user_id = ?");
    $ownerCheck->execute([$empId, (int)$_SESSION['user_id']]);
    $employeeType = $ownerCheck->fetchColumn();

    if ($empId <= 0 || !$employeeType || $tauxBase <= 0) {
        $error = "Veuillez sélectionner un salarié avec un salaire valide.";
    } else {
        $typeRemun = $employeeType;
        $cotisations = $typeRemun === 'journalier' ? 0 : floatval($_POST['cotisations_sociales'] ?? 0);
        $salaireBaseCalcule = ($typeRemun === 'journalier') ? ($tauxBase * $nbJours) : $tauxBase;
        $salaireBrut = $salaireBaseCalcule + $primes + $indemnites;
        $totalDeductions = $cotisations + $retenuesFiscales + $autresRetenues;
        $salaireNet = max(0, $salaireBrut - $totalDeductions);

        try {
            $slipNumber = sprintf("FP-%04d%02d-%04d", $periodYear, $periodMonth, $empId);
            $check = $db->prepare("SELECT id FROM payslips WHERE slip_number = ?");
            $check->execute([$slipNumber]);
            if ($check->fetch()) {
                $slipNumber .= '-' . substr(time(), -3);
            }

            $stmtInsert = $db->prepare("INSERT INTO payslips 
                (employee_id, slip_number, period_month, period_year, period_label, type_remuneration, taux_journalier_ou_base, nb_jours_travailles, salaire_brut, primes, indemnites, cotisations_sociales, retenues_fiscales, autres_retenues, salaire_net, mode_paiement, commentaires)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtInsert->execute([
                $empId, $slipNumber, $periodMonth, $periodYear, $periodLabel, $typeRemun,
                $tauxBase, $nbJours, $salaireBrut, $primes, $indemnites, $cotisations,
                $retenuesFiscales, $autresRetenues, $salaireNet, $modePaiement, $commentaires
            ]);

            $newId = $db->lastInsertId();
            header("Location: view_payslip.php?id=" . $newId);
            exit;
        } catch (Exception $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Calcul automatique des cotisations gabonaises pour pré-remplissage
$baseEmp = $currentEmp ? floatval($currentEmp['salaire_base']) : 0;
$isJournalier = ($currentEmp && $currentEmp['type_remuneration'] === 'journalier');
$baseCalcul = $isJournalier ? ($baseEmp * 26) : $baseEmp;

// Cotisation CNSS Gabon = 2.5% plafonné à 1 500 000 FCFA (soit max 37 500 FCFA)
$cnssEst = round(min($baseCalcul * 0.025, 37500));
// Cotisation CNAMGS Gabon = 1% du brut
$cnamgsEst = round($baseCalcul * 0.01);
$cotisTotalEst = $isJournalier ? 0 : $cnssEst + $cnamgsEst;

// Estimation IRPP / TCS Gabon (~5% à 8%)
$impotEst = round($baseCalcul * 0.06);

$pageTitle = "Générer Bulletin";
include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Émission d'un Bulletin de Paie</h2>
        <p style="font-size: 0.82rem; color: var(--text-muted);">Cotisations sociales CNSS & CNAMGS calculées selon la législation gabonaise</p>
    </div>
    <a href="payslips.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Registre
    </a>
</div>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card-glass">
    <form method="POST" action="generate_payslip.php">
        <input type="hidden" name="action" value="save_payslip">
        <input type="hidden" id="pay_is_journalier" value="<?= $isJournalier ? '1' : '0' ?>">

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="employee_select">Salarié <span class="required">*</span></label>
                <select id="employee_select" name="employee_id" class="form-select" onchange="window.location.href='generate_payslip.php?employee_id=' + this.value;">
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= ($currentEmp && $currentEmp['id'] == $emp['id']) ? 'selected' : '' ?>>
                            [<?= htmlspecialchars($emp['matricule']) ?>] <?= htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) ?> (<?= htmlspecialchars($emp['poste']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="period_month">Mois de Paie</label>
                <select id="period_month" name="period_month" class="form-select">
                    <?php
                    $curM = intval(date('m'));
                    $months = [1=>'Janvier', 2=>'Février', 3=>'Mars', 4=>'Avril', 5=>'Mai', 6=>'Juin', 7=>'Juillet', 8=>'Août', 9=>'Septembre', 10=>'Octobre', 11=>'Novembre', 12=>'Décembre'];
                    foreach ($months as $k => $v):
                    ?>
                        <option value="<?= $k ?>" <?= ($curM === $k) ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="period_year">Année</label>
                <input type="number" id="period_year" name="period_year" class="form-control" value="<?= date('Y') ?>">
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="type_remuneration">Mode de Paie</label>
                <input type="text" id="type_remuneration" name="type_remuneration" class="form-control" value="<?= htmlspecialchars($currentEmp['type_remuneration'] ?? 'mensuel') ?>" readonly style="background: rgba(255,255,255,0.03);">
            </div>

            <div class="form-group">
                <label class="form-label" for="pay_base">Base (FCFA) <span class="required">*</span></label>
                <input type="number" step="1" id="pay_base" name="taux_journalier_ou_base" class="form-control" value="<?= intval($baseEmp) ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="pay_days">Jours Travaillés</label>
                <input type="number" step="0.5" id="pay_days" name="nb_jours_travailles" class="form-control" value="<?= $isJournalier ? '26' : '30' ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="pay_primes">Primes / Sursalaire (FCFA)</label>
                <input type="number" step="1" id="pay_primes" name="primes" class="form-control" value="0">
            </div>

            <div class="form-group">
                <label class="form-label" for="pay_indemnites">Indemnité de Transport (FCFA)</label>
                <input type="number" step="1" id="pay_indemnites" name="indemnites" class="form-control" value="<?= ($currentEmp && $currentEmp['categorie'] === 'Cadre') ? '45000' : '25000' ?>">
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="pay_cotis">Cotisations Sociales Gabon (CNSS 2.5% + CNAMGS 1%)</label>
                <input type="number" step="1" id="pay_cotis" name="cotisations_sociales" class="form-control" value="<?= $cotisTotalEst ?>" <?= $isJournalier ? 'readonly' : '' ?>>
                <?php if ($isJournalier): ?>
                    <small style="color: var(--text-muted);">Aucune cotisation sociale ne sera déduite pour une rémunération journalière.</small>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="pay_retenues">Retenues Fiscales Gabon (IRPP / TCS)</label>
                <input type="number" step="1" id="pay_retenues" name="retenues_fiscales" class="form-control" value="<?= $impotEst ?>">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="mode_paiement">Mode de Règlement</label>
            <select id="mode_paiement" name="mode_paiement" class="form-select">
                <option value="Virement bancaire (BGFI Bank)" selected>Virement bancaire (BGFI Bank)</option>
                <option value="Virement bancaire (UBA Gabon)">Virement bancaire (UBA Gabon)</option>
                <option value="Virement bancaire (Bicig)">Virement bancaire (Bicig)</option>
                <option value="Chèque bancaire">Chèque bancaire</option>
                <option value="Espèces">Espèces (Reçu de caisse)</option>
            </select>
        </div>

        <!-- Calcul Net en Direct Simplifié -->
        <div style="background: rgba(136, 19, 55, 0.3); border: 1px solid rgba(225, 29, 72, 0.3); border-radius: var(--radius); padding: 14px 20px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <span style="font-size: 0.78rem; color: #fecdd3; text-transform: uppercase; font-weight: 700;">NET ESTIMÉ À PAYER :</span>
                <div style="font-size: 1.4rem; font-weight: 800; color: #ffffff;">
                    <span id="pay_net_preview">0</span> FCFA
                </div>
            </div>
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-print"></i> Émettre le Bulletin
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
