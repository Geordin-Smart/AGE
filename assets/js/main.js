/**
 * AGE - Application de Gestion des Employés
 * Scripts interactifs & Dynamisme UI/UX Pro Max
 */

document.addEventListener("DOMContentLoaded", function () {
  // 1. Menu Mobile Toggle
  const menuToggle = document.getElementById("menuToggle");
  const sidebar = document.getElementById("sidebar");

  if (menuToggle && sidebar) {
    menuToggle.addEventListener("click", function (e) {
      e.stopPropagation();
      sidebar.classList.toggle("open");
    });

    document.addEventListener("click", function (e) {
      if (
        sidebar.classList.contains("open") &&
        !sidebar.contains(e.target) &&
        e.target !== menuToggle
      ) {
        sidebar.classList.remove("open");
      }
    });
  }

  // 2. Gestion Dynamique Type de Contrat (CDD vs CDI)
  const typeContratSelect = document.getElementById("type_contrat");
  const dateFinGroup = document.getElementById("dateFinGroup");
  const dateFinInput = document.getElementById("date_fin_contrat");
  const dateDebutInput = document.getElementById("date_debut_contrat");

  function toggleDateFin() {
    if (!typeContratSelect || !dateFinGroup) return;
    if (dateFinInput && dateDebutInput) {
      dateFinInput.min = dateDebutInput.value;
    }
    if (typeContratSelect.value === "CDD") {
      dateFinGroup.style.display = "block";
      if (dateFinInput) dateFinInput.setAttribute("required", "required");
    } else {
      dateFinGroup.style.display = "none";
      if (dateFinInput) {
        dateFinInput.removeAttribute("required");
        dateFinInput.value = "";
      }
    }
  }

  if (typeContratSelect) {
    typeContratSelect.addEventListener("change", toggleDateFin);
    toggleDateFin(); // init
  }
  if (dateDebutInput) {
    dateDebutInput.addEventListener("change", toggleDateFin);
  }

  // 3. Gestion Dynamique Type de Rémunération (Mensuel vs Journalier)
  const typeRemunSelect = document.getElementById("type_remuneration");
  const salaireLabel = document.getElementById("salaire_label");

  function updateRemunLabel() {
    if (!typeRemunSelect || !salaireLabel) return;
    if (typeRemunSelect.value === "journalier") {
      salaireLabel.innerHTML =
        'Taux Journalier <span class="required">*</span>';
    } else {
      salaireLabel.innerHTML =
        'Salaire de Base Mensuel <span class="required">*</span>';
    }
  }

  if (typeRemunSelect) {
    typeRemunSelect.addEventListener("change", updateRemunLabel);
    updateRemunLabel();
  }

  // 4. Calculateur interactif de Fiche de Paie en temps réel
  const payBaseInput = document.getElementById("pay_base");
  const payDaysInput = document.getElementById("pay_days");
  const payPrimesInput = document.getElementById("pay_primes");
  const payIndemnitesInput = document.getElementById("pay_indemnites");
  const payCotisInput = document.getElementById("pay_cotis");
  const payRetenuesInput = document.getElementById("pay_retenues");
  const payNetPreview = document.getElementById("pay_net_preview");
  const payBrutPreview = document.getElementById("pay_brut_preview");

  function calculatePayslipLive() {
    if (!payNetPreview) return;

    const base = parseFloat(payBaseInput?.value || 0);
    const days = parseFloat(payDaysInput?.value || 30);
    const isJournalier =
      document.getElementById("pay_is_journalier")?.value === "1";

    const primes = parseFloat(payPrimesInput?.value || 0);
    const indemnites = parseFloat(payIndemnitesInput?.value || 0);
    const cotisations = parseFloat(payCotisInput?.value || 0);
    const retenues = parseFloat(payRetenuesInput?.value || 0);

    let salaireBaseCalcul = base;
    if (isJournalier) {
      salaireBaseCalcul = base * days;
    }

    const salaireBrut = salaireBaseCalcul + primes + indemnites;
    const totalDeductions = cotisations + retenues;
    const salaireNet = Math.max(0, salaireBrut - totalDeductions);

    if (payBrutPreview) {
      payBrutPreview.textContent =
        Math.round(salaireBrut).toLocaleString("fr-FR");
    }
    payNetPreview.textContent = Math.round(salaireNet).toLocaleString("fr-FR");
  }

  const calcInputs = [
    payBaseInput,
    payDaysInput,
    payPrimesInput,
    payIndemnitesInput,
    payCotisInput,
    payRetenuesInput,
  ];
  calcInputs.forEach((input) => {
    if (input) {
      input.addEventListener("input", calculatePayslipLive);
    }
  });
  calculatePayslipLive();

  // 5. Recherche instantanée dans les tableaux
  const tableSearchInput = document.getElementById("tableSearch");
  if (tableSearchInput) {
    tableSearchInput.addEventListener("keyup", function () {
      const filter = this.value.toLowerCase();
      const rows = document.querySelectorAll(".table-searchable tbody tr");

      rows.forEach((row) => {
        const text = row.innerText.toLowerCase();
        if (text.includes(filter)) {
          row.style.display = "";
        } else {
          row.style.display = "none";
        }
      });
    });
  }

  // 6. Bascule des onglets de Connexion / Enregistrement
  window.switchAuthTab = function (tab) {
    const loginForm = document.getElementById("loginForm");
    const registerForm = document.getElementById("registerForm");
    const tabLoginBtn = document.getElementById("tabLoginBtn");
    const tabRegisterBtn = document.getElementById("tabRegisterBtn");

    if (tab === "register") {
      if (loginForm) loginForm.style.display = "none";
      if (registerForm) registerForm.style.display = "block";
      if (tabLoginBtn) tabLoginBtn.classList.remove("active");
      if (tabRegisterBtn) tabRegisterBtn.classList.add("active");
    } else {
      if (loginForm) loginForm.style.display = "block";
      if (registerForm) registerForm.style.display = "none";
      if (tabLoginBtn) tabLoginBtn.classList.add("active");
      if (tabRegisterBtn) tabRegisterBtn.classList.remove("active");
    }
  };
});
