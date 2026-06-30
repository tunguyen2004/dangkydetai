    </main>
    <?php if (empty($hide_site_chrome)): ?>
        <footer class="site-footer">
            <span>K73_nhom10</span>
            <span><?= e(APP_NAME) ?> · <?= e(date('Y')) ?></span>
        </footer>
    <?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset_url('assets/js/main.js')) ?>"></script>
</body>
</html>
