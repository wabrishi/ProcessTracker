<?php
/**
 * Sequence Manager - Admin Interface for Managing ID Sequences
 * 
 * Allows administrators to create, edit, delete, and manage ID sequences.
 */

include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/sequence.php';

$message = '';
$currentUserRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($currentUserRole !== 'ADMIN') {
    header('Location: index.php?page=hr&menu=dashboard');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new sequence
    if (isset($_POST['add_sequence'])) {
        $name = $_POST['name'] ?? '';
        $prefix = $_POST['prefix'] ?? '';
        $padding = (int)($_POST['padding'] ?? 4);
        $startValue = (int)($_POST['start_value'] ?? 1);
        $increment = (int)($_POST['increment'] ?? 1);
        $description = $_POST['description'] ?? '';
        $active = isset($_POST['active']);
        
        if (empty($name) || empty($prefix)) {
            $message = 'Name and Prefix are required';
        } else {
            // Check if setting as default
            $makeDefault = isset($_POST['make_default']);
            
            if (createSequence([
                'name' => $name,
                'prefix' => $prefix,
                'padding' => $padding,
                'start_value' => $startValue,
                'increment' => $increment,
                'description' => $description,
                'active' => $active
            ])) {
                $seqId = strtolower(str_replace([' ', '-'], '_', $name)) . '_sequence';
                
                if ($makeDefault) {
                    setDefaultSequence($seqId);
                }
                
                $message = 'Sequence "' . htmlspecialchars($name) . '" created successfully';
            } else {
                $message = 'Failed to create sequence. Name may already exist.';
            }
        }
    }
    
    // Update sequence
    if (isset($_POST['update_sequence'])) {
        $seqId = $_POST['sequence_id'] ?? '';
        $data = [
            'name' => $_POST['name'] ?? '',
            'prefix' => $_POST['prefix'] ?? '',
            'padding' => (int)($_POST['padding'] ?? 4),
            'increment' => (int)($_POST['increment'] ?? 1),
            'description' => $_POST['description'] ?? '',
            'active' => isset($_POST['active']),
            'current_value' => (int)($_POST['current_value'] ?? 1)
        ];
        
        if (empty($data['name']) || empty($data['prefix'])) {
            $message = 'Name and Prefix are required';
        } else {
            if (updateSequence($seqId, $data)) {
                // Handle default setting
                if (isset($_POST['make_default'])) {
                    setDefaultSequence($seqId);
                } else {
                    // If making another sequence default, clear this one
                    $config = getSequencesConfig();
                    if (($config['settings']['default_sequence'] ?? '') === $seqId) {
                        $config['settings']['default_sequence'] = '';
                        saveSequencesConfig($config);
                    }
                }
                $message = 'Sequence updated successfully';
            } else {
                $message = 'Failed to update sequence';
            }
        }
    }
    
    // Delete sequence
    if (isset($_POST['delete_sequence'])) {
        $seqId = $_POST['sequence_id'] ?? '';
        $seq = getSequence($seqId);
        
        if ($seq && deleteSequence($seqId)) {
            $message = 'Sequence "' . htmlspecialchars($seq['name']) . '" deleted successfully';
        } else {
            $message = 'Failed to delete sequence';
        }
    }
    
    // Toggle sequence status
    if (isset($_POST['toggle_sequence'])) {
        $seqId = $_POST['sequence_id'] ?? '';
        $active = isset($_POST['active']);
        
        if (toggleSequenceStatus($seqId, $active)) {
            $message = 'Sequence status updated';
        } else {
            $message = 'Failed to update status';
        }
    }
    
    // Reset sequence
    if (isset($_POST['reset_sequence'])) {
        $seqId = $_POST['sequence_id'] ?? '';
        $newValue = (int)($_POST['reset_value'] ?? 1);
        
        if (resetSequence($seqId, $newValue)) {
            $message = 'Sequence reset to value ' . $newValue;
        } else {
            $message = 'Failed to reset sequence';
        }
    }
    
    // Set default sequence
    if (isset($_POST['set_default'])) {
        $seqId = $_POST['sequence_id'] ?? '';
        
        if (setDefaultSequence($seqId)) {
            $message = 'Default sequence updated';
        } else {
            $message = 'Failed to set default sequence';
        }
    }
    
    // Import sequences
    if (isset($_POST['import_sequences'])) {
        $json = $_POST['import_json'] ?? '';
        
        if (importSequences($json)) {
            $message = 'Sequences imported successfully';
        } else {
            $message = 'Invalid JSON or import failed';
        }
    }
}

// Get all sequences and stats
$sequences = getAllSequences();
$stats = getSequenceStats();
$config = getSequencesConfig();
$defaultSeqId = $config['settings']['default_sequence'] ?? null;

$backUrl = 'index.php?page=admin&menu=dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sequence Manager - ProcessTracker</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>ProcessTracker</h2>
                <div class="user-info">Admin Panel</div>
            </div>
            
            <ul class="menu">
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=dashboard" class="menu-link">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=workflow" class="menu-link">
                        <span class="icon">🔄</span>
                        Workflow Manager
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="index.php?page=admin&menu=sequences" class="menu-link active">
                        <span class="icon">🔢</span>
                        Sequence Manager
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=smtp" class="menu-link">
                        <span class="icon">⚙️</span>
                        SMTP Config
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

            <div class="page-header">
                <h1>🔢 Sequence Manager</h1>
                <div class="header-actions">
                    <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_sequences']; ?></div>
                    <div class="stat-label">Total Sequences</div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-value"><?php echo $stats['active_sequences']; ?></div>
                    <div class="stat-label">Active Sequences</div>
                </div>
                <div class="stat-card orange">
                    <div class="stat-value"><?php echo count($sequences); ?></div>
                    <div class="stat-label">Configured</div>
                </div>
                <div class="stat-card green">
                    <div class="stat-value">
                        <?php 
                        $nextSeq = getActiveCandidateSequence();
                        echo $nextSeq ? htmlspecialchars($nextSeq['prefix'] . str_pad($nextSeq['current_value'], $nextSeq['padding'], '0', STR_PAD_LEFT)) : 'N/A';
                        ?>
                    </div>
                    <div class="stat-label">Next Candidate ID</div>
                </div>
            </div>

            <!-- Add New Sequence -->
            <div class="content-card">
                <h2>➕ Create New Sequence</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Sequence Name *</label>
                            <input type="text" name="name" placeholder="e.g., KRS Candidates" required>
                        </div>
                        <div class="form-group">
                            <label>ID (auto-generated)</label>
                            <input type="text" value="Auto-generated from name" disabled style="background: #f5f5f5;">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Prefix *</label>
                            <input type="text" name="prefix" placeholder="e.g., KRS-" required style="font-family: monospace;">
                            <small style="color: #666;">Text before the number (e.g., "KRS-")</small>
                        </div>
                        <div class="form-group">
                            <label>Number Padding</label>
                            <input type="number" name="padding" value="4" min="1" max="10" required>
                            <small style="color: #666;">Digits in number (0001 vs 1)</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Start Value</label>
                            <input type="number" name="start_value" value="1" min="1">
                            <small style="color: #666;">Starting number for the sequence</small>
                        </div>
                        <div class="form-group">
                            <label>Increment</label>
                            <input type="number" name="increment" value="1" min="1">
                            <small style="color: #666;">How much to increase each time</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="What is this sequence used for?"></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="active" checked> 
                            Active (can generate IDs)
                        </label>
                        <label>
                            <input type="checkbox" name="make_default"> 
                            Set as Default Sequence
                        </label>
                    </div>
                    
                    <button type="submit" name="add_sequence" class="btn btn-primary">➕ Create Sequence</button>
                </form>
            </div>

            <!-- Existing Sequences -->
            <div class="content-card">
                <h2>📋 Existing Sequences</h2>
                
                <?php if (empty($sequences)): ?>
                    <div class="no-data">No sequences configured yet. Create one above!</div>
                <?php else: ?>
                    <div class="sequences-grid">
                        <?php foreach ($sequences as $seq): ?>
                            <div class="sequence-card <?php echo $seq['active'] ? 'active' : 'inactive'; ?>" id="seq-<?php echo $seq['id']; ?>">
                                <div class="sequence-header">
                                    <div class="sequence-status">
                                        <span class="status-dot <?php echo $seq['active'] ? 'active' : 'inactive'; ?>"></span>
                                        <?php echo $seq['active'] ? 'Active' : 'Inactive'; ?>
                                    </div>
                                    <?php if ($seq['id'] === $defaultSeqId): ?>
                                        <span class="default-badge">⭐ Default</span>
                                    <?php endif; ?>
                                </div>
                                
                                <h3><?php echo htmlspecialchars($seq['name']); ?></h3>
                                
                                <div class="sequence-preview">
                                    <div class="preview-label">Last ID:</div>
                                    <div class="preview-value">
                                        <?php 
                                        $lastValue = $seq['current_value'] - $seq['increment'];
                                        echo htmlspecialchars($seq['prefix'] . str_pad($lastValue, $seq['padding'], '0', STR_PAD_LEFT)); 
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="sequence-preview">
                                    <div class="preview-label">Next ID:</div>
                                    <div class="preview-value next">
                                        <?php echo htmlspecialchars($seq['prefix'] . str_pad($seq['current_value'], $seq['padding'], '0', STR_PAD_LEFT)); ?>
                                    </div>
                                </div>
                                
                                <div class="sequence-info">
                                    <div class="info-row">
                                        <span>Prefix:</span>
                                        <code><?php echo htmlspecialchars($seq['prefix']); ?></code>
                                    </div>
                                    <div class="info-row">
                                        <span>Padding:</span>
                                        <span><?php echo $seq['padding']; ?> digits</span>
                                    </div>
                                    <div class="info-row">
                                        <span>Increment:</span>
                                        <span>+<?php echo $seq['increment']; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span>Current:</span>
                                        <span><?php echo $seq['current_value']; ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($seq['description'])): ?>
                                    <p class="sequence-desc"><?php echo htmlspecialchars($seq['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="sequence-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" onclick="editSequence('<?php echo $seq['id']; ?>')">✏️ Edit</button>
                                    
                                    <?php if ($seq['id'] !== $defaultSeqId): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="sequence_id" value="<?php echo $seq['id']; ?>">
                                            <input type="hidden" name="set_default" value="1">
                                            <button type="submit" class="btn btn-sm btn-secondary">⭐ Set Default</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="sequence_id" value="<?php echo $seq['id']; ?>">
                                        <input type="hidden" name="delete_sequence" value="1">
                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Delete this sequence? This cannot be undone.')">🗑️ Delete</button>
                                    </form>
                                </div>
                                
                                <!-- Edit Form (hidden by default) -->
                                <div class="sequence-edit-form" id="edit-form-<?php echo $seq['id']; ?>" style="display:none;">
                                    <form method="post">
                                        <input type="hidden" name="sequence_id" value="<?php echo $seq['id']; ?>">
                                        <input type="hidden" name="update_sequence" value="1">
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Name *</label>
                                                <input type="text" name="name" value="<?php echo htmlspecialchars($seq['name']); ?>" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Prefix *</label>
                                                <input type="text" name="prefix" value="<?php echo htmlspecialchars($seq['prefix']); ?>" required style="font-family: monospace;">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Padding</label>
                                                <input type="number" name="padding" value="<?php echo $seq['padding']; ?>" min="1" max="10">
                                            </div>
                                            <div class="form-group">
                                                <label>Increment</label>
                                                <input type="number" name="increment" value="<?php echo $seq['increment']; ?>" min="1">
                                            </div>
                                        </div>
                                        
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label>Current Value</label>
                                                <input type="number" name="current_value" value="<?php echo $seq['current_value']; ?>" min="1">
                                                <small style="color: #666;">Use reset below for safer changes</small>
                                            </div>
                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea name="description" rows="2"><?php echo htmlspecialchars($seq['description']); ?></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="checkbox-group">
                                            <label>
                                                <input type="checkbox" name="active" <?php echo $seq['active'] ? 'checked' : ''; ?>> 
                                                Active
                                            </label>
                                            <label>
                                                <input type="checkbox" name="make_default" <?php echo $seq['id'] === $defaultSeqId ? 'checked' : ''; ?>> 
                                                Set as Default
                                            </label>
                                        </div>
                                        
                                        <div class="form-actions">
                                            <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                                            <button type="button" class="btn btn-secondary" onclick="cancelEdit('<?php echo $seq['id']; ?>')">Cancel</button>
                                        </div>
                                    </form>
                                    
                                    <!-- Reset Form -->
                                    <div class="reset-form">
                                        <h4>🔄 Reset Sequence</h4>
                                        <form method="post">
                                            <input type="hidden" name="sequence_id" value="<?php echo $seq['id']; ?>">
                                            <input type="hidden" name="reset_sequence" value="1">
                                            <div class="form-row" style="align-items: flex-end;">
                                                <div class="form-group" style="flex:1;">
                                                    <label>Reset to value:</label>
                                                    <input type="number" name="reset_value" value="<?php echo $seq['current_value']; ?>" min="1">
                                                </div>
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Reset this sequence? The next ID will change.')">Reset</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Import/Export -->
            <div class="content-card">
                <h2>📤 Export / 📥 Import Sequences</h2>
                
                <div class="export-import-grid">
                    <div class="export-section">
                        <h3>Export</h3>
                        <p>Copy or download all sequence configurations:</p>
                        <button type="button" onclick="exportSequences()" class="btn btn-secondary">📤 Download JSON</button>
                        <button type="button" onclick="copySequences()" class="btn btn-secondary">📋 Copy to Clipboard</button>
                        <textarea id="exportArea" style="display:none;"><?php echo htmlspecialchars(exportSequences()); ?></textarea>
                    </div>
                    
                    <div class="import-section">
                        <h3>Import</h3>
                        <form method="post">
                            <textarea name="import_json" rows="5" placeholder="Paste sequences JSON here..."></textarea>
                            <button type="submit" name="import_sequences" class="btn btn-secondary">📥 Import Sequences</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Preview Section -->
            <div class="content-card">
                <h2>👁️ Preview Generated IDs</h2>
                <p>See what the next few IDs will look like for each sequence:</p>
                
                <div class="preview-list">
                    <?php foreach ($sequences as $seq): ?>
                        <div class="preview-sequence">
                            <h4><?php echo htmlspecialchars($seq['name']); ?></h4>
                            <div class="preview-ids">
                                <?php 
                                $previewCount = min(5, $seq['increment'] > 1 ? 3 : 5);
                                $val = $seq['current_value'];
                                for ($i = 0; $i < $previewCount; $i++):
                                    echo '<span class="preview-id">' . htmlspecialchars($seq['prefix'] . str_pad($val, $seq['padding'], '0', STR_PAD_LEFT)) . '</span>';
                                    $val += $seq['increment'];
                                endfor;
                                ?>
                                <span class="preview-dots">...</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function editSequence(seqId) {
            const editForm = document.getElementById('edit-form-' + seqId);
            if (editForm) {
                editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
            }
        }

        function cancelEdit(seqId) {
            const editForm = document.getElementById('edit-form-' + seqId);
            if (editForm) {
                editForm.style.display = 'none';
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        function exportSequences() {
            const json = document.getElementById('exportArea').value;
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'sequences_config.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            showToast('Sequences exported!');
        }

        function copySequences() {
            const json = document.getElementById('exportArea').value;
            navigator.clipboard.writeText(json).then(() => {
                showToast('Copied to clipboard!');
            }).catch(() => {
                // Fallback
                const textarea = document.createElement('textarea');
                textarea.value = json;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied to clipboard!');
            });
        }
    </script>

    <style>
        .sequences-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .sequence-card {
            background: #fff;
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            padding: 20px;
            transition: box-shadow 0.3s;
        }
        
        .sequence-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .sequence-card.inactive {
            opacity: 0.7;
            background: #f8f9fa;
        }
        
        .sequence-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .sequence-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #ccc;
        }
        
        .status-dot.active {
            background: #27ae60;
        }
        
        .status-dot.inactive {
            background: #e74c3c;
        }
        
        .default-badge {
            background: #fef9e7;
            color: #f39c12;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.75em;
        }
        
        .sequence-card h3 {
            margin: 0 0 15px 0;
            color: #2c3e50;
        }
        
        .sequence-preview {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .preview-label {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .preview-value {
            font-family: monospace;
            font-size: 1.1em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .preview-value.next {
            color: #27ae60;
        }
        
        .sequence-info {
            margin: 15px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            font-size: 0.9em;
        }
        
        .info-row span:first-child {
            color: #7f8c8d;
        }
        
        .info-row code {
            background: #e8f4fd;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .sequence-desc {
            font-size: 0.9em;
            color: #7f8c8d;
            margin: 10px 0;
        }
        
        .sequence-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        
        .sequence-edit-form {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e1e5e9;
        }
        
        .reset-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .reset-form h4 {
            margin: 0 0 10px 0;
            font-size: 0.95em;
            color: #7f8c8d;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .export-import-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .export-section, .import-section {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .export-section h3, .import-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .export-section p {
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .export-section .btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .import-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            margin-bottom: 10px;
            resize: vertical;
        }
        
        .preview-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .preview-sequence {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .preview-sequence h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        
        .preview-ids {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .preview-id {
            background: #fff;
            padding: 5px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.95em;
            border: 1px solid #e1e5e9;
        }
        
        .preview-dots {
            color: #7f8c8d;
        }
        
        .toast {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 24px;
            background: #2c3e50;
            color: #fff;
            border-radius: 6px;
            z-index: 1000;
            animation: slideIn 0.3s;
        }
        
        .toast.success {
            background: #27ae60;
        }
        
        .toast.error {
            background: #e74c3c;
        }
        
        @keyframes slideIn {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .export-import-grid {
                grid-template-columns: 1fr;
            }
            
            .sequences-grid {
                grid-template-columns: 1fr;
            }
            
            .checkbox-group {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</body>
</html>

