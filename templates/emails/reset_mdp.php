<?php
/**
 * Email : réinitialisation du mot de passe.
 * Variables : $prenom, $lien
 */
declare(strict_types=1);

$prenom = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$lien   = htmlspecialchars((string) ($lien ?? ''), ENT_QUOTES, 'UTF-8');

$titre = 'Réinitialisation de votre mot de passe';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le
   bouton ci-dessous pour en choisir un nouveau. Ce lien est valable une heure.</p>
<p style="margin:16px 0;">
  <a href="<?= $lien ?>" style="background:#000000;color:#ffc800;text-decoration:none;
     padding:10px 18px;border-radius:6px;display:inline-block;">Choisir un nouveau mot de passe</a>
</p>
<p style="margin:0;color:#6b736f;font-size:13px;">Si vous n'êtes pas à l'origine de cette demande,
   ignorez simplement cet email.</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
