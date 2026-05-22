<?php

namespace WarehouseStock\Middleware;

use WarehouseStock\Helpers\Env;
use WarehouseStock\Helpers\ResponseHelper;

final class AuthMiddleware
{
    public static function requireAuth(string $path): bool
    {
        if ($path === '/api/health' || $path === '/api/auth/login') {
            return true;
        }

        $expectedToken = Env::get('APP_API_TOKEN', 'change_this_secret_token');
        $header = self::authorizationHeader();

        if ($header === null || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            ResponseHelper::error('Missing bearer token', 401);
            return false;
        }

        if (!hash_equals($expectedToken, trim($matches[1]))) {
            ResponseHelper::error('Invalid bearer token', 401);
            return false;
        }

        return true;
    }

    private static function authorizationHeader(): ?string
    {
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            foreach ($headers as $key => $value) {
                if (strtolower($key) === 'authorization') {
                    return $value;
                }
            }
        }

        return null;
    }
}
