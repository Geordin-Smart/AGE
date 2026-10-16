<?php
// employees.php - Gestion & Enregistrement des Salariés (Édition Gabon)
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Employés";
$db = getDB();
$company = getCompanyConfig();

$success = '';
$error = '';

// Enregistrement d'un employé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_employee') {
    $nom = strtoupper(trim($_POST['nom'] ?? ''));
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $date_naissance = !empty($_POST['date_naissance']) ? $_POST['date_naissance'] : null;
    $adresse = trim($_POST['adresse'] ?? 'Moanda, Gabon');
    $cnss_number = trim($_POST['cnss_number'] ?? '');
    $poste = trim($_POST['poste'] ?? '');
    $categorie = trim($_POST['categorie'] ?? 'Ouvrier');
    $type_remuneration = trim($_POST['type_remuneration'] ?? 'mensuel');
    $salaire_base = floatval($_POST['salaire_base'] ?? 0);
    $type_contrat = trim($_POST['type_contrat'] ?? 'CDI');
    $date_embauche = !empty($_POST['date_embauche']) ? $_POST['date_embauche'] : date('Y-m-d');
    $date_debut_contrat = !empty($_POST['date_debut_contrat']) ? $_POST['date_debut_contrat'] : $date_embauche;
    $date_fin_contrat = ($type_contrat === 'CDD' && !empty($_POST['date_fin_contrat'])) ? trim($_POST['date_fin_contrat']) : null;
    $datesContratValides = true;
    if ($type_contrat === 'CDD' && $date_fin_contrat !== null) {
        $dateDebut = DateTime::createFromFormat('!Y-m-d', $date_debut_contrat);
        $dateFin = DateTime::createFromFormat('!Y-m-d', $date_fin_contrat);
        $datesContratValides = $dateDebut && $dateDebut->format('Y-m-d') === $date_debut_contrat
            && $dateFin && $dateFin->format('Y-m-d') === $date_fin_contrat
            && $date_fin_contrat >= $date_debut_contrat;
    }

    if (empty($nom) || empty($prenom) || empty($poste) || $salaire_base <= 0) {
        $error = "Veuillez remplir les informations obligatoires avec un salaire valide.";
    } elseif ($type_contrat === 'CDD' && empty($date_fin_contrat)) {
        $error = "La date de fin est obligatoire pour un contrat CDD afin d'activer l'alerte à 30 jours.";
    } elseif ($type_contrat === 'CDD' && !$datesContratValides) {
        $error = "La date de fin du CDD doit être valide et postérieure ou égale à la date de début.";
    } else {
        try {
            $db->beginTransaction();

            $userId = (int)$_SESSION['user_id'];
            $matricule = generateNextMatricule($userId);

            $stmtEmp = $db->prepare("INSERT INTO employees 
                (user_id, matricule, nom, prenom, email, telephone, date_naissance, adresse, cnss_number, poste, categorie, type_remuneration, salaire_base, type_contrat, date_embauche, date_debut_contrat, date_fin_contrat, statut)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
            $stmtEmp->execute([
                $userId, $matricule, $nom, $prenom, $email, $telephone, $date_naissance, $adresse, $cnss_number,
                $poste, $categorie, $type_remuneration, $salaire_base, $type_contrat,
                $date_embauche, $date_debut_contrat, $date_fin_contrat
            ]);
            $employeeId = $db->lastInsertId();

            // Création automatique du contrat V1 immédiat
            $contractNumber = "CTR-" . $matricule . "-V1";
            $clauses = "Contrat régi par la Loi n° 022/2021 portant Code du Travail en République Gabonaise.";
            
            $stmtCtr = $db->prepare("INSERT INTO contracts 
                (employee_id, contract_number, version, type_contrat, poste, categorie, type_remuneration, salaire_base, date_debut, date_fin, clauses_notes, status)
                VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, 'actif')");
            $stmtCtr->execute([
                $employeeId, $contractNumber, $type_contrat, $poste, $categorie,
                $type_remuneration, $salaire_base, $date_debut_contrat, $date_fin_contrat, $clauses
            ]);

            $db->commit();
            $success = "Salarié <strong>$prenom $nom</strong> enregistré avec succès ! Matricule attribué : <strong>$matricule</strong>. Contrat <strong>$contractNumber</strong> généré automatiquement.";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    }
}

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_employee') {
    requireValidCsrfToken();
    $delId = (int)($_POST['employee_id'] ?? 0);
    if ($delId <= 0) {
        http_response_code(400);
        exit('Employé invalide.');
    }

    $db->prepare("DELETE FROM employees WHERE id = ? AND user_id = ?")->execute([$delId, (int)$_SESSION['user_id']]);
    $success = "Employé supprimé avec succès.";
}

// Liste
$search = trim($_GET['search'] ?? '');
$sql = "SELECT e.*, c.id as contract_id FROM employees e 
        LEFT JOIN contracts c ON c.employee_id = e.id AND c.status = 'actif'
        WHERE e.user_id = ?";
    $params = [(int)$_SESSION['user_id']];
if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ? OR e.poste LIKE ?)";
    $t = "%$search%";
    array_push($params, $t, $t, $t, $t);
}
$sql .= " ORDER BY e.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$employeesList = $stmt->fetchAll();

$nextMatricule = generateNextMatricule((int)$_SESSION['user_id']);
include __DIR__ . '/includes/header.php';
?>

<!-- Alertes -->
<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-check"></i> <?= $success ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= $error ?>
    </div>
<?php endif; ?>

<!-- Formulaire Simplifié d'Enregistrement -->
<div class="card-glass employee-register-card" style="margin-bottom: 24px;">
    <div class="card-header-flex">
        <h3 class="card-title">
            <i class="fa-solid fa-user-plus"></i> Enregistrer un Salarié
        </h3>
        <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #fda4af;">
            Prochain Matricule Auto : <?= $nextMatricule ?>
        </span>
    </div>

    <form method="POST" action="employees.php">
        <input type="hidden" name="action" value="create_employee">

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="nom">Nom de Famille <span class="required">*</span></label>
                <input type="text" id="nom" name="nom" class="form-control" placeholder="Ex: OBAME" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="prenom">Prénoms <span class="required">*</span></label>
                <input type="text" id="prenom" name="prenom" class="form-control" placeholder="Ex: Jean-Clément" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="telephone">Téléphone (Gabon)</label>
                <input type="tel" id="telephone" name="telephone" class="form-control" placeholder="+241 077 00 00 00">
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="poste">Poste / Fonction <span class="required">*</span></label>
                <input type="text" id="poste" name="poste" class="form-control" placeholder="Ex: Chef d'Équipe Logistique" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="categorie">Catégorie <span class="required">*</span></label>
                <select id="categorie" name="categorie" class="form-select" required>
                    <option value="Ouvrier">Ouvrier</option>
                    <option value="Agent de maîtrise" selected>Agent de maîtrise</option>
                    <option value="Cadre">Cadre</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="cnss_number">N° CNSS Salarié</label>
                <input type="text" id="cnss_number" name="cnss_number" class="form-control" placeholder="Ex: 512-8841-G">
            </div>
        </div>

        <div class="form-grid-3">
            <div class="form-group">
                <label class="form-label" for="type_remuneration">Mode de Paie <span class="required">*</span></label>
                <select id="type_remuneration" name="type_remuneration" class="form-select" required>
                    <option value="mensuel" selected>Mensuelle</option>
                    <option value="journalier">Journalière (taux/jour)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" id="salaire_label" for="salaire_base">
                    Salaire de Base (FCFA) <span class="required">*</span>
                </label>
                <input type="number" step="1" min="1" id="salaire_base" name="salaire_base" class="form-control" placeholder="Ex: 450000" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="type_contrat">Type de Contrat <span class="required">*</span></label>
                <select id="type_contrat" name="type_contrat" class="form-select" required>
                    <option value="CDI" <?= (($_POST['type_contrat'] ?? 'CDI') === 'CDI') ? 'selected' : '' ?>>CDI (Durée Indéterminée)</option>
                    <option value="CDD" <?= (($_POST['type_contrat'] ?? '') === 'CDD') ? 'selected' : '' ?>>CDD (Durée Déterminée)</option>
                </select>
            </div>
        </div>

        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label" for="date_debut_contrat">Date de Début <span class="required">*</span></label>
                <input type="date" id="date_debut_contrat" name="date_debut_contrat" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group" id="dateFinGroup" style="display: <?= (($_POST['type_contrat'] ?? '') === 'CDD') ? 'block' : 'none' ?>;">
                <label class="form-label" for="date_fin_contrat">Date de Fin CDD <span class="required">* (Alerte 30j)</span></label>
                <input type="date" id="date_fin_contrat" name="date_fin_contrat" class="form-control" min="<?= htmlspecialchars($date_debut_contrat ?? date('Y-m-d')) ?>" value="<?= htmlspecialchars($_POST['date_fin_contrat'] ?? '') ?>" <?= (($_POST['type_contrat'] ?? '') === 'CDD') ? 'required' : '' ?>>
            </div>
        </div>

        <div style="text-align: right; margin-top: 6px;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-check"></i> Enregistrer & Générer le Contrat
            </button>
        </div>
    </form>
</div>

<!-- Registre des Employés Simplifié -->
<div class="card-glass employee-list-card">
    <div class="card-header-flex">
        <h3 class="card-title">
            <i class="fa-solid fa-users"></i> Liste des Salariés (<?= count($employeesList) ?>)
        </h3>
        <form method="GET" action="employees.php" style="display: flex; gap: 8px;">
            <input type="text" name="search" class="form-control" style="width: 200px; padding: 6px 10px;" placeholder="Recherche..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-secondary btn-sm"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
        <div class="employee-list-actions">
            <button type="button" class="btn btn-secondary btn-sm" onclick="window.print()" title="Imprimer la liste des employés">
                <i class="fa-solid fa-print"></i> Imprimer la liste
            </button>
            <a href="employees_export.php<?= $search !== '' ? '?' . htmlspecialchars(http_build_query(['search' => $search])) : '' ?>" class="btn btn-primary btn-sm" title="Télécharger la liste au format CSV">
                <i class="fa-solid fa-download"></i> Télécharger CSV
            </a>
        </div>
    </div>

    <form method="POST" action="employees.php" id="deleteEmployeeForm" hidden>
        <input type="hidden" name="action" value="delete_employee">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
    </form>
    <form method="POST" action="batch_print.php" id="batchPrintForm">
        <div class="employee-selection-toolbar" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 14px; padding: 10px 12px; background: rgba(255,255,255,0.03); border-radius: var(--radius);">
            <strong style="font-size: 0.82rem; color: #ffffff;">Sélection multiple :</strong>
            <button type="submit" name="document_type" value="contracts" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-file-signature"></i> Imprimer les contrats
            </button>
            <button type="submit" name="document_type" value="payslips" class="btn btn-secondary btn-sm">
                <i class="fa-solid fa-file-invoice-dollar"></i> Imprimer les fiches de paie
            </button>
            <span id="selectionCount" style="font-size: 0.75rem; color: var(--text-muted);">0 sélectionné</span>
        </div>

        <div class="table-responsive">
        <table class="table-custom">
            <thead>
                <tr>
                    <th style="width: 42px;">
                        <input type="checkbox" id="selectAllEmployees" title="Sélectionner tous les employés">
                    </th>
                    <th>Matricule</th>
                    <th>Nom & Prénoms</th>
                    <th>Poste & Catégorie</th>
                    <th>Rémunération</th>
                    <th>Contrat</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employeesList as $emp): ?>
                    <tr>
                        <td>
                            <input type="checkbox" name="employee_ids[]" value="<?= $emp['id'] ?>" class="employee-checkbox" aria-label="Sélectionner <?= htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) ?>">
                        </td>
                        <td>
                            <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; color: #fda4af;">
                                <?= htmlspecialchars($emp['matricule']) ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #ffffff;"><?= htmlspecialchars($emp['nom'] . ' ' . $emp['prenom']) ?></strong>
                            <div style="font-size: 0.72rem; color: var(--text-muted);"><?= htmlspecialchars($emp['telephone'] ?? 'Moanda') ?></div>
                        </td>
                        <td>
                            <div><?= htmlspecialchars($emp['poste']) ?></div>
                            <span class="badge <?= ($emp['categorie'] === 'Cadre') ? 'badge-cat-cadre' : (($emp['categorie'] === 'Agent de maîtrise') ? 'badge-cat-maitrise' : 'badge-cat-ouvrier') ?>">
                                <?= htmlspecialchars($emp['categorie']) ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= formatCurrency($emp['salaire_base'], 'FCFA') ?></strong>
                            <div style="font-size: 0.72rem; color: var(--text-muted);">/ <?= $emp['type_remuneration'] ?></div>
                        </td>
                        <td>
                            <span class="badge <?= ($emp['type_contrat'] === 'CDI') ? 'badge-cdi' : 'badge-cdd' ?>">
                                <?= htmlspecialchars($emp['type_contrat']) ?>
                            </span>
                            <?php if ($emp['type_contrat'] === 'CDD' && !empty($emp['date_fin_contrat'])): ?>
                                <div style="font-size: 0.72rem; color: var(--text-muted);">Fin : <?= date('d/m/Y', strtotime($emp['date_fin_contrat'])) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right; white-space: nowrap;">
                            <?php if (!empty($emp['contract_id'])): ?>
                                <a href="view_contract.php?id=<?= $emp['contract_id'] ?>" class="btn btn-secondary btn-sm" title="Voir Contrat">
                                    <i class="fa-solid fa-eye"></i> Contrat
                                </a>
                            <?php endif; ?>
                            <a href="generate_payslip.php?employee_id=<?= $emp['id'] ?>" class="btn btn-secondary btn-sm" title="Bulletin de paie">
                                <i class="fa-solid fa-receipt"></i> Paie
                            </a>
                            <button type="submit" form="deleteEmployeeForm" name="employee_id" value="<?= (int)$emp['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Confirmez-vous la suppression ?');" title="Supprimer">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    </form>
</div>

<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script>
    const selectAllEmployees = document.getElementById('selectAllEmployees');
    const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
    const selectionCount = document.getElementById('selectionCount');

    function updateSelectionCount() {
        const count = document.querySelectorAll('.employee-checkbox:checked').length;
        selectionCount.textContent = count + (count > 1 ? ' sélectionnés' : ' sélectionné');
    }

    selectAllEmployees.addEventListener('change', function () {
        employeeCheckboxes.forEach((checkbox) => checkbox.checked = this.checked);
        updateSelectionCount();
    });

    employeeCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', function () {
        selectAllEmployees.checked = document.querySelectorAll('.employee-checkbox:checked').length === employeeCheckboxes.length;
        updateSelectionCount();
    }));

    document.getElementById('batchPrintForm').addEventListener('submit', function (event) {
        if (document.querySelectorAll('.employee-checkbox:checked').length === 0) {
            event.preventDefault();
            alert('Sélectionnez au moins un employé.');
        }
    });
</script>
