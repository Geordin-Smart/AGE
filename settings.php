<?php
// settings.php - Configuration Entreprise Gabonaise & Sécurité
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/config/database.php';

$pageTitle = "Configuration";
$db = getDB();
$userId = $_SESSION['user_id'];

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'update_company') {
        $company_name = trim($_POST['company_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email_contact = trim($_POST['email_contact'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $localisation = trim($_POST['localisation'] ?? '');
        $nif = trim($_POST['nif'] ?? '');
        $rccm = trim($_POST['rccm'] ?? '');
        $cnss_employer = trim($_POST['cnss_employer'] ?? '');
        $siret = "NIF $nif - RCCM $rccm";
        $currency = 'FCFA';

        if (empty($company_name)) {
            $error = "Le nom de l'entreprise est obligatoire.";
        } else {
            $stmt = $db->prepare("UPDATE users SET company_name = ?, phone = ?, email = ?, address = ?, localisation = ?, siret = ?, nif = ?, rccm = ?, cnss_employer = ?, currency = ? WHERE id = ?");
            if ($stmt->execute([$company_name, $phone, $email_contact, $address, $localisation, $siret, $nif, $rccm, $cnss_employer, $currency, $userId])) {
                $_SESSION['company_name'] = $company_name;
                $_SESSION['user_email'] = $email_contact;
                $success = "Informations de l'entreprise mises à jour avec succès.";
            } else {
                $error = "Erreur lors de la mise à jour.";
            }
        }
    }

    if ($_POST['action'] === 'update_security') {
        $login_email = trim($_POST['login_email'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if (empty($login_email)) {
            $error = "L'adresse email ne peut être vide.";
        } elseif (!empty($new_password)) {
            if ($new_password !== $confirm_password) {
                $error = "Les mots de passe ne correspondent pas.";
            } elseif (strlen($new_password) < 6) {
                $error = "Le mot de passe doit comporter au moins 6 caractères.";
            } else {
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
                $stmt->execute([$login_email, $hashed, $userId]);
                $_SESSION['user_email'] = $login_email;
                $success = "Identifiants mis à jour avec succès.";
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$login_email, $userId]);
            $_SESSION['user_email'] = $login_email;
            $success = "Email de connexion mis à jour.";
        }
    }
}

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$company = $stmt->fetch();
if (!$company) {
    $company = getCompanyConfig();
}

include __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <h2 style="font-size: 1.25rem; font-weight: 700; color: #ffffff;">Configuration Entreprise & Identifiants</h2>
    <p style="font-size: 0.82rem; color: var(--text-muted);">Paramètres de l'entreprise gabonaise et accès sécurisé</p>
</div>

<?php if (!empty($success)): ?>
    <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #6ee7b7; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-check"></i> <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 12px 16px; border-radius: var(--radius); margin-bottom: 20px;">
        <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="form-grid-2">

    <!-- Formulaire Entreprise Gabon -->
    <div class="card-glass">
        <div class="card-header-flex">
            <h3 class="card-title">
                <i class="fa-solid fa-building"></i> Informations Entreprise (Gabon)
            </h3>
        </div>

        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_company">

            <div class="form-group">
                <label class="form-label" for="company_name">Raison Sociale <span class="required">*</span></label>
                <input type="text" id="company_name" name="company_name" class="form-control" value="<?= htmlspecialchars($company['company_name'] ?? '') ?>" required>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label" for="phone">Téléphone</label>
                    <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($company['phone'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email_contact">Email Contact</label>
                    <input type="email" id="email_contact" name="email_contact" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="address">Adresse du Siège (Gabon)</label>
                <input type="text" id="address" name="address" class="form-control" value="<?= htmlspecialchars($company['address'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="localisation">Localisation de l'entreprise</label>
                <input type="text" id="localisation" name="localisation" class="form-control" value="<?= htmlspecialchars($company['localisation'] ?? '') ?>" placeholder="Ex: Moanda, Libreville, Oyem..."><!-- Localisation affichée sur les documents -->
            </div>

            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label" for="nif">NIF</label>
                    <input type="text" id="nif" name="nif" class="form-control" value="<?= htmlspecialchars($company['nif'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="rccm">RCCM</label>
                    <input type="text" id="rccm" name="rccm" class="form-control" value="<?= htmlspecialchars($company['rccm'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="cnss_employer">N° Employeur CNSS</label>
                    <input type="text" id="cnss_employer" name="cnss_employer" class="form-control" value="<?= htmlspecialchars($company['cnss_employer'] ?? '') ?>">
                </div>
            </div>

            <div style="text-align: right; margin-top: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Enregistrer
                </button>
            </div>
        </form>
    </div>

    <!-- Formulaire Sécurité -->
    <div class="card-glass">
        <div class="card-header-flex">
            <h3 class="card-title">
                <i class="fa-solid fa-lock"></i> Identifiants de Connexion
            </h3>
        </div>

        <form method="POST" action="settings.php">
            <input type="hidden" name="action" value="update_security">

            <div class="form-group">
                <label class="form-label" for="login_email">Email de Connexion <span class="required">*</span></label>
                <input type="email" id="login_email" name="login_email" class="form-control" value="<?= htmlspecialchars($company['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="new_password">Nouveau Mot de Passe</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Laisser vide si inchangé">
            </div>

            <div class="form-group">
                <label class="form-label" for="confirm_password">Confirmation</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Confirmer le mot de passe">
            </div>

            <div style="text-align: right; margin-top: 10px;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-key"></i> Mettre à jour
                </button>
            </div>
        </form>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
