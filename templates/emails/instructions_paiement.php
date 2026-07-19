<?php
/**
 * Email : instructions de paiement (organisateur non abonné).
 * Variables : $prenom, $intitule, $montant (float), $instructions (string)
 */
declare(strict_types=1);

$prenom       = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$intitule     = htmlspecialchars((string) ($intitule ?? ''), ENT_QUOTES, 'UTF-8');
$montant      = number_format((float) ($montant ?? 10), 2, ',', ' ');
// Instructions : texte libre admin → échappé puis sauts de ligne en <br>
$instructions = nl2br(htmlspecialchars((string) ($instructions ?? ''), ENT_QUOTES, 'UTF-8'));

$titre = 'Instructions de paiement';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Votre annonce « <strong><?= $intitule ?></strong> » a bien été enregistrée.
   Pour qu'elle soit publiée, un règlement de <strong><?= $montant ?> €</strong> est attendu.</p>
<div style="background:#fff8ef;border:1px solid #f0d6ad;border-radius:6px;padding:12px;margin:0 0 12px;">
  <?= $instructions ?>
</div>
<p style="margin:0;">Dès réception de votre paiement, l'administrateur validera votre annonce
   et vous en serez informé par email.</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
