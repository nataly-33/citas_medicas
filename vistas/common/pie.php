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

<script src="<?= $urlBase ?>/publico/js/aplicacion.js"></script>
<?php if (!empty($script_modulo)): ?>
<script src="<?= $urlBase ?>/publico/js/<?= htmlspecialchars($script_modulo) ?>"></script>
<?php endif; ?>

</body>
</html>
