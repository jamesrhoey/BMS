<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        return Response::url($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $assetPath): string
    {
        return Response::asset($assetPath);
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): string
    {
        return e(Session::old($key, $default));
    }
}

if (!function_exists('old_raw')) {
    function old_raw(string $key, mixed $default = null): mixed
    {
        return Session::old($key, $default);
    }
}

if (!function_exists('has_error')) {
    function has_error(string $field): bool
    {
        $errors = Session::errors();
        return isset($errors[$field]);
    }
}

if (!function_exists('field_error')) {
    function field_error(string $field): string
    {
        $errors = Session::errors();
        return isset($errors[$field]) ? e($errors[$field]) : '';
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return '—';
        }
        $ts = strtotime($datetime);
        if ($ts === false) {
            return e($datetime);
        }
        return date('M d, Y h:i A', $ts);
    }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return number_format($bytes / 1048576, 2) . ' MB';
    }
}

if (!function_exists('project_status_badge_class')) {
    function project_status_badge_class(string $status): string
    {
        return match ($status) {
            'Draft' => 'badge-soft-secondary',
            'Preparing Documents' => 'badge-soft-warning',
            'Submitted for Document Verification', 'Submitted' => 'badge-soft-primary',
            'Under Review' => 'badge-soft-warning',
            'Resubmitted for Verification' => 'badge-soft-info',
            'Returned for Corrections' => 'badge-soft-danger',
            'Document Verification Completed' => 'badge-soft-success',
            'JOM Number Issued' => 'badge-soft-success',
            default => 'badge-soft-secondary',
        };
    }
}

if (!function_exists('checklist_status_badge_class')) {
    function checklist_status_badge_class(string $status): string
    {
        return match ($status) {
            'Uploaded' => 'badge-soft-success',
            'Replaced' => 'badge-soft-info',
            'Missing' => 'badge-soft-danger',
            default => 'badge-soft-secondary',
        };
    }
}

if (!function_exists('bms_icon')) {
    function bms_icon(string $name, int $size = 16): string
    {
        $inner = match ($name) {
            'eye' => '<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"></path><circle cx="12" cy="12" r="3"></circle>',
            'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>',
            'prepare' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line>',
            'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line>',
            'replace' => '<polyline points="23 4 23 10 17 10"></polyline><polyline points="1 20 1 14 7 14"></polyline><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>',
            'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line>',
            'history' => '<polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path><polyline points="12 7 12 12 15 15"></polyline>',
            'send' => '<line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>',
            'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
            'review' => '<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
            'deactivate' => '<circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>',
            'activate' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline>',
            'search' => '<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>',
            'reset' => '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>',
            'plus' => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
            'back' => '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
            'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>',
            default => '<circle cx="12" cy="12" r="9"></circle>',
        };

        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
    }
}

if (!function_exists('render_table_footer')) {
    function render_table_footer(
        int $currentPage,
        int $perPage,
        int $rowCountOnPage,
        int $totalRows,
        ?callable $buildPageUrl = null
    ): string {
        $safePage = max(1, $currentPage);
        $safePerPage = max(1, $perPage);
        $totalPages = max(1, (int) ceil($totalRows / $safePerPage));

        if ($totalRows <= 0 || $rowCountOnPage <= 0) {
            $rangeLabel = '0 - 0 of 0';
        } else {
            $start = (($safePage - 1) * $safePerPage) + 1;
            $end = min($totalRows, $start + $rowCountOnPage - 1);
            $rangeLabel = $start . ' - ' . $end . ' of ' . $totalRows;
        }

        $canPrev = $buildPageUrl !== null && $safePage > 1;
        $canNext = $buildPageUrl !== null && $safePage < $totalPages;

        $firstBtn = $canPrev
            ? '<a href="' . e((string) $buildPageUrl(1)) . '" class="bms-pager-btn" title="First page" aria-label="First page">&laquo;</a>'
            : '<span class="bms-pager-btn is-disabled" aria-hidden="true">&laquo;</span>';

        $prevBtn = $canPrev
            ? '<a href="' . e((string) $buildPageUrl($safePage - 1)) . '" class="bms-pager-btn" title="Previous page" aria-label="Previous page">&lsaquo;</a>'
            : '<span class="bms-pager-btn is-disabled" aria-hidden="true">&lsaquo;</span>';

        $nextBtn = $canNext
            ? '<a href="' . e((string) $buildPageUrl($safePage + 1)) . '" class="bms-pager-btn" title="Next page" aria-label="Next page">&rsaquo;</a>'
            : '<span class="bms-pager-btn is-disabled" aria-hidden="true">&rsaquo;</span>';

        $lastBtn = $canNext
            ? '<a href="' . e((string) $buildPageUrl($totalPages)) . '" class="bms-pager-btn" title="Last page" aria-label="Last page">&raquo;</a>'
            : '<span class="bms-pager-btn is-disabled" aria-hidden="true">&raquo;</span>';

        return '<div class="bms-table-footer">'
            . '<div class="bms-table-count">' . $rangeLabel . ' &nbsp;&nbsp; total items: ' . $totalRows . '</div>'
            . '<div class="bms-table-pager" role="navigation" aria-label="Table pagination">'
            . $firstBtn
            . $prevBtn
            . '<span class="bms-pager-box">' . $safePage . '</span>'
            . '<span class="bms-pager-total">/ ' . $totalPages . '</span>'
            . $nextBtn
            . $lastBtn
            . '</div>'
            . '</div>';
    }
}


