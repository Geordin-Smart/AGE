# Historique des modifications

Ce fichier recense les modifications realisees dans l'application AGE.

## 1. Configuration de l'entreprise

- Suppression des informations predefinies de l'entreprise SGLS.
- Suppression des anciennes coordonnees et identifiants d'exemple : telephone, adresse, NIF, RCCM et CNSS.
- Le formulaire d'inscription demande maintenant :
  - le nom de l'entreprise ;
  - le numero de telephone ;
  - l'adresse ;
  - le NIF ;
  - le RCCM ;
  - le numero employeur CNSS.
- Les donnees saisies sont enregistrees directement dans la table `users`.
- Les contrats et fiches de paie affichent automatiquement les coordonnees de l'entreprise connectee.
- La configuration est maintenant chargee selon l'utilisateur connecte, et non plus toujours selon le premier utilisateur.
- Le schema `install.sql` contient les colonnes `nif`, `rccm` et `cnss_employer`.
- Le schema d'installation declare aussi le proprietaire de chaque employe et ses numeros CNSS/CNAMGS.
- Les valeurs SGLS ont ete retirees du script `seed.php` et des valeurs de remplacement des documents.
- Le compte SGLS present dans la base locale a ete nettoye sans supprimer ses identifiants de connexion.

Fichiers principaux :

- `index.php`
- `config/database.php`
- `settings.php`
- `install.sql`
- `seed.php`
- `view_contract.php`
- `view_payslip.php`
- `includes/header.php`
- `includes/sidebar.php`

## 2. Impression multiple des employes

- Ajout de cases a cocher dans la liste des employes.
- Ajout d'une case permettant de selectionner tous les employes.
- Ajout d'un compteur de selection.
- Ajout de deux commandes :
  - imprimer les contrats selectionnes ;
  - imprimer les fiches de paie selectionnees.
- Creation de `batch_print.php` pour afficher plusieurs documents sur une seule page d'impression.
- Les contrats actifs sont imprimes pour les employes selectionnes.
- Pour les fiches de paie, le dernier bulletin enregistre est utilise.
- Le format des fiches de paie imprimees en groupe reprend maintenant celui du bulletin individuel : informations du salarie et de la periode, detail des gains et retenues, net en lettres et signatures.
- L'en-tete des bulletins groupes affiche la periode et le numero du bulletin comme le document individuel.
- Un avertissement est affiche lorsqu'un employe ne possede aucun document correspondant.
- Chaque document est separe automatiquement lors de l'impression.

Fichiers concernes :

- `employees.php`
- `batch_print.php` (nouveau fichier)

## 3. Gestion de la corbeille des contrats

- Ajout d'une suppression definitive volontaire depuis la corbeille.
- Une confirmation est demandee avant toute suppression definitive.
- Ajout d'une suppression automatique des contrats places dans la corbeille depuis plus de 60 jours.
- La purge ne concerne que les contrats avec `status = 'corbeille'`.
- La purge automatique est executee lors de l'utilisation des pages authentifiees.
- La restauration des contrats reste disponible.

Fichiers concernes :

- `contracts_trash.php`
- `config/database.php`
- `includes/sidebar.php`

## 4. Interface

- Suppression du footer uniquement sur la page de gestion des employes.
- Le footer partage des autres pages n'a pas ete modifie.

Fichier concerne :

- `employees.php`

## 5. Espaces de travail séparés par entreprise

- Chaque employé est maintenant rattaché au compte de l'entreprise qui l'a créé.
- Les contrats et fiches de paie sont isolés par l'intermédiaire de leur employé associé.
- Les listes, tableaux de bord, alertes, corbeilles et impressions ne présentent que les données de l'entreprise connectée.
- Les pages de consultation et de modification vérifient aussi que le document appartient au compte connecté.
- Les matricules sont uniques par entreprise, ce qui permet à deux entreprises d'avoir chacune leur propre séquence.
- Une migration automatique ajoute `employees.user_id`, les index et la clé étrangère nécessaires au premier accès.
- Les employés historiques ont été rattachés au compte le plus ancien déjà enregistré; les nouveaux comptes commencent avec un espace vide.
- La colonne propriétaire est obligatoire une fois les données historiques attribuées.
- Le script `seed.php` refuse désormais de s'exécuter si la base contient déjà un compte, afin de ne pas effacer des données existantes.
- Après inscription, l'entreprise est connectée à son propre tableau de bord et ses données restent associées à son compte.

Fichiers principaux :

- `config/database.php`
- `install.sql`
- `index.php`
- `employees.php`
- `dashboard.php`
- `contracts.php`
- `contracts_trash.php`
- `payslips.php`
- `generate_payslip.php`
- `view_contract.php`
- `view_payslip.php`
- `edit_contract.php`
- `batch_print.php`
- `includes/sidebar.php`
- `seed.php`

## 6. Validations realisees

- Verification de syntaxe PHP avec `C:\xampp\php\php.exe -l`.
- Diagnostics VS Code sans erreur sur les fichiers modifies.
- Verification des colonnes SQL necessaires dans la base locale.
- Verification des requetes de recuperation des contrats et des fiches de paie.
- Verification de la regle de conservation de 60 jours dans la corbeille.
- Migration du proprietaire executee sur la base locale; les employes historiques sont rattaches au premier compte.
- Test transactionnel d'isolation avec un compte temporaire : employe et contrat visibles uniquement sous ce compte, puis transaction annulee.
- Verification qu'aucune donnee temporaire de test n'est restee dans la base.
- Verification syntaxique des 19 fichiers PHP du projet.

## Fonctionnement hors connexion

L'application fonctionne localement avec XAMPP, Apache et MySQL. Internet n'est necessaire que pour charger les polices Google Fonts et les icones Font Awesome depuis leurs CDN.

## Cotisations des salaries journaliers

- Les cotisations sociales estimees sont fixees a zero pour les salaries payes a la journee.
- A l'emission du bulletin, le serveur verifie le type de remuneration en base et force les cotisations sociales a zero pour un journalier, meme si une autre valeur est envoyee par le formulaire.
- Le champ de cotisations sociales est en lecture seule et affiche zero pour ces salaries.
- Les calculs des salaries payes au mois ne changent pas.
- Les eventuelles retenues fiscales restent distinctes des cotisations sociales.

Fichier concerne : `generate_payslip.php`.

## Format A4 des documents telecharges

- Les contrats et bulletins PDF telecharges utilisent le meme format A4 portrait et les memes marges de 25 mm que l'impression directe.
- Les styles de compactage permettant de tenir sur une page sont aussi appliques au rendu PDF Dompdf.
- Les informations en deux colonnes des bulletins restent disposees cote a cote dans les PDF telecharges.
- Les documents utilisent une police Arial/Calibri/Times New Roman avec une police de remplacement, et un interlignage simple.
- Les titres et clauses des contrats sont separes visuellement pour faciliter la lecture rapide, avec un espacement accru entre chaque article a l'impression et dans les PDF.
- Le format A4 paysage et les marges de 25 mm de la liste des employes sont identiques a l'impression directe et au PDF.
- Les contenus sont limites a la largeur disponible entre les marges et les mots longs peuvent revenir a la ligne dans les documents et tableaux.
- Les documents longs peuvent continuer sur une page suivante au lieu d'etre coupes ou de depasser la zone imprimable; les documents groupes commencent chacun sur une nouvelle page.
- La taille des textes de tous les documents imprimables et PDF est portee a 11 pt, y compris les listes.
- Les zones de signature sont placees plus bas avec une separation administrative et deux colonnes, tout en restant groupees avec le document lorsque le contenu le permet.
- Un espace blanc de 24 mm est reserve entre les intitulés et les lignes de signature pour le cachet de l'entreprise et la signature manuscrite du salarie, sur les contrats et bulletins imprimes ou telecharges.
- Une feuille de styles PDF dediee applique les memes tailles de texte aux exports Dompdf.

Fichiers concernes : `assets/css/style.css`, `assets/css/pdf-document-overrides.css`, `view_contract.php`, `view_payslip.php` et `download_pdf.php`.

## Simplification des actions PDF

- Suppression des boutons de telechargement PDF redondants dans les pages de contrats, bulletins et liste des employes.
- L'impression navigateur reste disponible et permet d'enregistrer le document en PDF via la destination d'impression.
- L'export CSV de la liste des employes est conserve.

Fichiers concernes : `contracts.php`, `payslips.php`, `view_contract.php`, `view_payslip.php` et `employees.php`.

## Corrections de securite CSRF

- La suppression d'un employe et la restauration d'un contrat ne sont plus declenchees par des requetes GET.
- Ces actions sont reservees a des formulaires POST qui contiennent un jeton CSRF aleatoire de session, verifie cote serveur.
- La suppression definitive d'un contrat dans la corbeille exige egalement ce jeton.
- Les requetes sans jeton valide sont refusees avec une reponse HTTP 403.

Fichiers concernes : `includes/auth_check.php`, `employees.php` et `contracts_trash.php`.
