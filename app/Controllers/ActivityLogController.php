<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\ActivityLog;

final class ActivityLogController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): void
    {
        $this->requireAdmin($request);

        $pageRaw = (string) $request->query('page', '1');
        $page = ctype_digit($pageRaw) ? max(1, (int) $pageRaw) : 1;

        $filters = [
            'q' => (string) $request->query('q', ''),
            'entity_type' => (string) $request->query('entity_type', ''),
        ];

        $pagination = ActivityLog::paginate($filters, $page, 5);

        $this->render('activity_logs/index', [
            'pageTitle' => 'System Activity Logs',
            'activeNav' => 'activity_logs',
            'logs' => $pagination['items'],
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }
}

