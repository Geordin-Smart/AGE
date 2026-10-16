        </main>
        
        <footer style="padding: 12px 28px; border-top: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; font-size: 0.72rem; color: var(--text-muted); background: rgba(12, 7, 13, 0.7);">
            <div>
                &copy; <?= date('Y') ?> <strong>AGE Gabon</strong> &bull; Application de Gestion des Employés, Contrats & Paie
            </div>
            <div>
                <?= htmlspecialchars(getCompanyLocationLabel($company ?? getCompanyConfig())) ?> &bull; République Gabonaise
            </div>
        </footer>
    </div>
</div>

<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
