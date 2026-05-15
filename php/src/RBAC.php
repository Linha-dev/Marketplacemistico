<?php
/**
 * RBAC - Controle de acesso baseado em papel (roles).
 * Equivalente direto de backend/rbac.js.
 */
class RBAC
{
    private const VALID_ROLES    = ['user', 'operator', 'admin'];
    private const INTERNAL_ROLES = ['operator', 'admin'];

    /**
     * Determina o role do usuário com base no token JWT e nas variáveis de ambiente.
     */
    public static function resolveUserRole(array $user): string
    {
        // Role explícito no token
        $explicit = self::normalizeRole($user['role'] ?? $user['tipo_role'] ?? $user['user_role'] ?? '');
        if ($explicit !== null) {
            return $explicit;
        }

        $userId    = isset($user['id']) ? (string)$user['id'] : '';
        $userEmail = strtolower((string)($user['email'] ?? ''));

        // Admin via variáveis de ambiente
        $adminIds    = self::parseIds(Config::get('ADMIN_USER_IDS'));
        $adminEmails = self::parseEmails(Config::get('ADMIN_USER_EMAILS'));

        if (($userId !== '' && in_array($userId, $adminIds, true)) ||
            ($userEmail !== '' && in_array($userEmail, $adminEmails, true))) {
            return 'admin';
        }

        // Operator via variáveis de ambiente
        $operatorIds    = self::parseIds(Config::get('OPERATOR_USER_IDS'));
        $operatorEmails = self::parseEmails(Config::get('OPERATOR_USER_EMAILS'));

        if (($userId !== '' && in_array($userId, $operatorIds, true)) ||
            ($userEmail !== '' && in_array($userEmail, $operatorEmails, true))) {
            return 'operator';
        }

        return 'user';
    }

    public static function hasRole(string $userRole, array $allowedRoles): bool
    {
        $normalized = self::normalizeRole($userRole) ?? 'user';
        $allowed    = array_filter(
            array_map([self::class, 'normalizeRole'], $allowedRoles),
            fn($r) => $r !== null
        );
        return in_array($normalized, $allowed, true);
    }

    public static function isInternalRole(string $userRole): bool
    {
        return in_array(self::normalizeRole($userRole) ?? 'user', self::INTERNAL_ROLES, true);
    }

    private static function normalizeRole(mixed $role): ?string
    {
        $value = strtolower((string)($role ?? ''));
        return in_array($value, self::VALID_ROLES, true) ? $value : null;
    }

    private static function parseIds(string $value): array
    {
        return array_filter(
            array_map(fn($s) => preg_replace('/\s+/', '', $s), explode(',', $value)),
            fn($s) => $s !== ''
        );
    }

    private static function parseEmails(string $value): array
    {
        return array_filter(
            array_map(fn($s) => strtolower(trim($s)), explode(',', $value)),
            fn($s) => $s !== ''
        );
    }
}
