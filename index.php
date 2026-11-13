<?php
// index.php - Connexion & Inscription AGE (Édition Gabon)
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$db = getDB();
$error = '';
$success = '';

// Connexion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($email) && !empty($password)) {
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
            $error = "La connexion est temporairement indisponible. Veuillez réessayer.";
        } else {
            $lockRemaining = getLoginLockRemainingSeconds($db, $clientIp);

            if ($lockRemaining > 0) {
                $error = "Trop de tentatives. Réessayez dans " . max(1, (int)ceil($lockRemaining / 60)) . " minute(s).";
            } else {
                $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    clearLoginAttempts($db, $clientIp);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['company_name'] = $user['company_name'];
                    header("Location: dashboard.php");
                    exit;
                }

                $lockRemaining = recordFailedLogin($db, $clientIp);
                $error = $lockRemaining > 0
                    ? "Trois tentatives échouées. Réessayez dans " . max(1, (int)ceil($lockRemaining / 60)) . " minute(s)."
                    : "Identifiants invalides.";
            }
        }
    } else {
        $error = "Veuillez renseigner tous les champs.";
    }
}

// Inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $company_name = trim($_POST['company_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $email_confirmation = trim($_POST['email_confirmation'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $localisation = trim($_POST['localisation'] ?? '');
    $nif = trim($_POST['nif'] ?? '');
    $rccm = trim($_POST['rccm'] ?? '');
    $cnss_employer = trim($_POST['cnss_employer'] ?? '');

    if (!empty($company_name) && !empty($email) && !empty($password) && !empty($phone) && !empty($address) && !empty($localisation) && !empty($nif) && !empty($rccm) && !empty($cnss_employer)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Veuillez saisir une adresse email valide.";
        } elseif ($email !== $email_confirmation) {
            $error = "Les adresses email ne correspondent pas.";
        } else {
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = "Cette adresse email est déjà enregistrée.";
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $siret = "NIF $nif - RCCM $rccm";
                $insert = $db->prepare("INSERT INTO users (company_name, email, password, phone, address, localisation, siret, nif, rccm, cnss_employer, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'FCFA')");
                if ($insert->execute([$company_name, $email, $hashed, $phone, $address, $localisation, $siret, $nif, $rccm, $cnss_employer])) {
                    $_SESSION['user_id'] = $db->lastInsertId();
                    $_SESSION['user_email'] = $email;
                    $_SESSION['company_name'] = $company_name;
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Une erreur est survenue lors de l'enregistrement.";
                }
            }
        }
    } else {
        $error = "Veuillez remplir tous les champs.";
    }
}

if (isset($_GET['logout'])) {
    $success = "Vous avez été déconnecté.";
}
    $showRegisterForm = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'register';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion & Inscription &mdash; AGE Gabon</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-layout">

    <div class="auth-card">
        <div class="auth-header">
            <div class="auth-logo">AGE</div>
            <h1 style="font-size: 1.25rem; font-weight: 800; color: #ffffff;">AGE &bull; Gabon</h1>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Application de Gestion des Employés, Contrats & Paie</p>
        </div>

        <div class="auth-tabs">
            <button type="button" class="auth-tab-btn <?= $showRegisterForm ? '' : 'active' ?>" id="tabLoginBtn" onclick="switchAuthTab('login')">
                Se connecter
            </button>
            <button type="button" class="auth-tab-btn <?= $showRegisterForm ? 'active' : '' ?>" id="tabRegisterBtn" onclick="switchAuthTab('register')">
                S'enregistrer
            </button>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 0.82rem;">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; padding: 10px 14px; border-radius: 6px; margin-bottom: 16px; font-size: 0.82rem;">
                <i class="fa-solid fa-check"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de Connexion -->
        <form method="POST" action="index.php" id="loginForm" style="display: <?= $showRegisterForm ? 'none' : 'block' ?>;">
            <input type="hidden" name="action" value="login">
            
            <div class="form-group">
                <label class="form-label" for="login_email">Email Professionnel</label>
                <input type="email" id="login_email" name="email" class="form-control" placeholder="contact@entreprise.ga" required autofocus>
            </div>

            <div class="form-group">
                <label class="form-label" for="login_password">Mot de passe</label>
                <input type="password" id="login_password" name="password" class="form-control" placeholder="Votre mot de passe" required>
            </div>

            <div style="margin-bottom: 18px; font-size: 0.75rem; color: var(--text-muted);">
                Utilisez les identifiants de votre entreprise enregistrée.
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px;">
                <i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter
            </button>
        </form>

        <!-- Formulaire d'Enregistrement -->
        <form method="POST" action="index.php" id="registerForm" style="display: <?= $showRegisterForm ? 'block' : 'none' ?>;">
            <input type="hidden" name="action" value="register">

            <div class="form-group">
                <label class="form-label" for="reg_company">Raison Sociale de l'Entreprise <span class="required">*</span></label>
                <input type="text" id="reg_company" name="company_name" class="form-control" placeholder="Ex: GABON SERVICES SA" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_email">Email professionnel <span class="required">*</span></label>
                <input type="email" id="reg_email" name="email" class="form-control" placeholder="contact@entreprise.ga" autocomplete="email" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_email_confirmation">Confirmer l'email professionnel <span class="required">*</span></label>
                <input type="email" id="reg_email_confirmation" name="email_confirmation" class="form-control" placeholder="Retapez l'adresse email" autocomplete="off" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_phone">Numéro de téléphone <span class="required">*</span></label>
                <input type="text" id="reg_phone" name="phone" class="form-control" placeholder="Ex: +241 01 23 45 67" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_address">Adresse <span class="required">*</span></label>
                <input type="text" id="reg_address" name="address" class="form-control" placeholder="Adresse du siège social" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_localisation">Localisation <span class="required">*</span></label>
                <input type="text" id="reg_localisation" name="localisation" class="form-control" placeholder="Ex: Moanda, Libreville, Oyem..." required>
            </div>

            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label" for="reg_nif">NIF <span class="required">*</span></label>
                    <input type="text" id="reg_nif" name="nif" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_rccm">RCCM <span class="required">*</span></label>
                    <input type="text" id="reg_rccm" name="rccm" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_cnss">CNSS <span class="required">*</span></label>
                    <input type="text" id="reg_cnss" name="cnss_employer" class="form-control" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_password">Mot de passe <span class="required">*</span></label>
                <input type="password" id="reg_password" name="password" class="form-control" placeholder="Au moins 6 caractères" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 10px; margin-top: 6px;">
                <i class="fa-solid fa-check"></i> Créer le Compte Entreprise
            </button>
        </form>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
