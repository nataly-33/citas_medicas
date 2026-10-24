<?php
/**
 * Parcial de Cierre de Página y Carga de Scripts
 * Sistema Clínica Vida Salud - Sistemas de Información 1 (UAGRM)
 */

$urlBase = defined('URL_BASE') ? URL_BASE : '/citas_medicas';
?>
<?php if (!empty($_SESSION['autenticado'])): ?>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/modal_confirmar.php'; ?>
<?php endif; ?>

<script>
    window.URL_BASE = '<?= defined('URL_BASE') ? URL_BASE : '' ?>';
</script>
<script src="<?= $urlBase ?>/public/js/aplicacion.js"></script>
<?php if (!empty($script_modulo)): ?>
<script src="<?= $urlBase ?>/public/js/<?= htmlspecialchars($script_modulo) ?>"></script>
<?php endif; ?>

</body>
</html>
