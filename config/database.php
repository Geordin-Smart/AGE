<?php
// Configuration de la base de données et fonctions d'assistance AGE (Adaptée République Gabonaise)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('DB_HOST', 'appligest-database-wixpa1');
define('DB_NAME', 'app_db');
define('DB_USER', 'app_user');
define('DB_PASS', 'Smart210403');

function getDB() {
    static $pdo = null;
    static $tenantSchemaReady = false;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die("Erreur de connexion à la base de données : " . $e->getMessage());
        }
    }
    if (!$tenantSchemaReady) {
        ensureTenantSchema($pdo);
        $tenantSchemaReady = true;
    }
    return $pdo;
}

function ensureTenantSchema(PDO $db) {
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        ip_address VARCHAR(45) NOT NULL PRIMARY KEY,
        failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        window_started_at DATETIME NOT NULL,
        locked_until DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    foreach (['employees', 'contracts'] as $table) {
        $categoryColumn = $db->query("SHOW COLUMNS FROM `$table` LIKE 'categorie'")->fetch();
        if ($categoryColumn && stripos($categoryColumn['Type'], 'enum(') === 0) {
            $db->exec("ALTER TABLE `$table` MODIFY categorie VARCHAR(50) NOT NULL DEFAULT 'À préciser'");
            $db->exec("UPDATE `$table` SET categorie = CASE
                WHEN categorie IS NULL OR TRIM(categorie) = '' THEN 'À préciser'
                WHEN categorie LIKE 'Agent de ma%' THEN 'Agent de maîtrise'
                ELSE categorie
                END
                WHERE categorie IS NULL OR TRIM(categorie) = '' OR categorie LIKE 'Agent de ma%'");
        }
    }

    $userColumns = $db->query("SHOW COLUMNS FROM users LIKE 'localisation'")->fetch();
    if (!$userColumns) {
        $db->exec("ALTER TABLE users ADD COLUMN localisation VARCHAR(255) NOT NULL DEFAULT '' AFTER address");
    }

    $column = $db->query("SHOW COLUMNS FROM employees LIKE 'user_id'")->fetch();
    if (!$column) {
        $db->exec("ALTER TABLE employees ADD COLUMN user_id INT NULL AFTER id");
        $column = ['Null' => 'YES'];
    }

    $legacyOwnerId = $db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
    if ($legacyOwnerId) {
        $stmt = $db->prepare("UPDATE employees SET user_id = ? WHERE user_id IS NULL");
        $stmt->execute([$legacyOwnerId]);
    }

    if ($column['Null'] === 'YES' && (int)$db->query("SELECT COUNT(*) FROM employees WHERE user_id IS NULL")->fetchColumn() === 0) {
        $db->exec("ALTER TABLE employees MODIFY user_id INT NOT NULL");
    }

    $indexes = $db->query("SHOW INDEX FROM employees")->fetchAll();
    $hasOwnerIndex = false;
    $hasOldMatriculeIndex = false;
    $hasTenantMatriculeIndex = false;
    foreach ($indexes as $index) {
        if ($index['Key_name'] === 'idx_employees_user_id') {
            $hasOwnerIndex = true;
        }
        if ($index['Key_name'] === 'matricule' && (int)$index['Non_unique'] === 0) {
            $hasOldMatriculeIndex = true;
        }
        if ($index['Key_name'] === 'unique_user_matricule') {
            $hasTenantMatriculeIndex = true;
        }
    }
    if ($hasOldMatriculeIndex) {
        $db->exec("ALTER TABLE employees DROP INDEX matricule");
    }
    if (!$hasOwnerIndex) {
        $db->exec("ALTER TABLE employees ADD INDEX idx_employees_user_id (user_id)");
    }
    if (!$hasTenantMatriculeIndex) {
        $db->exec("ALTER TABLE employees ADD UNIQUE KEY unique_user_matricule (user_id, matricule)");
    }

    $foreignKeys = $db->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'employees' AND CONSTRAINT_TYPE = 'FOREIGN KEY'")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('fk_employee_user', $foreignKeys, true)) {
        $db->exec("ALTER TABLE employees ADD CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE");
    }
}

function getLoginLockRemainingSeconds(PDO $db, string $ipAddress): int
{
    $stmt = $db->prepare("SELECT GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until))
        FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);

    return (int)$stmt->fetchColumn();
}

function recordFailedLogin(PDO $db, string $ipAddress): int
{
    $db->beginTransaction();

    try {
        $insert = $db->prepare("INSERT IGNORE INTO login_attempts
            (ip_address, failed_attempts, window_started_at, locked_until)
            VALUES (?, 0, NOW(), NULL)");
        $insert->execute([$ipAddress]);

        $stmt = $db->prepare("SELECT failed_attempts,
                window_started_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE) AS window_expired,
                GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS lock_remaining
            FROM login_attempts WHERE ip_address = ? FOR UPDATE");
        $stmt->execute([$ipAddress]);
        $attempt = $stmt->fetch();

        if ((int)$attempt['lock_remaining'] > 0) {
            $db->commit();
            return (int)$attempt['lock_remaining'];
        }

        $failedAttempts = (int)$attempt['window_expired'] === 1
            ? 1
            : min(3, (int)$attempt['failed_attempts'] + 1);
        $update = $db->prepare("UPDATE login_attempts
            SET failed_attempts = ?,
                window_started_at = IF(? = 1, NOW(), window_started_at),
                locked_until = IF(? >= 3, DATE_ADD(NOW(), INTERVAL 15 MINUTE), NULL)
            WHERE ip_address = ?");
        $update->execute([
            $failedAttempts,
            (int)$attempt['window_expired'],
            $failedAttempts,
            $ipAddress
        ]);

        $remaining = getLoginLockRemainingSeconds($db, $ipAddress);
        $db->commit();
        return $remaining;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

function clearLoginAttempts(PDO $db, string $ipAddress): void
{
    $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ipAddress]);
}

// Fonction pour récupérer la configuration de l'entreprise gabonaise
function getCompanyConfig() {
    $db = getDB();
    if (!empty($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
    } else {
        $stmt = $db->query("SELECT * FROM users ORDER BY id ASC LIMIT 1");
    }
    $company = $stmt->fetch();
    if (!$company) {
        return [
            'id' => 1,
            'company_name' => '',
            'email' => '',
            'phone' => '',
            'address' => '',
            'localisation' => '',
            'siret' => '',
            'nif' => '',
            'rccm' => '',
            'cnss_employer' => '',
            'currency' => 'FCFA'
        ];
    }
    return $company;
}

function getCompanyLocationLabel($company = null) {
    $company = $company ?: getCompanyConfig();
    $localisation = trim((string)($company['localisation'] ?? ''));
    if ($localisation !== '') {
        return $localisation;
    }

    $address = trim((string)($company['address'] ?? ''));
    if ($address !== '') {
        return $address;
    }

    return 'Gabon';
}

// Supprime les contrats archivés depuis plus de 60 jours.
function purgeExpiredTrashContracts($days = 60) {
    $days = max(1, (int)$days);
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return 0;
    }
    $db = getDB();
    $stmt = $db->prepare("DELETE c FROM contracts c
        JOIN employees e ON e.id = c.employee_id
        WHERE status = 'corbeille'
        AND e.user_id = ?
        AND corbeille_date IS NOT NULL
        AND corbeille_date < DATE_SUB(NOW(), INTERVAL $days DAY)");
    $stmt->execute([$userId]);
    return $stmt->rowCount();
}

// Génération automatique du matricule unique : AGE-ANNEE-XXXX
function generateNextMatricule($userId = null) {
    $db = getDB();
    $userId = (int)($userId ?? ($_SESSION['user_id'] ?? 0));
    $year = date('Y');
    $prefix = "AGE-" . $year . "-";
    
    $stmt = $db->prepare("SELECT matricule FROM employees WHERE user_id = ? AND matricule LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $prefix . "%"]);
    $lastMatricule = $stmt->fetchColumn();
    
    if ($lastMatricule) {
        $parts = explode('-', $lastMatricule);
        $num = intval(end($parts)) + 1;
    } else {
        $num = 1;
    }
    
    return sprintf("AGE-%s-%04d", $year, $num);
}

// Formatage de la monnaie en FCFA
function formatCurrency($amount, $currency = 'FCFA') {
    return number_format((float)$amount, 0, ',', ' ') . ' ' . htmlspecialchars($currency ?: 'FCFA');
}

// Récupération des contrats à échéance dans les $days prochains jours (par défaut 30 jours)
function getExpiringContracts($days = 30) {
    $db = getDB();
    $sql = "SELECT c.*, e.matricule, e.nom, e.prenom, e.poste as emp_poste, e.telephone, e.email as emp_email,
            DATEDIFF(c.date_fin, CURDATE()) as jours_restants
            FROM contracts c
            JOIN employees e ON c.employee_id = e.id
            WHERE c.status = 'actif'
            AND e.user_id = ?
            AND c.type_contrat = 'CDD'
            AND c.date_fin IS NOT NULL
            AND DATEDIFF(c.date_fin, CURDATE()) <= ?
            ORDER BY c.date_fin ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0), $days]);
    return $stmt->fetchAll();
}

// Calcul de la masse salariale mensuelle totale estimée en FCFA
function getTotalPayrollEstimate() {
    $db = getDB();
    $sql = "SELECT type_remuneration, salaire_base FROM employees WHERE statut = 'actif' AND user_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $employees = $stmt->fetchAll();
    
    $total = 0;
    foreach ($employees as $emp) {
        if ($emp['type_remuneration'] === 'journalier') {
            $total += ($emp['salaire_base'] * 26); // Base 26 jours ouvrables au Gabon
        } else {
            $total += $emp['salaire_base'];
        }
    }
    return $total;
}

// Convertisseur nombres en lettres français
function nombreEnLettres($nombre) {
    $nombre = intval($nombre);
    if ($nombre == 0) return 'zéro';
    
    $unites = ['', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf', 'dix', 
               'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize', 'dix-sept', 'dix-huit', 'dix-neuf'];
    $dizaines = ['', '', 'vingt', 'trente', 'quarante', 'cinquante', 'soixante', 'soixante-dix', 'quatre-vingt', 'quatre-vingt-dix'];
    
    if ($nombre < 20) {
        return $unites[$nombre];
    }
    if ($nombre < 100) {
        $d = intval($nombre / 10);
        $u = $nombre % 10;
        if ($d == 7) return 'soixante-' . ($u == 1 ? 'et-onze' : $unites[10 + $u]);
        if ($d == 9) return 'quatre-vingt-' . $unites[10 + $u];
        if ($u == 0) return $dizaines[$d] . ($d == 8 ? 's' : '');
        if ($u == 1 && $d != 8) return $dizaines[$d] . '-et-un';
        return $dizaines[$d] . '-' . $unites[$u];
    }
    if ($nombre < 1000) {
        $c = intval($nombre / 100);
        $reste = $nombre % 100;
        $str = ($c > 1 ? $unites[$c] . ' ' : '') . 'cent' . ($c > 1 && $reste == 0 ? 's' : '');
        if ($reste > 0) $str .= ' ' . nombreEnLettres($reste);
        return $str;
    }
    if ($nombre < 1000000) {
        $m = intval($nombre / 1000);
        $reste = $nombre % 1000;
        $str = ($m > 1 ? nombreEnLettres($m) . ' ' : '') . 'mille';
        if ($reste > 0) $str .= ' ' . nombreEnLettres($reste);
        return $str;
    }
    if ($nombre < 1000000000) {
        $mil = intval($nombre / 1000000);
        $reste = $nombre % 1000000;
        $str = nombreEnLettres($mil) . ' million' . ($mil > 1 ? 's' : '');
        if ($reste > 0) $str .= ' ' . nombreEnLettres($reste);
        return $str;
    }
    return (string)$nombre;
}
