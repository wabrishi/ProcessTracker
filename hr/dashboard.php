
<?php
include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/candidate.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'HR User';

// Get active menu item from URL parameter
$activeMenu = $_GET['menu'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_candidate'])) {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'position' => $_POST['position'],
            'location' => $_POST['location'] ?? ''
        ];
        $result = createCandidate($data);
        if (isset($result['id'])) {
            $candidates = getCandidates();
            $candidates[$result['id']]['created_by'] = $currentUserId;
            saveCandidates($candidates);
            $message = 'Candidate created successfully: ' . $result['id'];
            $activeMenu = 'candidates';
        } else {
            $message = 'Failed to create candidate: ' . ($result['message'] ?? 'Resume upload failed or email already exists');
        }
    } elseif (isset($_POST['move_step'])) {
        $id = $_POST['candidate_id'];
        $step = (int)$_POST['step'];
        $data = $_POST;
        if (moveToStep($id, $step, $data)) {
            $message = 'Moved to step ' . $step;
        } else {
            $message = 'Cannot move to step ' . $step;
        }
    }
    if (isset($_POST['log_call'])) {
        $result = $_POST['result'] ?? null;
        $remarks = $_POST['remarks'] ?? '';
        $candidates = getCandidates();
        $hr = $_SESSION['user_id'] ?? 'system';
        
        if (!$result) {
            $message = 'Please select call result';
        } else {

            $callLogEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'result' => $result,
                'remarks' => $remarks,
                'called_by' => $hr
            ];
            
            if (!isset($candidates[$candidateId]['call_logs'])) {
                $candidates[$candidateId]['call_logs'] = [];
            }
            
            array_unshift($candidates[$candidateId]['call_logs'], $callLogEntry);
            $candidates[$candidateId]['last_call'] = $callLogEntry['timestamp'];
            $candidates[$candidateId]['call_result'] = $result;
            
            if (saveCandidates($candidates)) {
                $message = 'Call logged successfully';
                logRecruitmentAction($candidateId, 'Call: ' . $result, $hr);
                $candidate = getCandidate($candidateId);
            } else {
                $message = 'Failed to save call log';
            }
        }
    }
}

$candidates = getCandidates();

// Filter candidates for current HR user
$filteredCandidates = [];
foreach ($candidates as $id => $cand) {
    $assignedTo = $cand['assigned_to'] ?? null;
    $createdBy = $cand['created_by'] ?? null;
    if ($assignedTo === $currentUserId || $createdBy === $currentUserId) {
        $filteredCandidates[$id] = $cand;
    }
}
$candidates = $filteredCandidates;

// Stats
$totalCandidates = count($candidates);
$activeCandidates = count(array_filter($candidates, fn($c) => $c['status'] === 'IN_PROGRESS'));
$completedCandidates = count(array_filter($candidates, fn($c) => $c['status'] === 'COMPLETED'));
$cancelledCandidates = count(array_filter($candidates, fn($c) => $c['status'] === 'CANCELLED'));

// Step distribution for chart
$stepDistribution = [];
for ($i = 1; $i <= 7; $i++) {
    $stepDistribution[$i] = 0;
}
foreach ($candidates as $cand) {
    $step = $cand['current_step'] ?? 1;
    if (isset($stepDistribution[$step])) {
        $stepDistribution[$step]++;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard - ProcessTracker</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>ProcessTracker</h2>
                <div class="user-info">Welcome, <?php echo htmlspecialchars($currentUserName); ?></div>
            </div>
            
            <ul class="menu">
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=dashboard" class="menu-link <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=create" class="menu-link <?php echo $activeMenu === 'create' ? 'active' : ''; ?>">
                        <span class="icon">➕</span>
                        Create Candidate
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=candidates" class="menu-link <?php echo $activeMenu === 'candidates' ? 'active' : ''; ?>">
                        <span class="icon">👥</span>
                        My Candidates
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=send_mail" class="menu-link <?php echo $activeMenu === 'send_mail' ? 'active' : ''; ?>">
                        <span class="icon">📧</span>
                        Send Mail
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=profile" class="menu-link <?php echo $activeMenu === 'profile' ? 'active' : ''; ?>">
                        <span class="icon">👤</span>
                        My Profile
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="../index.php?page=logout" class="menu-link">
                        <span class="icon">🚪</span>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Mobile Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

            <!-- Dashboard View -->
            <?php if ($activeMenu === 'dashboard'): ?>
                <div class="page-header">
                    <h1>📊 Dashboard Overview</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalCandidates; ?></div>
                        <div class="stat-label">Total Candidates</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-value"><?php echo $activeCandidates; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-value"><?php echo $completedCandidates; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-value"><?php echo $cancelledCandidates; ?></div>
                        <div class="stat-label">Cancelled</div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-row">
                    <div class="chart-container">
                        <h3>📈 Candidates by Step</h3>
                        <div class="step-chart">
                            <?php 
                            $stepNames = [1 => 'Profile Selection', 2 => 'Confirmation', 3 => 'Document Verification', 4 => 'First Interview', 5 => 'HR Interview', 6 => 'Final Interview', 7 => 'Selection'];
                            $maxStep = max($stepDistribution) ?: 1;
                            foreach ($stepDistribution as $step => $count):
                                $percentage = ($count / $maxStep) * 100;
                            ?>
                                <div class="step-bar-row">
                                    <div class="step-label">Step <?php echo $step; ?>: <?php echo $stepNames[$step] ?? ''; ?></div>
                                    <div class="step-bar-container">
                                        <div class="step-bar" style="width: <?php echo $percentage; ?>%;">
                                            <?php echo $count; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="chart-container">
                        <h3>📊 Status Distribution</h3>
                        <div class="status-chart">
                            <div class="status-legend">
                                <div class="legend-item"><span class="legend-color in-progress"></span> In Progress: <?php echo $activeCandidates; ?></div>
                                <div class="legend-item"><span class="legend-color completed"></span> Completed: <?php echo $completedCandidates; ?></div>
                                <div class="legend-item"><span class="legend-color cancelled"></span> Cancelled: <?php echo $cancelledCandidates; ?></div>
                            </div>
                            <div class="total-display">
                                <span class="total-number"><?php echo $totalCandidates; ?></span>
                                <span class="total-label">Total</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <h2>Quick Actions</h2>
                    <div class="action-buttons">
                        <a href="index.php?page=hr&menu=create" class="btn btn-primary">➕ Add New Candidate</a>
                        <a href="index.php?page=hr&menu=candidates" class="btn btn-secondary">👥 View My Candidates</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Create Candidate View -->
            <?php if ($activeMenu === 'create'): ?>
                <div class="page-header">
                    <h1>➕ Create New Candidate</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name" required placeholder="Enter candidate's full name">
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" required placeholder="Enter email address">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Phone Number *</label>
                                <input type="tel" name="phone" required placeholder="Enter phone number">
                            </div>
                            <div class="form-group">
                                <label>Position *</label>
                                <input type="text" name="position" required placeholder="Enter position applied for">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location" placeholder="e.g., Delhi, Mumbai">
                            </div>
                            <div class="form-group">
                                <label>Resume (Optional)</label>
                                <input type="file" name="resume" accept=".pdf,.doc,.docx">
                            </div>
                        </div>
                        <button type="submit" name="create_candidate" class="btn btn-primary">➕ Create Candidate</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- My Candidates View -->
            <?php if ($activeMenu === 'candidates'): ?>
                <div class="page-header">
                    <h1>👥 My Candidates</h1>
                    <div class="header-actions">
                        <a href="index.php?page=hr&menu=create" class="btn btn-primary">➕ Add New</a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <!-- Search and Filter Controls -->
                <div class="search-filter-bar">
                    <div class="search-group">
                        <input type="text" id="searchInput" placeholder="Search by ID, Name, Email, Phone..." class="search-input">
                    </div>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="COMPLETED">Completed</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>
                    <select id="stepFilter" class="filter-select">
                        <option value="">All Steps</option>
                        <?php for ($i = 1; $i <= 7; $i++): ?>
                            <option value="<?php echo $i; ?>"><?php echo $i . ' - ' . STEPS[$i]; ?></option>
                        <?php endfor; ?>
                    </select>
                    <input type="date" id="dateFrom" class="filter-select" placeholder="From Date">
                    <input type="date" id="dateTo" class="filter-select" placeholder="To Date">
                    <select id="sortBy" class="filter-select">
                        <option value="name">Sort by Name</option>
                        <option value="current_step">Sort by Step</option>
                        <option value="status">Sort by Status</option>
                        <option value="assigned_date">Sort by Date</option>
                    </select>
                    <select id="sortOrder" class="filter-select">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                    <button type="button" id="clearFilters" class="btn btn-secondary btn-sm">Clear</button>
                </div>

                <!-- Candidates Table - Mobile Friendly -->
                <div class="table-container">
                    <table class="data-table" id="candidatesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Position</th>
                                <th>Step</th>
                                <th>Status</th>
                                <th>Assigned Date</th>
                                <th>Last Call</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="candidatesTableBody">
                            <?php foreach ($candidates as $id => $cand): ?>
                                <?php 
                                    $assignedDate = $cand['assigned_date'] ?? '';
                                    $lastCall = $cand['last_call'] ?? '';
                                ?>
                                <tr data-id="<?php echo $id; ?>" 
                                    data-name="<?php echo strtolower($cand['name']); ?>" 
                                    data-email="<?php echo strtolower($cand['email']); ?>" 
                                    data-phone="<?php echo strtolower($cand['phone']); ?>" 
                                    data-position="<?php echo strtolower($cand['position']); ?>" 
                                    data-status="<?php echo $cand['status']; ?>" 
                                    data-step="<?php echo $cand['current_step']; ?>" 
                                    data-assigned-date="<?php echo $assignedDate; ?>">
                                    <td data-label="ID"><?php echo $id; ?></td>
                                    <td data-label="Name"><?php echo htmlspecialchars($cand['name']); ?></td>
                                    <td data-label="Email"><?php echo htmlspecialchars($cand['email']); ?></td>
                                    <td data-label="Phone"><?php echo $cand['phone']; ?></td>
                                    <td data-label="Position"><?php echo htmlspecialchars($cand['position']); ?></td>
                                    <td data-label="Step"><?php echo $cand['current_step'] . ' - ' . STEPS[$cand['current_step']]; ?></td>
                                    <td data-label="Status">
                                        <span class="status-badge status-<?php echo strtolower($cand['status']); ?>">
                                            <?php echo $cand['status']; ?>
                                        </span>
                                        <?php if (!empty($lastCall)): ?>
                                            <?php 
                                            $lastResult = $cand['call_result'] ?? '';
                                            $isInterested = $lastResult === 'interested';
                                            $isNotPick = $lastResult === 'not_pick';
                                            ?>
                                            <span class="call-indicator <?php echo $isInterested ? 'interested' : ($isNotPick ? 'not-pick' : 'not-interested'); ?>">
                                                <?php echo $isInterested ? '✓' : ($isNotPick ? '📵' : '✗'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Assigned Date">
                                        <?php if (!empty($assignedDate)): ?>
                                            <?php echo date('d M Y', strtotime($assignedDate)); ?>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Last Call">
                                        <?php if (!empty($lastCall)): ?>
                                            <?php echo date('d M, h:i A', strtotime($lastCall)); ?>
                                        <?php else: ?>
                                            <span class="no-data">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="actions-cell">
                                        <a href="index.php?page=candidate_details&id=<?php echo $id; ?>" class="btn btn-sm btn-secondary">View</a>
                                        <button type="button" class="btn btn-sm btn-secondary call-now-btn" data-id="<?php echo $id; ?>" data-phone="<?php echo htmlspecialchars($cand['phone']); ?>">Call</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <!-- Send Mail View -->
            <?php if ($activeMenu === 'send_mail'): ?>
                <?php 
                // Include the send mail page
                ob_start();
                include __DIR__ . '/send_mail.php';
                $sendMailContent = ob_get_clean();
                echo $sendMailContent;
                ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Call Modal -->
    <div class="modal-overlay" id="callModal">
        <div class="modal call-modal">
            <h3>📞 Log Call Result</h3>
            <form id="callForm">
                <input type="hidden" name="id" id="callCandidateId">
                <div class="form-group">
                    <label>Call Result *</label>
                    <div class="call-result-options">
                        <label class="call-result-option interested">
                            <input type="radio" name="result" value="interested">
                            <div class="option-content">
                                <span class="option-icon">✓</span>
                                <span class="option-label">Interested</span>
                            </div>
                        </label>
                        <label class="call-result-option not-interested">
                            <input type="radio" name="result" value="not_interested">
                            <div class="option-content">
                                <span class="option-icon">✗</span>
                                <span class="option-label">Not Interested</span>
                            </div>
                        </label>
                        <label class="call-result-option not-pick">
                            <input type="radio" name="result" value="not_pick">
                            <div class="option-content">
                                <span class="option-icon">📵</span>
                                <span class="option-label">Not Pick</span>
                            </div>
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" id="callRemarks" rows="3" placeholder="Enter call notes..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeCallModal()">Cancel</button>
                    <button type="submit" name="log_call" class="btn btn-success">💾 Save</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        // Notification function
        function showNotification(message, type = 'info') {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = 'notification notification-' + type;
            notification.innerHTML = '<span>' + message + '</span><button onclick="this.parentElement.remove()">×</button>';
            
            // Style the notification
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 15px 20px; border-radius: 8px; color: #fff; font-weight: 500; z-index: 10000; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); animation: slideIn 0.3s ease;';
            
            if (type === 'success') {
                notification.style.background = '#27ae60';
            } else if (type === 'error') {
                notification.style.background = '#e74c3c';
            } else {
                notification.style.background = '#3498db';
            }
            
            // Add animation keyframes
            if (!document.getElementById('notificationStyles')) {
                const style = document.createElement('style');
                style.id = 'notificationStyles';
                style.textContent = '@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } } @keyframes slideOut { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }';
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        // Call modal functions
        function openCallModal(candidateId) {
            document.getElementById('callCandidateId').value = candidateId;
            document.getElementById('callModal').classList.add('open');
        }

        function closeCallModal() {
            document.getElementById('callModal').classList.remove('open');
        }

        // Search and filter functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const stepFilter = document.getElementById('stepFilter');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const sortBy = document.getElementById('sortBy');
            const sortOrder = document.getElementById('sortOrder');
            const clearFilters = document.getElementById('clearFilters');
            const candidatesTableBody = document.getElementById('candidatesTableBody');
            
            if (candidatesTableBody) {
                const candidateRows = Array.from(document.querySelectorAll('#candidatesTableBody tr'));

                function filterAndSortCandidates() {
                    const searchTerm = searchInput.value.toLowerCase().trim();
                    const statusValue = statusFilter.value;
                    const stepValue = stepFilter.value;
                    const fromDate = dateFrom.value;
                    const toDate = dateTo.value;
                    const sortByValue = sortBy.value;
                    const sortOrderValue = sortOrder.value;

                    // Filter candidates
                    const filteredCandidates = candidateRows.filter(row => {
                        const id = row.dataset.id || '';
                        const name = row.dataset.name || '';
                        const email = row.dataset.email || '';
                        const phone = row.dataset.phone || '';
                        const position = row.dataset.position || '';
                        const rowStatus = row.dataset.status || '';
                        const rowStep = row.dataset.step || '';
                        const assignedDate = row.dataset.assignedDate || '';

                        // Search by ID, Name, Email, or Phone
                        const matchesSearch = searchTerm === '' || 
                            id.toLowerCase().includes(searchTerm) ||
                            name.includes(searchTerm) || 
                            email.includes(searchTerm) || 
                            phone.includes(searchTerm) ||
                            position.includes(searchTerm);

                        const matchesStatus = statusValue === '' || rowStatus === statusValue;
                        const matchesStep = stepValue === '' || rowStep === stepValue;

                        // Date range filtering
                        let matchesDate = true;
                        if (fromDate && assignedDate < fromDate) {
                            matchesDate = false;
                        }
                        if (toDate && assignedDate > toDate) {
                            matchesDate = false;
                        }
                        // If no date filter is set, show rows with or without assigned dates
                        if (!fromDate && !toDate) {
                            matchesDate = true;
                        }

                        return matchesSearch && matchesStatus && matchesStep && matchesDate;
                    });

                    // Sort candidates
                    filteredCandidates.sort((a, b) => {
                        let aValue, bValue;

                        switch (sortByValue) {
                            case 'name':
                                aValue = a.dataset.name || '';
                                bValue = b.dataset.name || '';
                                break;
                            case 'current_step':
                                aValue = parseInt(a.dataset.step) || 0;
                                bValue = parseInt(b.dataset.step) || 0;
                                break;
                            case 'status':
                                aValue = a.dataset.status || '';
                                bValue = b.dataset.status || '';
                                break;
                            case 'assigned_date':
                                aValue = a.dataset.assignedDate || '';
                                bValue = b.dataset.assignedDate || '';
                                break;
                            default:
                                return 0;
                        }

                        if (sortOrderValue === 'asc') {
                            return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
                        } else {
                            return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
                        }
                    });

                    // Hide all rows first
                    candidateRows.forEach(row => row.style.display = 'none');

                    // Show filtered and sorted rows
                    filteredCandidates.forEach(row => row.style.display = '');

                    // Reorder in DOM
                    filteredCandidates.forEach(row => {
                        candidatesTableBody.appendChild(row);
                    });
                }

                // Add event listeners
                if (searchInput) searchInput.addEventListener('input', filterAndSortCandidates);
                if (statusFilter) statusFilter.addEventListener('change', filterAndSortCandidates);
                if (stepFilter) stepFilter.addEventListener('change', filterAndSortCandidates);
                if (dateFrom) dateFrom.addEventListener('change', filterAndSortCandidates);
                if (dateTo) dateTo.addEventListener('change', filterAndSortCandidates);
                if (sortBy) sortBy.addEventListener('change', filterAndSortCandidates);
                if (sortOrder) sortOrder.addEventListener('change', filterAndSortCandidates);

                if (clearFilters) {
                    clearFilters.addEventListener('click', function() {
                        searchInput.value = '';
                        statusFilter.value = '';
                        stepFilter.value = '';
                        dateFrom.value = '';
                        dateTo.value = '';
                        sortBy.value = 'name';
                        sortOrder.value = 'asc';
                        filterAndSortCandidates();
                    });
                }

                // Initial sort
                filterAndSortCandidates();

                // Call Now buttons
                document.querySelectorAll('.call-now-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const phone = this.dataset.phone;
                        
                        if (phone) {
                            window.location.href = 'tel:' + phone;
                        }
                        
                        openCallModal(id);
                    });
                });
            }

            // Call form submission
            const callForm = document.getElementById('callForm');
            if (callForm) {
                callForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(callForm);
                    formData.append("log_call", "1"); // IMPORTANT
                    const candidateId = formData.get('id');
                    const result = formData.get('result');
                    const remarks = formData.get('remarks');
                    
                    // Validate form
                    if (!candidateId || !result) {
                        showNotification('Please select a call result', 'error');
                        return;
                    }
                    
                    document.getElementById('loadingOverlay').style.display = 'flex';
                    
                    fetch('hr/update_call.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Server returned error: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        
                        if (data.success) {
                            // Clear form
                            callForm.reset();
                            closeCallModal();
                            
                            // Show success message
                            showNotification('Call logged successfully!', 'success');
                            
                            // Reload page after short delay to update UI
                            setTimeout(() => {
                                window.location.reload();
                            }, 1000);
                        } else {
                            // Show detailed error message
                            const errorMsg = data.message || 'Failed to save call log';
                            const debugInfo = data.debug ? '\n\nDebug: ' + JSON.stringify(data.debug, null, 2) : '';
                            console.error('Save error:', data);
                            
                            if (data.debug && data.debug.file_writable === false) {
                                showNotification('Permission Error: Database file is not writable', 'error');
                            } else {
                                showNotification('Error: ' + errorMsg, 'error');
                            }
                        }
                    })
                    .catch(err => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        console.error('Error:', err);
                        showNotification('Request failed: ' + err.message, 'error');
                    });
                });
            }

            // Close modal when clicking outside
            document.getElementById('callModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCallModal();
                }
            });

            // Show loader on form submission
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function() {
                    if (form.id !== 'callForm') {
                        document.getElementById('loadingOverlay').style.display = 'flex';
                    }
                });
            });
        });
    </script>
    
    <style>
        /* Mobile-friendly table styles */
        .step-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .step-bar-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .step-label {
            width: 140px;
            font-size: 0.85em;
            color: #666;
            flex-shrink: 0;
        }
        
        .step-bar-container {
            flex: 1;
            min-width: 100px;
            background: #e9ecef;
            border-radius: 6px;
            height: 26px;
            overflow: hidden;
        }
        
        .step-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 8px;
            color: #fff;
            font-weight: 600;
            font-size: 0.8em;
            border-radius: 6px;
            transition: width 0.5s ease;
        }
        
        .status-chart {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 30px;
            padding: 10px 0;
            flex-wrap: wrap;
        }
        
        .status-legend {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
        }
        
        .legend-color {
            width: 14px;
            height: 14px;
            border-radius: 3px;
        }
        
        .legend-color.in-progress { background: #f39c12; }
        .legend-color.completed { background: #27ae60; }
        .legend-color.cancelled { background: #e74c3c; }
        
        .total-display {
            text-align: center;
        }
        
        .total-number {
            display: block;
            font-size: 2em;
            font-weight: 700;
            color: #2c3e50;
        }
        
        .total-label {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .search-filter-bar {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }
        
        .search-input {
            flex: 1;
            min-width: 180px;
            padding: 10px 15px;
            border: 1px solid #d1d9e0;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .filter-select {
            padding: 10px 15px;
            border: 1px solid #d1d9e0;
            border-radius: 6px;
            font-size: 14px;
            min-width: 120px;
            background: #fff;
        }
        
        .call-indicator {
            display: inline-block;
            font-size: 0.7em;
            margin-left: 4px;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .call-indicator.interested {
            background: #d4edda;
            color: #155724;
        }
        
        .call-indicator.not-interested {
            background: #f8d7da;
            color: #721c24;
        }
        
        .call-indicator.not-pick {
            background: #fef9e7;
            color: #9a7b0a;
        }
        
        .no-data {
            color: #999;
        }
        
        .actions-cell {
            white-space: nowrap;
        }
        
        .actions-cell .btn {
            margin: 2px;
        }
        
        /* Mobile table styles */
        @media (max-width: 992px) {
            .data-table thead {
                display: none;
            }
            
            .data-table, .data-table tbody, .data-table tr, .data-table td {
                display: block;
                width: 100%;
            }
            
            .data-table tr {
                margin-bottom: 15px;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
                background: #fff;
            }
            
            .data-table td {
                padding: 10px 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 45%;
            }
            
            .data-table td:last-child {
                border-bottom: none;
            }
            
            .data-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                font-weight: 600;
                color: #7f8c8d;
                font-size: 0.85em;
            }
            
            .data-table td[data-label="Actions"] {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                background: #f8f9fa;
                padding: 12px 15px;
            }
            
            .data-table td[data-label="Actions"]::before {
                display: none;
            }
            
            .actions-cell {
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
                min-width: 100%;
            }
            
            .filter-select {
                width: 100%;
                min-width: 100%;
            }
            
            .step-label {
                width: 100%;
                margin-bottom: 5px;
            }
            
            .step-bar-container {
                width: 100%;
            }
            
            .status-chart {
                flex-direction: column;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .page-header h1 {
                font-size: 1.3em;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions .btn {
                width: 100%;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 12px;
            }
            
            .stat-card {
                padding: 15px;
            }
            
            .stat-card .stat-value {
                font-size: 1.8em;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
            }
        }
        
        /* Call Modal Styles */
        .call-modal {
            max-width: 450px;
        }
        
        .call-result-options {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 8px;
        }
        
        .call-result-option {
            display: block;
            padding: 14px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: #fff;
        }
        
        .call-result-option:hover {
            border-color: #3498db;
            background: #f8f9fa;
        }
        
        .call-result-option input[type="radio"] {
            display: none;
        }
        
        .call-result-option input[type="radio"]:checked + .option-content {
            color: inherit;
        }
        
        .call-result-option.interested {
            border-color: #27ae60;
            background: #f0fff4;
        }
        
        .call-result-option.interested:hover {
            border-color: #219a52;
            background: #e6ffed;
        }
        
        .call-result-option.interested input[type="radio"]:checked + .option-content {
            color: #1e7e34;
        }
        
        .call-result-option.not-interested {
            border-color: #e74c3c;
            background: #fff5f5;
        }
        
        .call-result-option.not-interested:hover {
            border-color: #c0392b;
            background: #ffe6e6;
        }
        
        .call-result-option.not-interested input[type="radio"]:checked + .option-content {
            color: #c0392b;
        }
        
        .call-result-option.not-pick {
            border-color: #f39c12;
            background: #fffbf0;
        }
        
        .call-result-option.not-pick:hover {
            border-color: #d68910;
            background: #fff3cd;
        }
        
        .call-result-option.not-pick input[type="radio"]:checked + .option-content {
            color: #d68910;
        }
        
        .call-result-option input[type="radio"]:checked + .option-content .option-icon {
            transform: scale(1.1);
        }
        
        .option-content {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #5a6c7d;
        }
        
        .option-icon {
            font-size: 1.4em;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.7);
            border-radius: 8px;
            transition: transform 0.2s ease;
        }
        
        .option-label {
            font-weight: 600;
            font-size: 1.05em;
        }
        
        /* Modal overlay fix */
        .modal-overlay {
            backdrop-filter: blur(3px);
        }
    </style>
</body>
</html>


