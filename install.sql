-- Base de données AGE (Application de Gestion des Employés)
CREATE DATABASE IF NOT EXISTS `age_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `age_db`;

-- Table des utilisateurs / Configuration Entreprise
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(255) NOT NULL DEFAULT '',
    `email` VARCHAR(191) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) DEFAULT '',
    `address` TEXT DEFAULT '',
    `localisation` VARCHAR(255) NOT NULL DEFAULT '',
    `siret` VARCHAR(100) DEFAULT '',
    `nif` VARCHAR(100) DEFAULT '',
    `rccm` VARCHAR(100) DEFAULT '',
    `cnss_employer` VARCHAR(100) DEFAULT '',
    `currency` VARCHAR(10) DEFAULT 'FCFA',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Limitation des tentatives de connexion par adresse IP
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `ip_address` VARCHAR(45) NOT NULL PRIMARY KEY,
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `locked_until` DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des employés
CREATE TABLE IF NOT EXISTS `employees` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `matricule` VARCHAR(50) NOT NULL,
    `nom` VARCHAR(100) NOT NULL,
    `prenom` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NULL,
    `telephone` VARCHAR(50) NULL,
    `date_naissance` DATE NULL,
    `adresse` TEXT NULL,
    `cnss_number` VARCHAR(50) NULL,
    `cnamgs_number` VARCHAR(50) NULL,
    `poste` VARCHAR(150) NOT NULL,
    `categorie` VARCHAR(50) NOT NULL DEFAULT 'À préciser',
    `type_remuneration` ENUM('mensuel', 'journalier') NOT NULL,
    `salaire_base` DECIMAL(12,2) NOT NULL,
    `type_contrat` ENUM('CDI', 'CDD') NOT NULL,
    `date_embauche` DATE NOT NULL,
    `date_debut_contrat` DATE NOT NULL,
    `date_fin_contrat` DATE NULL,
    `statut` ENUM('actif', 'inactif') DEFAULT 'actif',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_matricule` (`user_id`, `matricule`),
    INDEX `idx_employees_user_id` (`user_id`),
    CONSTRAINT `fk_employee_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des contrats avec système de corbeille / historique
CREATE TABLE IF NOT EXISTS `contracts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `contract_number` VARCHAR(60) NOT NULL,
    `version` INT NOT NULL DEFAULT 1,
    `type_contrat` ENUM('CDI', 'CDD') NOT NULL,
    `poste` VARCHAR(150) NOT NULL,
    `categorie` VARCHAR(50) NOT NULL DEFAULT 'À préciser',
    `type_remuneration` ENUM('mensuel', 'journalier') NOT NULL,
    `salaire_base` DECIMAL(12,2) NOT NULL,
    `date_debut` DATE NOT NULL,
    `date_fin` DATE NULL,
    `clauses_notes` TEXT NULL,
    `status` ENUM('actif', 'corbeille') NOT NULL DEFAULT 'actif',
    `corbeille_date` DATETIME NULL,
    `corbeille_reason` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_employee_status` (`employee_id`, `status`),
    INDEX `idx_date_fin` (`date_fin`),
    CONSTRAINT `fk_contract_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des fiches de paie
CREATE TABLE IF NOT EXISTS `payslips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT NOT NULL,
    `slip_number` VARCHAR(60) NOT NULL UNIQUE,
    `period_month` INT NOT NULL,
    `period_year` INT NOT NULL,
    `period_label` VARCHAR(50) NOT NULL,
    `type_remuneration` ENUM('mensuel', 'journalier') NOT NULL,
    `taux_journalier_ou_base` DECIMAL(12,2) NOT NULL,
    `nb_jours_travailles` DECIMAL(5,1) DEFAULT 30.0,
    `salaire_brut` DECIMAL(12,2) NOT NULL,
    `primes` DECIMAL(12,2) DEFAULT 0.00,
    `indemnites` DECIMAL(12,2) DEFAULT 0.00,
    `cotisations_sociales` DECIMAL(12,2) DEFAULT 0.00,
    `retenues_fiscales` DECIMAL(12,2) DEFAULT 0.00,
    `autres_retenues` DECIMAL(12,2) DEFAULT 0.00,
    `salaire_net` DECIMAL(12,2) NOT NULL,
    `mode_paiement` VARCHAR(50) DEFAULT 'Virement bancaire',
    `commentaires` TEXT NULL,
    `generated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_period` (`period_year`, `period_month`),
    CONSTRAINT `fk_payslip_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
