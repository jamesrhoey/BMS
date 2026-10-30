<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Department;

final class ProfileController extends Controller
{
    public function show(Request $request): void
    {
        $user = $this->requireUser();

        $department = null;
        if ($user['department_id'] !== null) {
            $department = Department::findById((int) $user['department_id']);
        }

        $this->render('profile/show', [
            'pageTitle' => 'My Profile',
            'activeNav' => 'profile',
            'profileUser' => $user,
            'department' => $department,
        ]);
    }
}

