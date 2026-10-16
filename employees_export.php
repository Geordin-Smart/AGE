<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$search = trim($_GET['search'] ?? '');
$sql = "SELECT matricule, nom, prenom, telephone, poste, categorie, type_remuneration, salaire_base,
               type_contrat, date_debut_contrat, date_fin_contrat, statut
        FROM employees
        WHERE user_id = ?";
$params = [(int)$_SESSION['user_id']];

if ($search !== '') {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR poste LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term);
}

$sql .= ' ORDER BY nom, prenom';
$stmt = getDB()->prepare($sql);
$stmt->execute($params);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="liste-employes-' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Matricule', 'Nom', 'Prénoms', 'Téléphone', 'Poste', 'Catégorie', 'Mode de rémunération',
    'Salaire de base (FCFA)', 'Type de contrat', 'Date de début', 'Date de fin', 'Statut'
], ';', '"', '\\');

while ($employee = $stmt->fetch()) {
    $values = array_values($employee);
    foreach ($values as &$value) {
        $value = (string)($value ?? '');
        if (preg_match('/^[\t\r ]*[=+\-@]/', $value)) {
            $value = "'" . $value;
        }
    }
    unset($value);
    fputcsv($output, $values, ';', '"', '\\');
}

fclose($output);