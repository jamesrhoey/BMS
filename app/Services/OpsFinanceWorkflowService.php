<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectWorkflowHistory;
use PDO;
use RuntimeException;

final class OpsFinanceWorkflowService
{
    /** @return array{items: list<array<string,mixed>>, total:int, page:int, per_page:int, total_pages:int} */
    public static function paginate(array $filters, int $page = 1, int $perPage = 15, bool $history = false): array
    {
        $where = ["(p.submitted_to_opfin_at IS NOT NULL OR p.status IN ('Submitted to OPS Finance', 'Received by OPS Finance', 'Under OPS Finance Processing', 'Awaiting PR', 'Awaiting Canvassing Sheets', 'OPS Finance Documents Complete', 'Submitted to Admin Finance', 'PR Submitted to OPS Finance'))"];
        $params = [];
        if (!$history) {
            $where[] = 'p.opfin_submitted_to_afin_at IS NULL';
        }
        $category = strtolower(trim((string) ($filters['category'] ?? '')));
        if ($category === 'mrc') $where[] = "(p.project_category = 'MRC' OR p.workflow_type IN ('mrc','standard'))";
        if (in_array($category, ['special_project','special project'], true)) $where[] = "(p.project_category = 'Special Project' OR p.workflow_type = 'special_project')";
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') { $where[] = 'p.status = :status'; $params['status'] = $status; }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(p.project_code LIKE :q OR p.project_name LIKE :q2 OR p.client_name LIKE :q3)';
            $params['q'] = "%{$q}%"; $params['q2'] = "%{$q}%"; $params['q3'] = "%{$q}%";
        }
        if (($filters['date_from'] ?? '') !== '') { $where[] = 'DATE(p.opfin_received_at) >= :date_from'; $params['date_from'] = $filters['date_from']; }
        if (($filters['date_to'] ?? '') !== '') { $where[] = 'DATE(p.opfin_received_at) <= :date_to'; $params['date_to'] = $filters['date_to']; }
        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $pdo = Database::connection();
        $count = $pdo->prepare("SELECT COUNT(*) FROM projects p {$whereSql}"); $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, min($page, max(1, (int) ceil($total / $perPage))));
        $sort = (string) ($filters['sort'] ?? 'received');
        $order = $sort === 'oldest' ? 'ASC' : ($sort === 'name' ? 'ASC' : 'DESC');
        $orderSql = $sort === 'name' ? 'p.project_name ASC, p.id DESC' : "p.opfin_received_at {$order}, p.id DESC";
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT p.*, d.department_name AS current_department_name,
                       ru.full_name AS opfin_received_by_name,
                       su.full_name AS submitted_to_opfin_by_name,
                       au.full_name AS opfin_submitted_to_afin_by_name
                FROM projects p
                LEFT JOIN departments d ON d.id=p.current_department_id
                LEFT JOIN users ru ON ru.id=p.opfin_received_by
                LEFT JOIN users su ON su.id=p.submitted_to_opfin_by
                LEFT JOIN users au ON au.id=p.opfin_submitted_to_afin_by
                {$whereSql} ORDER BY {$orderSql} LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $pdo->prepare($sql); $stmt->execute($params);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) self::decorate($item);
        return ['items' => $items, 'total' => $total, 'page' => $page, 'per_page' => $perPage, 'total_pages' => max(1, (int) ceil($total / $perPage))];
    }

    /** @return array<string,int> */
    public static function stats(): array
    {
        $pdo = Database::connection();
        $row = $pdo->query("SELECT
            COUNT(*) total_received,
            COALESCE(SUM(project_category='MRC' OR workflow_type IN ('mrc','standard')),0) mrc_projects,
            COALESCE(SUM(project_category='Special Project' OR workflow_type='special_project'),0) special_projects,
            COALESCE(SUM(opfin_submitted_to_afin_at IS NULL),0) pending_processing,
            COALESCE(SUM(opfin_submitted_to_afin_at IS NULL AND NOT EXISTS (SELECT 1 FROM project_documents d WHERE d.project_id=projects.id AND d.document_type='PR' AND d.is_current=1)),0) awaiting_pr,
            COALESCE(SUM(opfin_submitted_to_afin_at IS NULL AND NOT EXISTS (SELECT 1 FROM project_documents d WHERE d.project_id=projects.id AND d.document_type='CANVASSING_SHEETS' AND d.is_current=1)),0) awaiting_canvassing,
            COALESCE(SUM(opfin_submitted_to_afin_at IS NULL AND EXISTS (SELECT 1 FROM project_documents d WHERE d.project_id=projects.id AND d.document_type='PR' AND d.is_current=1) AND EXISTS (SELECT 1 FROM project_documents d WHERE d.project_id=projects.id AND d.document_type='CANVASSING_SHEETS' AND d.is_current=1)),0) complete,
            COALESCE(SUM(opfin_submitted_to_afin_at IS NOT NULL),0) submitted
            FROM projects
            WHERE submitted_to_opfin_at IS NOT NULL
               OR status IN ('Submitted to OPS Finance', 'Received by OPS Finance', 'Under OPS Finance Processing', 'Awaiting PR', 'Awaiting Canvassing Sheets', 'OPS Finance Documents Complete', 'Submitted to Admin Finance', 'PR Submitted to OPS Finance')")->fetch() ?: [];
        return array_map('intval', ['total_received'=>$row['total_received']??0,'mrc_projects'=>$row['mrc_projects']??0,'special_projects'=>$row['special_projects']??0,'pending_processing'=>$row['pending_processing']??0,'awaiting_pr'=>$row['awaiting_pr']??0,'awaiting_canvassing'=>$row['awaiting_canvassing']??0,'complete'=>$row['complete']??0,'submitted'=>$row['submitted']??0]);
    }

    /** @return array<string,mixed> */
    public static function details(int $projectId): array
    {
        $project = Project::findById($projectId);
        if ($project === null || !Project::isSubmittedToOpsFinance($project)) throw new RuntimeException('Project has not been submitted by OPS to OPS Finance.');
        $docs = ProjectDocument::forProject($projectId);
        $project['documents'] = $docs;
        $project['history'] = ProjectWorkflowHistory::forProject($projectId);
        $project['pr'] = ProjectDocument::findCurrentByType($projectId, 'PR');
        $project['canvassing'] = array_values(array_filter($docs, static fn(array $d): bool => $d['document_type'] === 'CANVASSING_SHEETS' && (int)$d['is_current'] === 1));
        return $project;
    }

    /**
     * Expand PHP's nested multi-file upload structure into individual files.
     *
     * @param array<string, mixed>|null $files
     * @return list<array<string, mixed>>
     */
    public static function expandUploadedFiles(?array $files): array
    {
        if ($files === null || !array_key_exists('name', $files)) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $expanded = [];
        foreach (array_keys($files['name']) as $index) {
            $file = [];
            foreach ($files as $key => $value) {
                $file[$key] = is_array($value) ? ($value[$index] ?? null) : $value;
            }
            $expanded[] = $file;
        }

        return $expanded;
    }

    public static function upload(array $project, string $type, ?array $file, array $user, Request $request): array
    {
        if (!in_array($type, ['PR','CANVASSING_SHEETS'], true)) return ['success'=>false,'error'=>'Invalid OPS Finance document type.'];
        $result = DocumentStorageService::storeWorkflowDocument($project, $file, $type, $user, $request, 'OPS Finance upload');
        if ($result['success']) {
            $pdo = Database::connection();
            $pr = ProjectDocument::findCurrentByType((int)$project['id'], 'PR');
            $sheets = array_filter(ProjectDocument::forProject((int)$project['id']), static fn(array $d): bool => $d['document_type'] === 'CANVASSING_SHEETS' && (int)$d['is_current'] === 1);
            $status = ($pr !== null && $sheets !== []) ? Project::STATUS_OPS_FINANCE_DOCUMENTS_COMPLETE
                : ($pr === null ? Project::STATUS_AWAITING_PR : Project::STATUS_AWAITING_CANVASSING_SHEETS);
            $stmt = $pdo->prepare('UPDATE projects SET status=:status, updated_at=NOW() WHERE id=:id AND opfin_submitted_to_afin_at IS NULL');
            $stmt->execute(['status'=>$status,'id'=>(int)$project['id']]);
            ActivityLogger::log('ops_finance.document_uploaded','project',(int)$project['id'],"Uploaded {$type} for project {$project['project_code']}.",$request,(int)$user['id'],false);
        }
        return $result;
    }

    public static function submit(array $project, array $user, Request $request): array
    {
        $id = (int)$project['id']; $pdo = Database::connection();
        $pr = ProjectDocument::findCurrentByType($id, 'PR');
        $sheets = array_values(array_filter(ProjectDocument::forProject($id), static fn(array $d): bool => $d['document_type']==='CANVASSING_SHEETS' && (int)$d['is_current']===1));
        if ($pr === null || $sheets === []) return ['success'=>false,'error'=>'Submission is blocked. Upload both PR and at least one Canvassing Sheet.'];
        $afin = Department::findByCode(Department::CODE_ADMIN_FINANCE);
        if ($afin === null) return ['success'=>false,'error'=>'Admin Finance department configuration is missing.'];
        try {
            Database::transaction(function(PDO $db) use ($id,$project,$user,$request,$pr,$sheets,$afin): void {
                $handoff = ['pr_document_id'=>(int)$pr['id'],'canvassing_sheet_document_ids'=>array_map(static fn(array $d): int => (int)$d['id'],$sheets)];
                $stmt = $db->prepare("UPDATE projects SET status=:status,current_department_id=:dept,opfin_submitted_to_afin_at=NOW(),opfin_submitted_to_afin_by=:user,opfin_afin_handoff_documents=:docs,updated_at=NOW() WHERE id=:id AND submitted_to_opfin_at IS NOT NULL AND opfin_submitted_to_afin_at IS NULL");
                $stmt->execute(['status'=>Project::STATUS_SUBMITTED_TO_ADMIN_FINANCE,'dept'=>(int)$afin['id'],'user'=>(int)$user['id'],'docs'=>json_encode($handoff),'id'=>$id]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Project was already submitted to Admin Finance.');
                ProjectWorkflowHistory::record(['project_id'=>$id,'from_status'=>$project['status'],'to_status'=>Project::STATUS_SUBMITTED_TO_ADMIN_FINANCE,'action'=>'submitted_to_admin_finance_by_ops_finance','from_department_id'=>$project['current_department_id'],'to_department_id'=>$afin['id'],'acted_by'=>$user['id'],'notes'=>'OPS Finance handoff includes PR and Canvassing Sheets.','flagged_documents'=>$handoff]);
                ActivityLogger::log('ops_finance.submitted_to_admin_finance','project',$id,"Submitted project {$project['project_code']} to Admin Finance.",$request,(int)$user['id'],false);
            });
        } catch (\Throwable $e) { return ['success'=>false,'error'=>$e->getMessage()]; }
        return ['success'=>true];
    }

    /** @param array<string,mixed> $item */
    private static function decorate(array &$item): void
    {
        $item['category_label'] = (strcasecmp((string)($item['project_category']??''),'Special Project')===0 || ($item['workflow_type']??'')==='special_project') ? 'Special Project' : 'MRC';
        $item['pr'] = ProjectDocument::findCurrentByType((int)$item['id'],'PR');
        $item['canvassing_count'] = count(array_filter(ProjectDocument::forProject((int)$item['id']), static fn(array $d): bool => $d['document_type']==='CANVASSING_SHEETS' && (int)$d['is_current']===1));
    }
}
