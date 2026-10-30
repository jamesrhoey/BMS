<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function home(Request $request): void
    {
        if (AuthService::check()) {
            Response::redirect('/dashboard');
        }
        Response::redirect('/login');
    }

    public function showLogin(Request $request): void
    {
        $loggedOut = $request->query('logged_out') === '1';

        $this->render('auth/login', [
            'pageTitle' => 'Sign In',
            'loggedOut' => $loggedOut,
        ], 'layouts/auth');
    }

    public function login(Request $request): void
    {
        $username = (string) $request->post('username', '');
        $password = (string) ($request->post('password', '') ?? '');

        $validator = new Validator([
            'username' => $username,
            'password' => $password,
        ]);

        $validator
            ->required('username', 'Username')
            ->maxLength('username', 50, 'Username')
            ->required('password', 'Password')
            ->maxLength('password', 255, 'Password');

        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashOldInput(['username' => $username]);
            Response::redirect('/login');
        }

        $result = AuthService::attemptLogin(trim($username), $password, $request);

        if (!$result['success']) {
            Session::flash('error', $result['error'] ?? 'Invalid username or password.');
            Session::flashOldInput(['username' => trim($username)]);
            Response::redirect('/login');
        }

        $intended = Session::get('_intended_url');
        Session::remove('_intended_url');

        Session::flash('success', 'Welcome back, ' . ($result['user']['full_name'] ?? 'User') . '.');

        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//') && $intended !== '/login') {
            Response::redirect($intended);
        }

        Response::redirect('/dashboard');
    }

    public function logout(Request $request): void
    {
        AuthService::logout($request);
        Response::redirect('/login?logged_out=1');
    }
}

