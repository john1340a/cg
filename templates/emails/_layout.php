<?php
/**
 * Enveloppe HTML commune aux emails.
 * $titre  : titre affiché en en-tête
 * $corps  : HTML déjà échappé du contenu
 */
declare(strict_types=1);
/** @var string $titre */
/** @var string $corps */
?>
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f6f5;font-family:Arial,Helvetica,sans-serif;color:#333;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f5;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="560" cellpadding="0" cellspacing="0"
             style="background:#fff;border-radius:8px;overflow:hidden;max-width:560px;width:100%;">
        <tr>
          <td style="background:#000000;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;letter-spacing:.04em;">
            <span style="color:#ffc800;">◆</span> Bourses aux Minéraux
          </td>
        </tr>
        <tr>
          <td style="padding:24px;">
            <h1 style="font-size:20px;margin:0 0 16px;color:#1b1b1b;font-weight:normal;letter-spacing:.03em;"><?= $titre ?></h1>
            <?= $corps ?>
          </td>
        </tr>
        <tr>
          <td style="padding:16px 24px;background:#f0f2f1;color:#6b736f;font-size:12px;">
            Cet email vous est envoyé par le calendrier des bourses aux minéraux.
            Données hébergées en France.
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
