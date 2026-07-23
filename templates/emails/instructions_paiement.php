<?php
/**
 * Email : instructions de paiement (organisateur non abonné).
 * Variables : $prenom, $intitule, $montant (float), $instructions (string),
 *             $lien_paiement (string, URL WooCommerce avec email pré-rempli),
 *             $email (string, email du compte).
 */
declare(strict_types=1);

$prenom       = htmlspecialchars((string) ($prenom ?? ''), ENT_QUOTES, 'UTF-8');
$intitule     = htmlspecialchars((string) ($intitule ?? ''), ENT_QUOTES, 'UTF-8');
$montant      = number_format((float) ($montant ?? 10), 2, ',', ' ');
$lienPaiement = (string) ($lien_paiement ?? '');
$emailCompte  = htmlspecialchars((string) ($email ?? ''), ENT_QUOTES, 'UTF-8');
$lienHtml     = htmlspecialchars($lienPaiement, ENT_QUOTES, 'UTF-8');
// Instructions : texte libre admin → échappé puis sauts de ligne en <br>
$instructions = nl2br(htmlspecialchars((string) ($instructions ?? ''), ENT_QUOTES, 'UTF-8'));

$titre = 'Instructions de paiement';

ob_start(); ?>
<p style="margin:0 0 12px;">Bonjour <?= $prenom ?>,</p>
<p style="margin:0 0 12px;">Votre annonce « <strong><?= $intitule ?></strong> » a bien été enregistrée.
   Pour qu'elle soit publiée, un règlement de <strong><?= $montant ?> €</strong> est attendu.</p>
<?php if ($lienPaiement !== ''): ?>
<p style="margin:0 0 16px;">
  <a href="<?= $lienHtml ?>"
     style="display:inline-block;background:#000;color:#ffc800;text-decoration:none;
            font-weight:600;padding:12px 20px;border-radius:4px;">
    Payer <?= $montant ?> € en ligne
  </a>
</p>
<p style="margin:0 0 12px;">Merci de régler avec le <strong>même email que votre compte</strong>
   <?= $emailCompte !== '' ? '(<strong>' . $emailCompte . '</strong>) ' : '' ?>afin que nous
   puissions rapprocher votre paiement de votre annonce.</p>
<?php else: ?>
<div style="background:#fff8ef;border:1px solid #f0d6ad;border-radius:6px;padding:12px;margin:0 0 12px;">
  <?= $instructions ?>
</div>
<?php endif; ?>
<p style="margin:0;">Dès réception de votre paiement, l'administrateur validera votre annonce
   et vous en serez informé par email.</p>
<?php
$corps = ob_get_clean();
include __DIR__ . '/_layout.php';
