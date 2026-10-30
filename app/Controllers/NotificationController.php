<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Notification;

final class NotificationController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $notifications = Notification::forUser((int) $user['id'], 40);

        $this->render('notifications/index', [
            'pageTitle' => 'Notifications',
            'activeNav' => 'notifications',
            'notifications' => $notifications,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function markRead(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        $id = isset($params['id']) && ctype_digit($params['id']) ? (int) $params['id'] : 0;

        $notification = $id > 0 ? Notification::markAsRead($id, (int) $user['id']) : null;
        if ($notification === null) {
            $this->notFound('Notification not found.');
        }

        $redirectTo = (string) $request->post('redirect_to', '');
        if ($redirectTo !== '' && str_starts_with($redirectTo, '/') && !str_starts_with($redirectTo, '//')) {
            Response::redirect($redirectTo);
        }

        if (!empty($notification['action_url']) && str_starts_with((string) $notification['action_url'], '/')) {
            Response::redirect((string) $notification['action_url']);
        }

        Response::redirect('/notifications');
    }

    /**
     * @param array<string, string> $params
     */
    public function markAllRead(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        Notification::markAllAsRead((int) $user['id']);

        Session::flash('success', 'All notifications marked as read.');

        $redirectTo = (string) $request->post('redirect_to', '');
        if ($redirectTo !== '' && str_starts_with($redirectTo, '/') && !str_starts_with($redirectTo, '//')) {
            Response::redirect($redirectTo);
        }

        Response::redirect('/notifications');
    }
}

