<?php
/**
 * Email : annonce rejetée avec motif.
 * Variables : $prenom, $intitule, $motif
 */
declare(strict_types=1);

$prenom   = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$intitule = htmlspecialchars((string) ($intitule ?? ''), ENT_QUOTES, 'UTF-8');
$motif    = nl2br(htmlspecialchars((string) ($motif ?? ''), ENT_QUOTES, 'UTF-8'));

$titre = 'Votre annonce n\'a pas été retenue';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Votre annonce « <strong><?= $intitule ?></strong> » n'a pas pu être publiée
   pour la raison suivante :</p>
<div style="background:#fdecea;border:1px solid #f5c6c2;border-radius:6px;padding:12px;margin:0 0 12px;">
  <?= $motif ?>
</div>
<p style="margin:0;">Vous pouvez modifier votre annonce depuis votre espace organisateur
   puis la soumettre à nouveau.</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
