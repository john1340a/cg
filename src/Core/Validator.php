<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Petit validateur d'entrées : accumule des erreurs par champ.
 *
 * Usage :
 *     $v = new Validator($data);
 *     $v->required('email')->email('email');
 *     if ($v->fails()) Response::error('Données invalides', 422, ['champs' => $v->errors()]);
 */
final class Validator
{
    /** @var array<string,mixed> */
    private array $data;
    /** @var array<string,string> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    private function value(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function isEmpty(string $field): bool
    {
        $v = $this->value($field);
        return $v === null || (is_string($v) && trim($v) === '')
            || (is_array($v) && count($v) === 0);
    }

    public function required(string $field, string $message = 'Ce champ est obligatoire.'): self
    {
        if ($this->isEmpty($field) && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function email(string $field, string $message = 'Adresse email invalide.'): self
    {
        $v = $this->value($field);
        if (!$this->isEmpty($field)
            && (!is_string($v) || !filter_var($v, FILTER_VALIDATE_EMAIL))
            && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function minLength(string $field, int $min, ?string $message = null): self
    {
        $v = $this->value($field);
        if (!$this->isEmpty($field) && is_string($v) && mb_strlen($v) < $min
            && !isset($this->errors[$field])) {
            $this->errors[$field] = $message ?? "Minimum $min caractères.";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, ?string $message = null): self
    {
        $v = $this->value($field);
        if (is_string($v) && mb_strlen($v) > $max && !isset($this->errors[$field])) {
            $this->errors[$field] = $message ?? "Maximum $max caractères.";
        }
        return $this;
    }

    /**
     * Valide un format de date AAAA-MM-JJ réel.
     */
    public function date(string $field, string $message = 'Date invalide (format AAAA-MM-JJ).'): self
    {
        $v = $this->value($field);
        if (!$this->isEmpty($field) && !isset($this->errors[$field])) {
            $d = is_string($v) ? \DateTime::createFromFormat('Y-m-d', $v) : false;
            if (!$d || $d->format('Y-m-d') !== $v) {
                $this->errors[$field] = $message;
            }
        }
        return $this;
    }

    /**
     * URL http/https optionnelle.
     */
    public function url(string $field, string $message = 'URL invalide.'): self
    {
        $v = $this->value($field);
        if (!$this->isEmpty($field) && is_string($v)
            && !filter_var($v, FILTER_VALIDATE_URL)
            && !isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    /**
     * @return array<string,string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
