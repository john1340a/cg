<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Models\SettingModel;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Envoi des emails transactionnels.
 *
 * En local (MAIL_ENABLED=false), les messages sont écrits dans
 * storage/logs/mails/ au lieu d'être expédiés — pratique pour tester
 * le parcours sans SMTP. En production, envoi via SMTP alwaysdata.
 *
 * Les gabarits HTML sont dans templates/emails/.
 */
final class MailService
{
    private SettingModel $settings;

    public function __construct()
    {
        $this->settings = new SettingModel();
    }

    /**
     * Rend un gabarit d'email en injectant les variables.
     *
     * @param array<string,mixed> $vars
     */
    private function render(string $template, array $vars): string
    {
        $path = dirname(__DIR__, 2) . "/templates/emails/{$template}.php";
        if (!is_file($path)) {
            // Repli minimal si le gabarit manque (robustesse).
            return '<p>' . htmlspecialchars((string) ($vars['message'] ?? '')) . '</p>';
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }

    /**
     * Envoie un email à partir d'un gabarit.
     *
     * @param array<string,mixed> $vars
     */
    public function send(string $to, string $subject, string $template, array $vars = []): bool
    {
        $html = $this->render($template, $vars);

        $fromEmail = $this->settings->get('email_expediteur', Env::get('MAIL_FROM', 'no-reply@localhost'));
        $fromName  = $this->settings->get('nom_expediteur', Env::get('MAIL_FROM_NAME', 'Bourses aux Minéraux'));

        // --- Mode local : on n'expédie pas, on journalise ---
        if (!Env::bool('MAIL_ENABLED', false)) {
            $this->logToFile($to, $subject, $html);
            return true;
        }

        // --- Mode production : SMTP alwaysdata via PHPMailer ---
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = Env::get('SMTP_HOST', '');
            $mail->Port       = Env::int('SMTP_PORT', 587);
            $mail->SMTPAuth   = true;
            $mail->Username   = Env::get('SMTP_USER', '');
            $mail->Password   = Env::get('SMTP_PASS', '');
            $mail->CharSet    = 'UTF-8';

            $security = strtolower(Env::get('SMTP_SECURITY', 'tls'));
            if ($security === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($security === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $html;
            $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html)));

            $mail->send();
            return true;
        } catch (MailException $e) {
            error_log('[MailService] Échec envoi à ' . $to . ' : ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Écrit l'email dans un fichier (mode local / debug).
     */
    private function logToFile(string $to, string $subject, string $html): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs/mails';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // Suffixe aléatoire pour éviter les collisions (plusieurs envois/seconde).
        $name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6)
              . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $to) . '.html';
        $content = "<!-- À : {$to} | Sujet : {$subject} -->\n" . $html;
        file_put_contents($dir . '/' . $name, $content);
    }
}
