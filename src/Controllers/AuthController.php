<?php

namespace WarehouseStock\Controllers;

use WarehouseStock\Helpers\Env;
use WarehouseStock\Helpers\ResponseHelper;

final class AuthController extends BaseController
{
    public function login(): void
    {
        $data = $this->input();
        $username = $this->stringOrNull($data, 'username');
        $password = (string) ($data['password'] ?? '');

        $expectedUsername = Env::get('APP_ADMIN_USERNAME', 'admin');
        $passwordHash = Env::get('APP_ADMIN_PASSWORD_HASH', '');

        if ($username === null || $password === '') {
            ResponseHelper::error('username and password are required', 422);
            return;
        }

        if ($passwordHash === '') {
            ResponseHelper::error('APP_ADMIN_PASSWORD_HASH is not configured', 500);
            return;
        }

        if (!hash_equals($expectedUsername, $username) || !password_verify($password, $passwordHash)) {
            $this->logActivity('LOGIN_FAILED', 'auth', null, null, ['username' => $username], $username);
            ResponseHelper::error('Invalid username or password', 401);
            return;
        }

        $token = Env::get('APP_API_TOKEN', 'change_this_secret_token');
        $this->logActivity('LOGIN', 'auth', null, null, ['username' => $username], $username);

        ResponseHelper::success([
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful');
    }
}
