<?php
include_once __DIR__ . '/../includes/candidate.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_candidate'])) {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'position' => $_POST['position']
        ];
        $result = createCandidate($data);
        if ($result) {
            // Mark candidate as created by this HR user
            $candidates = getCandidates();
            $candidates[$result['id']]['created_by'] = $currentUserId;
            saveCandidates($candidates);
            $message = $result['message'] . ': ' . $result['id'];
        } else {
            $message = 'Failed to create candidate: Resume upload failed. Please check file type (PDF/DOC/DOCX), size (max 5MB), and try again.';
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
}

$candidates = getCandidates();

// Filter candidates: show only those assigned to current user or created by current user
$filteredCandidates = [];
foreach ($candidates as $id => $cand) {
    $assignedTo = $cand['assigned_to'] ?? null;
    $createdBy = $cand['created_by'] ?? null;
    if ($assignedTo === $currentUserId || $createdBy === $currentUserId) {
        $filteredCandidates[$id] = $cand;
    }
}
$candidates = $filteredCandidates;
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="container">
        <h1>HR Dashboard</h1>
        <nav style="margin-bottom: 20px;">
            <a href="dashboard.php" class="action-btn" style="background:#3498db;">Dashboard</a>
            <a href="profile.php" class="action-btn" style="background:#17a2b8;">My Profile</a>
            <a href="../index.php?page=logout" class="action-btn" style="background:#e74c3c;">Logout</a>
        </nav>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <h2>Create Candidate</h2>
        <form method="post" enctype="multipart/form-data">
            <label>Name: <input name="name" required></label>
            <label>Email: <input type="email" name="email" required></label>
            <label>Phone: <input name="phone" required></label>
            <label>Position: <input name="position" required></label>
            <label>Location: <input name="location" placeholder="e.g., Delhi, Mumbai"></label>
            <label>Resume: <input type="file" name="resume"></label>
            <button name="create_candidate">Create</button>
        </form>

        <h2>Candidates</h2>
        
        <!-- Search and Filter Controls -->
        <div class="search-filter-container" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px;">
            <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                <div style="flex: 1; min-width: 200px;">
                    <label>Search: <input type="text" id="searchInput" placeholder="Search by name, email, position..." style="width: 100%;"></label>
                </div>
                <div>
                    <label>Status: 
                        <select id="statusFilter">
                            <option value="">All Status</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="COMPLETED">Completed</option>
                            <option value="CANCELLED">Cancelled</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label>Step: 
                        <select id="stepFilter">
                            <option value="">All Steps</option>
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i . ' - ' . STEPS[$i]; ?></option>
                            <?php endfor; ?>
                        </select>
                    </label>
                </div>
                <div>
                    <label>Sort by: 
                        <select id="sortBy">
                            <option value="name">Name</option>
                            <option value="current_step">Step</option>
                            <option value="status">Status</option>
                            <option value="assigned_date">Assigned Date</option>
                            <option value="created">Created (ID)</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label>Order: 
                        <select id="sortOrder">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                    </label>
                </div>
                <div>
                    <label>From Date: <input type="date" id="dateFrom" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px;"></label>
                </div>
                <div>
                    <label>To Date: <input type="date" id="dateTo" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px;"></label>
                </div>
                <div>
                    <button type="button" id="clearDateFilter" style="padding: 6px 12px; background: #6c757d; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Clear Dates</button>
                </div>
            </div>
        </div>

        <!-- Candidates Table -->
        <div class="candidates-table-container">
            <table class="candidates-table" id="candidatesTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Position</th>
                        <th>Location</th>
                        <th>Current Step</th>
                        <th>Status</th>
                        <th>Last Call</th>
                        <th>Call Remarks</th>
                        <th>Assigned Date</th>
                        <th>Resume</th>
                        <th>Documents</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="candidatesTableBody">
                    <?php foreach ($candidates as $id => $cand): ?>
                        <?php 
                            $assignedDate = $cand['assigned_date'] ?? '';
                            $location = $cand['location'] ?? '-';
                        ?>
                        <tr data-id="<?php echo $id; ?>" data-name="<?php echo strtolower($cand['name']); ?>" data-email="<?php echo strtolower($cand['email']); ?>" data-position="<?php echo strtolower($cand['position']); ?>" data-status="<?php echo $cand['status']; ?>" data-step="<?php echo $cand['current_step']; ?>" data-assigned-date="<?php echo $assignedDate; ?>">
                            <td data-label="ID"><?php echo $id; ?></td>
                            <td data-label="Name"><?php echo $cand['name']; ?></td>
                            <td data-label="Email"><?php echo $cand['email']; ?></td>
                            <td data-label="Phone"><?php echo $cand['phone']; ?></td>
                            <td data-label="Position"><?php echo $cand['position']; ?></td>
                            <td data-label="Location"><?php echo $location; ?></td>
                            <td data-label="Current Step"><?php echo $cand['current_step'] . ' - ' . STEPS[$cand['current_step']]; ?></td>
                            <td data-label="Status">
                                <span class="status-badge status-<?php echo strtolower($cand['status']); ?>"><?php echo $cand['status']; ?></span>
                                <?php if (!empty($cand['last_call'])): ?>
                                    <?php 
                                    $lastResult = $cand['call_result'] ?? '';
                                    $isInterested = $lastResult === 'interested';
                                    $statusClass = $isInterested ? 'call-interested' : 'call-not-interested';
                                    $statusIcon = $isInterested ? '✓' : '✗';
                                    $statusText = $isInterested ? 'Interested' : 'Not Interested';
                                    ?>
                                    <div class="call-status-badge <?php echo $statusClass; ?>" style="font-size: 0.7em; margin-top: 4px; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                                        <?php echo $statusIcon . ' ' . $statusText; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Last Call">
                                <?php if (!empty($cand['last_call'])): ?>
                                    <?php echo date('d M Y, h:i A', strtotime($cand['last_call'])); ?>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Call Remarks">
                                <?php 
                                $remarks = '';
                                if (!empty($cand['call_logs'][0]['remarks'])) {
                                    $remarks = $cand['call_logs'][0]['remarks'];
                                } elseif (!empty($cand['call_remarks'])) {
                                    $remarks = $cand['call_remarks'];
                                }
                                ?>
                                <?php if (!empty($remarks)): ?>
                                    <span title="<?php echo htmlspecialchars($remarks); ?>">
                                        <?php echo strlen($remarks) > 30 ? htmlspecialchars(substr($remarks, 0, 30)) . '...' : htmlspecialchars($remarks); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Assigned Date"><?php echo !empty($assignedDate) ? $assignedDate : '-'; ?></td>
                            <td data-label="Resume">
                                <?php if (!empty($cand['resume'])): ?>
                                    <a href="../uploads/resumes/<?php echo $cand['resume']; ?>" target="_blank" class="resume-link">View</a>
                                <?php else: ?>
                                    <span class="no-resume">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Documents">
                                <?php 
                                $docCount = !empty($cand['documents']) ? count($cand['documents']) : 0;
                                echo $docCount > 0 ? $docCount . ' file' . ($docCount > 1 ? 's' : '') : '-';
                                ?>
                            </td>
                            <td data-label="Actions">
                                <a href="../index.php?page=candidate_details&id=<?php echo $id; ?>" class="action-btn">View Details</a>
                                <button type="button" class="action-btn call-now-btn" data-id="<?php echo $id; ?>" data-phone="<?php echo htmlspecialchars($cand['phone']); ?>" style="margin-left:8px; background:#ffc107;">Call Now</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <!-- Call Modal -->
    <div id="callModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
        <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:480px; box-shadow:0 6px 30px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0;">Log Call</h3>
            <form id="callForm">
                <input type="hidden" name="id" id="callCandidateId">
                <div style="margin-bottom:10px;">
                    <label style="font-weight:bold; display:block; margin-bottom:6px;">Remarks (optional)</label>
                    <textarea name="remarks" id="callRemarks" rows="4" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;"></textarea>
                </div>
                <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                    <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="result" value="interested" checked> Interested</label>
                    <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="result" value="not_interested"> Not Interested</label>
                </div>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" id="callCancel" style="background:#6c757d; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Cancel</button>
                    <button type="submit" style="background:#28a745; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Show loader on form submission (except call form)
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    // Don't show loading overlay for call form (it has its own handling)
                    if (form.id === 'callForm') {
                        return;
                    }
                    document.getElementById('loadingOverlay').style.display = 'flex';
                });
            });

            // Search and Filter functionality
            const searchInput = document.getElementById('searchInput');
            const statusFilter = document.getElementById('statusFilter');
            const stepFilter = document.getElementById('stepFilter');
            const sortBy = document.getElementById('sortBy');
            const sortOrder = document.getElementById('sortOrder');
            const dateFrom = document.getElementById('dateFrom');
            const dateTo = document.getElementById('dateTo');
            const clearDateFilter = document.getElementById('clearDateFilter');
            const candidatesTableBody = document.getElementById('candidatesTableBody');
            const candidateRows = Array.from(document.querySelectorAll('#candidatesTableBody tr'));

            function filterAndSortCandidates() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                const stepValue = stepFilter.value;
                const sortByValue = sortBy.value;
                const sortOrderValue = sortOrder.value;
                const dateFromValue = dateFrom.value;
                const dateToValue = dateTo.value;

                // Filter candidates
                const filteredCandidates = candidateRows.filter(row => {
                    const name = row.dataset.name;
                    const email = row.dataset.email;
                    const position = row.dataset.position;
                    const status = row.dataset.status;
                    const step = row.dataset.step;
                    const assignedDate = row.dataset.assignedDate || '';

                    const matchesSearch = searchTerm === '' || 
                        name.includes(searchTerm) || 
                        email.includes(searchTerm) || 
                        position.includes(searchTerm);

                    const matchesStatus = statusValue === '' || status === statusValue;
                    const matchesStep = stepValue === '' || step === stepValue;

                    // Date range filtering
                    let matchesDate = true;
                    if (assignedDate !== '') {
                        if (dateFromValue && assignedDate < dateFromValue) {
                            matchesDate = false;
                        }
                        if (dateToValue && assignedDate > dateToValue) {
                            matchesDate = false;
                        }
                    } else {
                        // If no assigned date and date filter is set, hide the row
                        matchesDate = dateFromValue === '' && dateToValue === '';
                    }

                    return matchesSearch && matchesStatus && matchesStep && matchesDate;
                });

                // Sort candidates
                filteredCandidates.sort((a, b) => {
                    let aValue, bValue;

                    switch (sortByValue) {
                        case 'name':
                            aValue = a.dataset.name;
                            bValue = b.dataset.name;
                            break;
                        case 'current_step':
                            aValue = parseInt(a.dataset.step);
                            bValue = parseInt(b.dataset.step);
                            break;
                        case 'status':
                            aValue = a.dataset.status;
                            bValue = b.dataset.status;
                            break;
                        case 'assigned_date':
                            aValue = a.dataset.assignedDate || '';
                            bValue = b.dataset.assignedDate || '';
                            break;
                        case 'created':
                            aValue = a.dataset.id;
                            bValue = b.dataset.id;
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
            searchInput.addEventListener('input', filterAndSortCandidates);
            statusFilter.addEventListener('change', filterAndSortCandidates);
            stepFilter.addEventListener('change', filterAndSortCandidates);
            sortBy.addEventListener('change', filterAndSortCandidates);
            sortOrder.addEventListener('change', filterAndSortCandidates);
            
            // Date filter event listeners
            dateFrom.addEventListener('input', filterAndSortCandidates);
            dateTo.addEventListener('input', filterAndSortCandidates);
            
            // Clear date filter button
            clearDateFilter.addEventListener('click', function() {
                dateFrom.value = '';
                dateTo.value = '';
                filterAndSortCandidates();
            });

            // Initial sort
            filterAndSortCandidates();

            // Call Now modal handler for HR
            const modal = document.getElementById('callModal');
            const callForm = document.getElementById('callForm');
            const callIdInput = document.getElementById('callCandidateId');
            const callRemarks = document.getElementById('callRemarks');
            const callCancel = document.getElementById('callCancel');

            // Function to hide modal
            function hideCallModal() {
                modal.style.display = 'none';
            }

            // Function to show modal
            function showCallModal() {
                modal.style.display = 'flex';
            }

            // Attach click handlers to all Call Now buttons
            const callButtons = document.querySelectorAll('.call-now-btn');
            
            callButtons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const id = this.dataset.id;
                    const phone = this.dataset.phone;
                    
                    callIdInput.value = id;
                    callRemarks.value = '';
                    
                    // Open phone dialer
                    if (phone) {
                        window.location.href = 'tel:' + phone;
                    }
                    
                    // Show modal immediately (removing the delay to ensure it shows)
                    showCallModal();
                    
                    e.preventDefault();
                    return false;
                });
            });

            // Cancel button handler
            callCancel.addEventListener('click', function(e) {
                hideCallModal();
                e.preventDefault();
            });

            // Close modal when clicking outside the modal content
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    hideCallModal();
                }
            });

            // Prevent modal content clicks from closing modal
            const modalContent = modal.querySelector('div[style*="background:#fff"]');
            if (modalContent) {
                modalContent.addEventListener('click', function(e) {
                    e.stopPropagation();
                });
            }

            // Form submission handler
            callForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(callForm);
                const candidateId = formData.get('id');
                const result = formData.get('result');
                const remarks = formData.get('remarks');
                
                // Validate form data
                if (!candidateId || !result) {
                    alert('Error: Missing required fields');
                    return false;
                }
                
                // Show loading overlay
                document.getElementById('loadingOverlay').style.display = 'flex';
                
                // Disable submit button to prevent double submission
                const submitBtn = callForm.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Saving...';
                }
                
                fetch('../hr/update_call.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server error: ' + response.status);
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading overlay
                    document.getElementById('loadingOverlay').style.display = 'none';
                    
                    if (data.success) {
                        // Close modal
                        hideCallModal();
                        
                        // Update the row with actual call details from JSON
                        const row = document.querySelector('#candidatesTableBody tr[data-id="' + candidateId + '"]');
                        if (row) {
                            const statusCell = row.querySelector('td[data-label="Status"]');
                            if (statusCell) {
                                // Remove existing call note if any
                                const existingNote = statusCell.querySelector('.call-note');
                                if (existingNote) {
                                    existingNote.remove();
                                }
                                
                                // Create call details info
                                const callInfo = document.createElement('div');
                                callInfo.className = 'call-note';
                                callInfo.style.fontSize = '0.75em';
                                callInfo.style.marginTop = '4px';
                                callInfo.style.padding = '3px 6px';
                                callInfo.style.borderRadius = '4px';
                                callInfo.style.backgroundColor = result === 'interested' ? '#d4edda' : '#f8d7da';
                                callInfo.style.color = result === 'interested' ? '#155724' : '#721c24';
                                
                                // Format the call details
                                const resultLabel = result === 'interested' ? '✓ Interested' : '✗ Not Interested';
                                const timestamp = data.data.last_call ? new Date(data.data.last_call).toLocaleString() : new Date().toLocaleString();
                                const remarksText = data.data.remarks ? '<br><em>' + data.data.remarks + '</em>' : '';
                                
                                callInfo.innerHTML = '<strong>' + resultLabel + '</strong><br>' + timestamp + remarksText;
                                statusCell.appendChild(callInfo);
                            }
                        }
                        
                        // Show brief success feedback on button
                        if (submitBtn) {
                            submitBtn.textContent = 'Saved!';
                            setTimeout(() => {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Save';
                            }, 1500);
                        }
                    } else {
                        // Show error message
                        alert('Error: ' + (data.message || 'Failed to save call log'));
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Save';
                        }
                    }
                })
                .catch(err => {
                    // Hide loading overlay
                    document.getElementById('loadingOverlay').style.display = 'none';
                    
                    console.error('Error saving call:', err);
                    alert('Request failed: ' + err.message + '. Please check the console for details.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Save';
                    }
                });
                
                return false;
            });
        });
    </script>
</body>
</html>