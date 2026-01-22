<?php /* STYLE/MODERN - Footer */
if (!defined('DP_BASE_DIR')) {
    die('You should not access this file directly');
}
$dialog = (int) dPgetParam($_GET, 'dialog', 0);
?>
</div>
</main>
</div>

<?php if (empty($dialog)) { ?>
    <!-- Footer -->
    <footer style="
            background: var(--bg-card);
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            text-align: center;
            margin-left: var(--sidebar-width);
            font-size: 0.75rem;
            color: var(--text-muted);
        ">
        <p style="margin: 0;">
            Powered by <strong>dotProject</strong>
            <?php echo @$AppUI->getVersion(); ?>
            &bull;
            <?php echo date('Y'); ?>
        </p>
    </footer>
<?php } ?>

</body>

</html>