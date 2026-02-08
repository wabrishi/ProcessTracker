<?php
// Admin Mail Logs Page - Candidate-focused view showing candidates with emails

include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/mail_sender.php';

$message = '';
$messageType = '';

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'Admin';

$candidates = getCandidates();
$templates = getAvailableTemplates();
$users = getUsers();

// Get mail logs
$mailLogs = getMailLogs();

// Group logs by candidate_id
$candidateEmails = [];
foreach ($mailLogs as $log) {
    $cid = $log['candidate_id'] ?? '';
    if ($cid) {
        if (!isset($candidateEmails[$cid])) {
            $candidateEmails[$cid] = [];
        }
        $candidateEmails[$cid][] = $log;
    }
}

// Get filters from request
$filterCandidate = $_GET['candidate'] ?? '';
$filterTemplate = $_GET['template'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Build candidate list (only candidates with emails)
$candidatesWithEmails = [];
foreach ($candidateEmails as $cid => $logs) {
    $candidateData = $candidates[$cid] ?? null;
    $candidateName = $candidateData['name'] ?? $logs[0]['candidate_name'] ?? $cid;
    $candidateEmail = $candidateData['email'] ?? $logs[0]['to_email'] ?? '';
    
    // Apply filters to this candidate's emails
    $filteredLogs = $logs;
    
    if ($filterTemplate) {
        $filteredLogs = array_filter($filteredLogs, fn($log) => ($log['template_name'] ?? '') === $filterTemplate);
    }
    if ($filterStatus) {
        $filteredLogs = array_filter($filteredLogs, fn($log) => ($log['status'] ?? '') === $filterStatus);
    }
    if ($filterDateFrom) {
        $filteredLogs = array_filter($filteredLogs, fn($log) => ($log['sent_at'] ?? '') >= $filterDateFrom . ' 00:00:00');
    }
    if ($filterDateTo) {
        $filteredLogs = array_filter($filteredLogs, fn($log) => ($log['sent_at'] ?? '') <= $filterDateTo . ' 23:59:59');
    }
    if ($searchTerm) {
        $search = strtolower($searchTerm);
        $filteredLogs = array_filter($filteredLogs, fn($log) => 
            stripos($log['candidate_name'] ?? '', $search) !== false ||
            stripos($log['candidate_id'] ?? '', $search) !== false ||
            stripos($log['to_email'] ?? '', $search) !== false ||
            stripos($log['subject'] ?? '', $search) !== false
        );
    }
    
    // Only include if candidate matches name/email search and has filtered logs
    $matchesCandidateSearch = !$searchTerm || 
        stripos($candidateName, $searchTerm) !== false ||
        stripos($cid, $searchTerm) !== false ||
        stripos($candidateEmail, $searchTerm) !== false;
    
    if (!empty($filteredLogs) && $matchesCandidateSearch) {
        $candidatesWithEmails[$cid] = [
            'candidate_id' => $cid,
            'candidate_name' => $candidateName,
            'candidate_email' => $candidateEmail,
            'total_emails' => count($filteredLogs),
            'logs' => array_values($filteredLogs),
            'first_email' => min(array_column($filteredLogs, 'sent_at')),
            'last_email' => max(array_column($filteredLogs, 'sent_at')),
            'sent_count' => count(array_filter($filteredLogs, fn($l) => ($l['status'] ?? '') === 'SENT')),
            'failed_count' => count(array_filter($filteredLogs, fn($l) => ($l['status'] ?? '') === 'FAILED')),
        ];
    }
}

// Sort by last email (most recent first)
usort($candidatesWithEmails, fn($a, $b) => strtotime($b['last_email']) - strtotime($a['last_email']));

// Get unique values for dropdowns (only candidates with emails)
$loggedCandidates = [];
foreach ($mailLogs as $log) {
    $cid = $log['candidate_id'] ?? '';
    if ($cid && !isset($loggedCandidates[$cid])) {
        $loggedCandidates[$cid] = $candidates[$cid]['name'] ?? $cid;
    }
}

// Helper function for HR name
function getHrNameForLogs($hrId, $users) {
    foreach ($users as $user) {
        if ($user['id'] === $hrId) {
            return $user['name'];
        }
    }
    return $hrId;
}

?>
<style>
    .mail-logs-container {
        padding: 0;
    }
    
    .logs-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .logs-header h1 {
        margin: 0;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .logs-actions {
        display: flex;
        gap: 10px;
    }
    
    .filter-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }
    
    .filter-card h3 {
        margin: 0 0 15px 0;
        color: #2c3e50;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .filter-row {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 180px;
    }
    
    .filter-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: 600;
        color: #5a6c7d;
        font-size: 0.85rem;
    }
    
    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 10px 14px;
        border: 1px solid #d1d9e0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s;
        background: #fff;
    }
    
    .filter-group input:focus,
    .filter-group select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .btn {
        padding: 10px 18px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 14px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
    }
    
    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }
    
    .btn-secondary {
        background: #f8f9fa;
        color: #5a6c7d;
        border: 1px solid #e1e5e9;
    }
    
    .btn-secondary:hover {
        background: #e9ecef;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 0.85rem;
    }
    
    .stats-row {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .stat-card {
        background: #fff;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
        min-width: 200px;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    
    .stat-icon.total {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    .stat-icon.sent {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }
    
    .stat-icon.failed {
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
    }
    
    .stat-icon.candidates {
        background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    }
    
    .stat-info h3 {
        margin: 0;
        font-size: 1.8rem;
        color: #2c3e50;
    }
    
    .stat-info p {
        margin: 0;
        color: #7f8c8d;
        font-size: 0.9rem;
    }
    
    .logs-table-card {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        overflow: hidden;
    }
    
    .logs-table-header {
        padding: 15px 20px;
        background: #f8f9fa;
        border-bottom: 1px solid #e1e5e9;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .logs-count {
        font-weight: 600;
        color: #5a6c7d;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .logs-table th {
        text-align: left;
        padding: 14px 16px;
        background: #f8f9fa;
        font-weight: 600;
        color: #5a6c7d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .logs-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #f1f3f5;
        vertical-align: middle;
    }
    
    .logs-table tr:hover {
        background: #f8f9fa;
    }
    
    .logs-table tr:last-child td {
        border-bottom: none;
    }
    
    .candidate-cell {
        display: flex;
        flex-direction: column;
        gap: 2px;
    }
    
    .candidate-name {
        font-weight: 600;
        color: #2c3e50;
    }
    
    .candidate-id {
        font-size: 0.85rem;
        color: #7f8c8d;
    }
    
    .email-count-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        background: #e8f4fd;
        color: #1976d2;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
    }
    
    .template-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.8rem;
        font-weight: 600;
        background: #e8f4fd;
        color: #1976d2;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .status-sent {
        background: #d4edda;
        color: #155724;
    }
    
    .status-failed {
        background: #f8d7da;
        color: #721c24;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #7f8c8d;
    }
    
    .empty-state-icon {
        font-size: 4rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .empty-state h3 {
        margin: 0 0 8px 0;
        color: #5a6c7d;
    }
    
    .empty-state p {
        margin: 0;
        font-size: 0.9rem;
    }
    
    /* Modal Styles */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        padding: 20px;
        overflow-y: auto;
    }
    
    .modal-overlay.open {
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }
    
    .modal-content {
        background: #fff;
        border-radius: 16px;
        max-width: 900px;
        width: 100%;
        margin: 20px auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        overflow: hidden;
    }
    
    .modal-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: #fff;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
    }
    
    .modal-close {
        background: rgba(255,255,255,0.2);
        border: none;
        color: #fff;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.5rem;
        transition: background 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-close:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .modal-body {
        padding: 25px;
        max-height: 70vh;
        overflow-y: auto;
    }
    
    .candidate-summary {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
    }
    
    .summary-item {
        display: flex;
        flex-direction: column;
    }
    
    .summary-label {
        font-size: 0.8rem;
        color: #7f8c8d;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .summary-value {
        font-weight: 600;
        color: #2c3e50;
        font-size: 1.1rem;
    }
    
    .email-list-title {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e1e5e9;
    }
    
    .email-item {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 12px;
        border-left: 4px solid #27ae60;
    }
    
    .email-item.failed {
        border-left-color: #e74c3c;
        background: #fdf2f2;
    }
    
    .email-item-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 10px;
    }
    
    .email-subject {
        font-weight: 600;
        color: #2c3e50;
        flex: 1;
    }
    
    .email-meta {
        display: flex;
        gap: 15px;
        font-size: 0.85rem;
        color: #7f8c8d;
        flex-wrap: wrap;
    }
    
    .email-detail {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .placeholder-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 8px;
    }
    
    .placeholder-tag {
        background: #fff3cd;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-family: monospace;
        color: #856404;
    }
    
    .all-mails-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #667eea;
    }
    
    @media (max-width: 768px) {
        .logs-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .filter-row {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .stats-row {
            grid-template-columns: repeat(2, 1fr);
        }
        
        .candidate-summary {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="mail-logs-container">
    <div class="logs-header">
        <h1>📋 Email Logs</h1>
        <div class="logs-actions">
            <a href="index.php?page=admin&menu=send_mail" class="btn btn-primary">
                ➕ Send Email
            </a>
        </div>

    <!-- Stats Row -->
    <?php
    $totalCandidates = count($candidatesWithEmails);
    $totalEmails = array_sum(array_column($candidatesWithEmails, 'total_emails'));
    $totalSent = array_sum(array_column($candidatesWithEmails, 'sent_count'));
    $totalFailed = array_sum(array_column($candidatesWithEmails, 'failed_count'));
    ?>
    <div class="stats-row">
        <div class="stat-card">
            <div class="stat-icon candidates">👥</div>
            <div class="stat-info">
                <h3><?php echo $totalCandidates; ?></h3>
                <p>Candidates with Emails</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon total">📧</div>
            <div class="stat-info">
                <h3><?php echo $totalEmails; ?></h3>
                <p>Total Emails</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon sent">✓</div>
            <div class="stat-info">
                <h3><?php echo $totalSent; ?></h3>
                <p>Sent Successfully</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon failed">✗</div>
            <div class="stat-info">
                <h3><?php echo $totalFailed; ?></h3>
                <p>Failed</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
        <h3>🔍 Filter Logs</h3>
        <form method="get" class="filter-form">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="menu" value="mail_logs">
            <div class="filter-row">
                <div class="filter-group">
                    <label>🔎 Search</label>
                    <input type="text" name="search" placeholder="Search name, email, candidate ID..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="filter-group">
                    <label>👤 Candidate</label>
                    <select name="candidate">
                        <option value="">All Candidates</option>
                        <?php foreach ($loggedCandidates as $cid => $name): ?>
                            <option value="<?php echo $cid; ?>" <?php echo $filterCandidate === $cid ? 'selected' : ''; ?>>
                                <?php echo $cid . ' - ' . htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📧 Template</label>
                    <select name="template">
                        <option value="">All Templates</option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl; ?>" <?php echo $filterTemplate === $tpl ? 'selected' : ''; ?>>
                                <?php echo getTemplateDisplayName($tpl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📊 Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="SENT" <?php echo $filterStatus === 'SENT' ? 'selected' : ''; ?>>Sent</option>
                        <option value="FAILED" <?php echo $filterStatus === 'FAILED' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>📅 From Date</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="filter-group">
                    <label>📅 To Date</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="index.php?page=admin&menu=mail_logs" class="btn btn-secondary">Clear</a>
                </div>
        </form>
    </div>

    <!-- Candidates Table -->
    <div class="logs-table-card">
        <div class="logs-table-header">
            <span class="logs-count"><?php echo count($candidatesWithEmails); ?> candidate(s) with emails</span>
        </div>
        <div class="table-responsive">
            <?php if (empty($candidatesWithEmails)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Candidates with Emails Found</h3>
                    <p>Try adjusting your filters or send some emails first.</p>
                </div>
            <?php else: ?>
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Email Address</th>
                            <th>Emails Sent</th>
                            <th>First Email</th>
                            <th>Last Email</th>
                            <th>Sent By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidatesWithEmails as $candidate): ?>
                            <?php 
                                $sentBy = [];
                                foreach ($candidate['logs'] as $log) {
                                    $by = $log['sent_by_name'] ?? '-';
                                    if (!in_array($by, $sentBy)) {
                                        $sentBy[] = $by;
                                    }
                                }
                                $sentByStr = implode(', ', $sentBy);
                            ?>
                            <tr data-candidate='<?php echo htmlspecialchars(json_encode($candidate)); ?>'>
                                <td>
                                    <div class="candidate-cell">
                                        <span class="candidate-name"><?php echo htmlspecialchars($candidate['candidate_name']); ?></span>
                                        <span class="candidate-id"><?php echo $candidate['candidate_id']; ?></span>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($candidate['candidate_email']); ?></td>
                                <td>
                                    <span class="email-count-badge">
                                        📧 <?php echo $candidate['total_emails']; ?> 
                                        (<?php echo $candidate['sent_count']; ?> sent, <?php echo $candidate['failed_count']; ?> failed)
                                    </span>
                                </td>
                                <td>
                                    <?php if ($candidate['first_email']): ?>
                                        <?php echo date('d M Y', strtotime($candidate['first_email'])); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo date('h:i A', strtotime($candidate['first_email'])); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($candidate['last_email']): ?>
                                        <?php echo date('d M Y', strtotime($candidate['last_email'])); ?><br>
                                        <small style="color: #7f8c8d;"><?php echo date('h:i A', strtotime($candidate['last_email'])); ?></small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($sentByStr, 0, 25)); ?><?php echo strlen($sentByStr) > 25 ? '...' : ''; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="viewAllEmails(<?php echo htmlspecialchars(json_encode($candidate)); ?>)">
                                        👁️ View All (<?php echo $candidate['total_emails']; ?>)
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
</div>

<!-- All Emails Modal -->
<div class="modal-overlay" id="allEmailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>📧 All Emails for Candidate</h3>
            <button class="modal-close" onclick="closeModal()">×</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Content populated by JavaScript -->
        </div>
    </div>
</div>

<script>
    function viewAllEmails(candidateData) {
        const candidate = typeof candidateData === 'string' ? JSON.parse(candidateData) : candidateData;
        
        // Build candidate summary
        const summaryHtml = `
            <div class="candidate-summary">
                <div class="summary-item">
                    <span class="summary-label">Candidate</span>
                    <span class="summary-value">${candidate.candidate_name}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">ID</span>
                    <span class="summary-value">${candidate.candidate_id}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Email</span>
                    <span class="summary-value">${candidate.candidate_email}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total Emails</span>
                    <span class="summary-value">${candidate.total_emails}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Sent</span>
                    <span class="summary-value" style="color: #27ae60;">${candidate.sent_count}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Failed</span>
                    <span class="summary-value" style="color: #e74c3c;">${candidate.failed_count}</span>
                </div>
            </div>
        `;
        
        // Build email list
        let emailListHtml = '<div class="all-mails-title">📬 All Sent Emails</div>';
        
        // Sort logs by date (newest first)
        const sortedLogs = candidate.logs.sort((a, b) => new Date(b.sent_at) - new Date(a.sent_at));
        
        sortedLogs.forEach((log, index) => {
            const status = log.status || 'UNKNOWN';
            const isFailed = status === 'FAILED';
            
            let placeholdersHtml = '';
            if (log.placeholders && Object.keys(log.placeholders).length > 0) {
                placeholdersHtml = `
                    <div class="placeholder-tags">
                        ${Object.entries(log.placeholders).map(([k, v]) => `<span class="placeholder-tag">${k}: ${v}</span>`).join('')}
                    </div>
                `;
            }
            
            let errorHtml = '';
            if (log.error_message) {
                errorHtml = `
                    <div style="margin-top: 8px; padding: 8px; background: #fdf2f2; border-radius: 6px; color: #721c24; font-size: 0.85rem;">
                        <strong>Error:</strong> ${log.error_message}
                    </div>
                `;
            }
            
            emailListHtml += `
                <div class="email-item ${isFailed ? 'failed' : ''}">
                    <div class="email-item-header">
                        <div class="email-subject">${log.subject || '-'}</div>
                        <span class="status-badge status-${status.toLowerCase()}">${status}</span>
                    </div>
                    <div class="email-meta">
                        <div class="email-detail">
                            📅 ${log.sent_at ? new Date(log.sent_at).toLocaleString() : '-'}
                        </div>
                        <div class="email-detail">
                            📧 ${log.template_name || '-'}
                        </div>
                        <div class="email-detail">
                            👤 ${log.sent_by_name || '-'}
                        </div>
                    </div>
                    ${placeholdersHtml}
                    ${errorHtml}
                </div>
            `;
        });
        
        document.getElementById('modalBody').innerHTML = summaryHtml + emailListHtml;
        document.getElementById('allEmailsModal').classList.add('open');
    }

    function closeModal() {
        document.getElementById('allEmailsModal').classList.remove('open');
    }

    // Close modal when clicking outside
    document.getElementById('allEmailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
</script>

