<?php
declare(strict_types=1);

$pageScripts = $pageScripts ?? [];
?>
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/app.js"></script>
<?php foreach ($pageScripts as $script): ?>
    <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
