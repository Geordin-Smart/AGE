<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);
$orientation = 'portrait';
$css = file_get_contents(__DIR__ . '/assets/css/style.css');
$css = preg_replace('/^\s*@import[^;]+;\s*/mi', '', $css);

if ($type === 'employees') {
    $search = trim($_GET['search'] ?? '');
    $sql = "SELECT matricule, nom, prenom, telephone, poste, categorie, type_remuneration, salaire_base,
                   type_contrat, date_debut_contrat, date_fin_contrat, statut
            FROM employees WHERE user_id = ?";
    $params = [(int)$_SESSION['user_id']];
    if ($search !== '') {
        $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR poste LIKE ?)";
        $term = '%' . $search . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $sql .= ' ORDER BY nom, prenom';
    $stmt = getDB()->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    $company = getCompanyConfig();
    $orientation = 'landscape';
    $filename = 'liste-employes-' . date('Y-m-d') . '.pdf';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Liste des employés</title><style>' . $css . '</style><style>' . file_get_contents(__DIR__ . '/assets/css/pdf-document-overrides.css') . '</style><style>@page{size:A4 landscape;margin:25mm}body{background:#fff!important;color:#111!important;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif;font-size:11pt;line-height:1.1}body>h1,body>p{font-size:11pt!important;line-height:1.1!important}body>table.table-custom{width:100%;border-collapse:collapse;table-layout:fixed;background:#fff}body>table.table-custom th,body>table.table-custom td{overflow-wrap:break-word;word-wrap:break-word;white-space:normal;font-family:Arial,Calibri,"Times New Roman","DejaVu Sans",sans-serif!important;font-size:11pt!important;line-height:1.1!important}table.table-custom th{background:#e8f1f8!important;color:#12304a!important;text-align:left;padding:5px;border:1px solid #8da4b8}table.table-custom td{background:#fff!important;color:#111!important;padding:4px;border:1px solid #aab7c4}</style></head><body class="employee-list-pdf">';
    $html .= '<h1>Liste des employés</h1><p><strong>' . htmlspecialchars($company['company_name'], ENT_QUOTES, 'UTF-8') . '</strong> &bull; ' . htmlspecialchars(getCompanyLocationLabel($company), ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<table class="table-custom"><thead><tr><th>Matricule</th><th>Nom et prénoms</th><th>Téléphone</th><th>Poste</th><th>Catégorie</th><th>Rémunération</th><th>Contrat</th><th>Début</th><th>Fin</th><th>Statut</th></tr></thead><tbody>';
    foreach ($employees as $employee) {
        $name = htmlspecialchars($employee['nom'] . ' ' . $employee['prenom'], ENT_QUOTES, 'UTF-8');
        $salary = number_format((float)$employee['salaire_base'], 0, ',', ' ') . ' FCFA / ' . $employee['type_remuneration'];
        $startDate = $employee['date_debut_contrat'] ? date('d/m/Y', strtotime($employee['date_debut_contrat'])) : '';
        $endDate = $employee['date_fin_contrat'] ? date('d/m/Y', strtotime($employee['date_fin_contrat'])) : '';
        $html .= '<tr><td>' . htmlspecialchars($employee['matricule'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . $name . '</td><td>' . htmlspecialchars($employee['telephone'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($employee['poste'], ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($employee['categorie'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . htmlspecialchars($salary, ENT_QUOTES, 'UTF-8') . '</td><td>' . htmlspecialchars($employee['type_contrat'], ENT_QUOTES, 'UTF-8') . '</td>';
        $html .= '<td>' . $startDate . '</td><td>' . $endDate . '</td><td>' . htmlspecialchars($employee['statut'], ENT_QUOTES, 'UTF-8') . '</td></tr>';
    }
    $html .= '</tbody></table></body></html>';
} elseif (in_array($type, ['contract', 'payslip'], true) && $id > 0) {
    $_GET['id'] = $id;
    $_GET['download'] = 'pdf';
    ob_start();
    require __DIR__ . ($type === 'contract' ? '/view_contract.php' : '/view_payslip.php');
    $html = ob_get_clean();
    $documentNumber = $type === 'contract' ? $contract['contract_number'] : $slip['slip_number'];
    $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $documentNumber) . '.pdf';
} else {
    http_response_code(400);
    exit('Document invalide.');
}

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('chroot', __DIR__);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', $orientation);
$dompdf->render();
$dompdf->stream($filename, ['Attachment' => true]);