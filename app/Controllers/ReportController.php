<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Models\Report;
use App\Models\Setting;
use App\Services\AuthorizationService;
use Throwable;

final class ReportController extends Controller
{
    public function index(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Reports.');
        }

        $this->render('iatms/reports/index', [
            'pageTitle' => 'Reports & Digitized Paper Forms',
            'activeNav' => 'iatms_reports',
            'definitions' => Report::definitions(),
        ]);
    }

    public function show(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to Report.');
        }

        $slug = (string) ($params['slug'] ?? '');
        $filters = [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'keyword' => (string) $request->query('keyword', ''),
        ];

        try {
            $report = Report::run($slug, $filters);
        } catch (Throwable $e) {
            $this->notFound('Report not found.');
        }

        $this->render('iatms/reports/show', [
            'pageTitle' => $report['meta']['title'],
            'activeNav' => 'iatms_reports',
            'report' => $report,
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized CSV export attempt.');
        }

        $slug = (string) ($params['slug'] ?? '');
        $filters = [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'keyword' => (string) $request->query('keyword', ''),
        ];

        try {
            $report = Report::run($slug, $filters);
        } catch (Throwable $e) {
            $this->notFound('Report not found.');
        }

        $filename = 'pagecom_' . $slug . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        $out = fopen('php://output', 'wb');
        if ($out !== false) {
            // UTF-8 BOM for clean Excel opening
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $report['meta']['columns']);
            foreach ($report['rows'] as $row) {
                $safe = array_map(static fn($cell): string => Report::sanitizeCsvCell($cell), $row);
                fputcsv($out, $safe);
            }
            fclose($out);
        }
        exit;
    }

    public function exportExcel(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized Excel export attempt.');
        }

        $slug = (string) ($params['slug'] ?? '');
        $filters = [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'keyword' => (string) $request->query('keyword', ''),
        ];

        try {
            $report = Report::run($slug, $filters);
        } catch (Throwable $e) {
            $this->notFound('Report not found.');
        }

        $filename = 'pagecom_' . $slug . '_' . date('Ymd_His') . '.xls';
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');

        echo '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>';
        echo '<table border="1"><thead><tr>';
        foreach ($report['meta']['columns'] as $col) {
            echo '<th style="background:#1A202C;color:#ffffff;font-weight:bold;">' . htmlspecialchars((string) $col, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($report['rows'] as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                $safe = Report::sanitizeCsvCell($cell);
                // Preserve leading zeros in Excel via mso-number-format
                echo '<td style="mso-number-format:\'\\@\';">' . htmlspecialchars($safe, ENT_QUOTES, 'UTF-8') . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }

    public function printPreview(Request $request, array $params = []): void
    {
        $user = $this->requireUser();
        if (!AuthorizationService::canAccessIatms($user)) {
            $this->denyAccess($request, 'Unauthorized access to print preview.');
        }

        $slug = (string) ($params['slug'] ?? '');
        $filters = [
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
            'keyword' => (string) $request->query('keyword', ''),
        ];

        try {
            $report = Report::run($slug, $filters);
        } catch (Throwable $e) {
            $this->notFound('Report not found.');
        }

        $this->render('iatms/reports/print', [
            'pageTitle' => $report['meta']['title'] . ' — Print Preview',
            'companyName' => (string) Setting::get('company_name', 'PAGECOM INC.'),
            'report' => $report,
            'filters' => $filters,
            'preparedBy' => (string) ($user['full_name'] ?? 'Authorized Officer'),
        ], null);
    }
}
