<?php
/**
 * Email : annonce validée et publiée.
 * Variables : $prenom, $intitule, $app_url
 */
declare(strict_types=1);

$prenom   = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$intitule = htmlspecialchars((string) ($intitule ?? ''), ENT_QUOTES, 'UTF-8');
$appUrl   = htmlspecialchars((string) ($app_url ?? ''), ENT_QUOTES, 'UTF-8');

$titre = 'Votre annonce est publiée';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Bonne nouvelle : votre annonce « <strong><?= $intitule ?></strong> »
   a été validée et est désormais <strong>visible sur la carte publique</strong>.</p>
<p style="margin:16px 0 0;">
  <a href="<?= $appUrl ?>/carte.html" style="background:#000000;color:#ffc800;text-decoration:none;
     padding:10px 18px;border-radius:6px;display:inline-block;">Voir la carte</a>
</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
