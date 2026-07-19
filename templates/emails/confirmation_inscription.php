<?php
/**
 * Email : confirmation d'inscription.
 * Variables : $prenom (string), $est_abonne (bool), $app_url (string)
 */
declare(strict_types=1);

$prenom    = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$estAbonne = !empty($est_abonne);
$appUrl    = htmlspecialchars((string) ($app_url ?? ''), ENT_QUOTES, 'UTF-8');

$titre = 'Bienvenue !';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Votre compte organisateur a bien été créé. Vous pouvez dès à présent
   publier vos annonces de bourses aux minéraux.</p>
<?php if ($estAbonne): ?>
<p style="margin:0 0 12px;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:6px;padding:10px;">
   Votre adresse figure parmi les abonnés à la revue :
   <strong>votre première annonce est gratuite</strong>.</p>
<?php endif; ?>
<p style="margin:16px 0 0;">
  <a href="<?= $appUrl ?>/compte/" style="background:#000000;color:#ffc800;text-decoration:none;
     padding:10px 18px;border-radius:6px;display:inline-block;">Accéder à mon espace</a>
</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
