<?php
/**
 * Sanitize - Funções de sanitização e validação de entrada.
 * Equivalente direto de backend/sanitize.js.
 */
class Sanitize
{
    // -----------------------------------------------------------------------
    // Helpers básicos
    // -----------------------------------------------------------------------

    public static function onlyDigits(mixed $s): string
    {
        return preg_replace('/\D+/', '', (string)($s ?? ''));
    }

    public static function normalizeSpaces(mixed $s): string
    {
        return preg_replace('/\s+/', ' ', trim((string)($s ?? '')));
    }

    public static function string(mixed $input, int $maxLen = 10000): mixed
    {
        if (!is_string($input)) {
            return $input;
        }
        // Remove tags HTML e entidades potencialmente perigosas
        $sanitized = strip_tags($input);
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return substr(trim($sanitized), 0, $maxLen);
    }

    public static function email(mixed $email): mixed
    {
        if (!is_string($email)) {
            return $email;
        }
        return substr(
            preg_replace('/[^a-z0-9@._+\-]/', '', strtolower(trim($email))),
            0, 255
        );
    }

    public static function phone(mixed $tel): string
    {
        return self::onlyDigits($tel);
    }

    public static function number(mixed $input): ?float
    {
        $num = filter_var($input, FILTER_VALIDATE_FLOAT);
        return $num !== false ? $num : null;
    }

    public static function decimalPositive(mixed $input, bool $allowZero = false): ?float
    {
        $num = self::number($input);
        if ($num === null) {
            return null;
        }
        if ($allowZero ? $num < 0 : $num <= 0) {
            return null;
        }
        return $num;
    }

    public static function integer(mixed $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        $num = filter_var($input, FILTER_VALIDATE_INT);
        return $num !== false ? $num : null;
    }

    public static function boolean(mixed $input): bool
    {
        if (is_bool($input)) {
            return $input;
        }
        if (is_string($input)) {
            return in_array(strtolower($input), ['true', '1'], true);
        }
        return (bool)$input;
    }

    public static function url(mixed $url): string
    {
        if (!is_string($url)) {
            return '';
        }
        $normalized = trim($url);
        if ($normalized === '') {
            return '';
        }
        if (!preg_match('#^(https?://|/)#', $normalized)) {
            return '';
        }
        if (preg_match('#^(javascript|data):#i', $normalized)) {
            return '';
        }
        return substr($normalized, 0, 2000);
    }

    // -----------------------------------------------------------------------
    // Validações
    // -----------------------------------------------------------------------

    public static function validateEmail(mixed $email): array
    {
        $e  = self::email($email);
        $ok = (bool)filter_var($e, FILTER_VALIDATE_EMAIL);
        return ['ok' => $ok, 'value' => $e, 'reason' => $ok ? null : 'Formato de e-mail inválido'];
    }

    public static function validatePhone(mixed $tel): array
    {
        $digits = self::phone($tel);
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return ['ok' => true, 'value' => $digits];
        }
        return ['ok' => false, 'reason' => 'Telefone deve ter 10 ou 11 dígitos (após sanitização)'];
    }

    public const PASSWORD_STANDARD_MESSAGE =
        'A senha deve ter no mínimo 8 caracteres, incluindo letra, número e caractere especial';

    public static function validatePassword(mixed $pw): array
    {
        if (!is_string($pw) || strlen($pw) < 8) {
            return ['ok' => false, 'reason' => self::PASSWORD_STANDARD_MESSAGE];
        }
        if (!preg_match('/[A-Za-z]/', $pw)) {
            return ['ok' => false, 'reason' => self::PASSWORD_STANDARD_MESSAGE];
        }
        if (!preg_match('/[0-9]/', $pw)) {
            return ['ok' => false, 'reason' => self::PASSWORD_STANDARD_MESSAGE];
        }
        if (!preg_match('/[^A-Za-z0-9]/', $pw)) {
            return ['ok' => false, 'reason' => self::PASSWORD_STANDARD_MESSAGE];
        }
        if (self::hasBadSequence($pw, 4)) {
            return ['ok' => false, 'reason' => 'Não pode conter sequências ou repetições de 4+ caracteres'];
        }
        return ['ok' => true];
    }

    public static function hasBadSequence(string $s, int $limit = 4): bool
    {
        if ($s === '') {
            return false;
        }
        $str = strtolower($s);
        $len = strlen($str);

        // Caracteres repetidos
        $run = 1;
        for ($i = 1; $i < $len; $i++) {
            if ($str[$i] === $str[$i - 1]) {
                $run++;
                if ($run >= $limit) {
                    return true;
                }
            } else {
                $run = 1;
            }
        }

        // Caracteres sequenciais
        for ($i = 0; $i <= $len - $limit; $i++) {
            $ok = true;
            for ($j = 1; $j < $limit; $j++) {
                $prev = ord($str[$i + $j - 1]);
                $cur  = ord($str[$i + $j]);
                $isPrevDigit  = ($prev >= 48 && $prev <= 57);
                $isCurDigit   = ($cur  >= 48 && $cur  <= 57);
                $isPrevLetter = ($prev >= 97 && $prev <= 122);
                $isCurLetter  = ($cur  >= 97 && $cur  <= 122);
                if (!(($isPrevDigit && $isCurDigit) || ($isPrevLetter && $isCurLetter)) || $cur !== $prev + 1) {
                    $ok = false;
                    break;
                }
            }
            if ($ok) {
                return true;
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // CPF / CNPJ
    // -----------------------------------------------------------------------

    public static function cpfCnpj(mixed $value): string
    {
        return self::onlyDigits($value);
    }

    public static function validateCpfCnpj(mixed $value): array
    {
        $digits = self::cpfCnpj($value);
        if (strlen($digits) === 11) {
            if (!self::isValidCpf($digits)) {
                return ['type' => 'CPF', 'ok' => false, 'reason' => 'CPF inválido'];
            }
            return ['type' => 'CPF', 'ok' => true, 'value' => $digits];
        }
        if (strlen($digits) === 14) {
            if (!self::isValidCnpj($digits)) {
                return ['type' => 'CNPJ', 'ok' => false, 'reason' => 'CNPJ inválido'];
            }
            return ['type' => 'CNPJ', 'ok' => true, 'value' => $digits];
        }
        return ['type' => null, 'ok' => false, 'reason' => 'Deve ter 11 (CPF) ou 14 (CNPJ) dígitos'];
    }

    private static function allDigitsEqual(string $value): bool
    {
        return (bool)preg_match('/^(\d)\1+$/', $value);
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || self::allDigitsEqual($cpf)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int)$cpf[$i] * (10 - $i);
        }
        $check = ($sum * 10) % 11;
        if ($check === 10) {
            $check = 0;
        }
        if ($check !== (int)$cpf[9]) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int)$cpf[$i] * (11 - $i);
        }
        $check = ($sum * 10) % 11;
        if ($check === 10) {
            $check = 0;
        }
        return $check === (int)$cpf[10];
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (strlen($cnpj) !== 14 || self::allDigitsEqual($cnpj)) {
            return false;
        }
        $w1  = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $w2  = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$cnpj[$i] * $w1[$i];
        }
        $rem = $sum % 11;
        $d1  = $rem < 2 ? 0 : 11 - $rem;
        if ($d1 !== (int)$cnpj[12]) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int)$cnpj[$i] * $w2[$i];
        }
        $rem = $sum % 11;
        $d2  = $rem < 2 ? 0 : 11 - $rem;
        return $d2 === (int)$cnpj[13];
    }

    // -----------------------------------------------------------------------
    // Dimensões de produto
    // -----------------------------------------------------------------------

    public static function validateDimensions(array $data = [], bool $requireAll = false): array
    {
        $weightKg       = self::decimalPositive($data['weightKg'] ?? $data['weight_kg'] ?? null);
        $heightCm       = self::decimalPositive($data['heightCm'] ?? $data['height_cm'] ?? null);
        $widthCm        = self::decimalPositive($data['widthCm']  ?? $data['width_cm']  ?? null);
        $lengthCm       = self::decimalPositive($data['lengthCm'] ?? $data['length_cm'] ?? null);
        $insuranceValue = self::decimalPositive(
            $data['insuranceValue'] ?? $data['insurance_value'] ?? 0,
            true
        );

        if ($requireAll && (!$weightKg || !$heightCm || !$widthCm || !$lengthCm)) {
            return ['ok' => false, 'reason' => 'Peso e dimensões são obrigatórios para publicação'];
        }

        if ($insuranceValue === null) {
            return ['ok' => false, 'reason' => 'insurance_value deve ser maior ou igual a zero'];
        }

        return [
            'ok'    => true,
            'value' => [
                'weightKg'       => $weightKg,
                'heightCm'       => $heightCm,
                'widthCm'        => $widthCm,
                'lengthCm'       => $lengthCm,
                'insuranceValue' => $insuranceValue,
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // URL de imagem
    // -----------------------------------------------------------------------

    public static function validateImageUrl(mixed $url): array
    {
        if (!$url) {
            return ['ok' => true, 'value' => ''];
        }
        $trimmed = trim((string)$url);
        if ($trimmed === '') {
            return ['ok' => true, 'value' => ''];
        }
        if (!str_starts_with($trimmed, 'https://')) {
            return ['ok' => false, 'reason' => 'URL da imagem deve usar HTTPS'];
        }
        if (!filter_var($trimmed, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'reason' => 'URL da imagem inválida'];
        }
        return ['ok' => true, 'value' => substr($trimmed, 0, 2000)];
    }

    // -----------------------------------------------------------------------
    // Payout mode
    // -----------------------------------------------------------------------

    public static function sanitizePayoutMode(mixed $payoutMode): array
    {
        $value   = strtolower(self::string($payoutMode ?? ''));
        $allowed = ['efi_split', 'manual'];
        if ($value === '') {
            return ['ok' => true, 'value' => 'manual'];
        }
        if (!in_array($value, $allowed, true)) {
            return ['ok' => false, 'reason' => 'payout_mode inválido. Use efi_split ou manual'];
        }
        return ['ok' => true, 'value' => $value];
    }
}
