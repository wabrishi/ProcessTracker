<?php
/**
 * Sequence Generator - Dynamic ID Generation System
 * 
 * Manages sequential ID generation with customizable prefixes, padding, and increments.
 */

define('SEQUENCES_CONFIG_PATH', __DIR__ . '/../config/sequences.json');

/**
 * Load sequences configuration
 */
function getSequencesConfig(): array {
    if (!file_exists(SEQUENCES_CONFIG_PATH)) {
        return ['sequences' => [], 'settings' => []];
    }
    $content = file_get_contents(SEQUENCES_CONFIG_PATH);
    return json_decode($content, true) ?? ['sequences' => [], 'settings' => []];
}

/**
 * Save sequences configuration
 */
function saveSequencesConfig(array $config): bool {
    $json = json_encode($config, JSON_PRETTY_PRINT);
    return file_put_contents(SEQUENCES_CONFIG_PATH, $json) !== false;
}

/**
 * Get all sequences
 */
function getAllSequences(): array {
    $config = getSequencesConfig();
    return $config['sequences'] ?? [];
}

/**
 * Get a specific sequence by ID
 */
function getSequence(string $sequenceId): ?array {
    $sequences = getAllSequences();
    foreach ($sequences as $seq) {
        if ($seq['id'] === $sequenceId) {
            return $seq;
        }
    }
    return null;
}

/**
 * Get the active sequence for candidate ID generation
 */
function getActiveCandidateSequence(): ?array {
    $config = getSequencesConfig();
    $sequences = $config['sequences'] ?? [];
    
    // First try to get the default sequence
    $defaultId = $config['settings']['default_sequence'] ?? null;
    if ($defaultId) {
        foreach ($sequences as $seq) {
            if ($seq['id'] === $defaultId && $seq['active']) {
                return $seq;
            }
        }
    }
    
    // Fallback to first active sequence
    foreach ($sequences as $seq) {
        if ($seq['active']) {
            return $seq;
        }
    }
    
    return null;
}

/**
 * Create a new sequence
 */
function createSequence(array $data): bool {
    $config = getSequencesConfig();
    
    // Generate ID if not provided
    if (empty($data['id'])) {
        $data['id'] = strtolower(str_replace([' ', '-'], '_', $data['name'])) . '_sequence';
    }
    
    // Check if ID already exists
    foreach ($config['sequences'] as $seq) {
        if ($seq['id'] === $data['id']) {
            return false;
        }
    }
    
    $newSequence = [
        'id' => $data['id'],
        'name' => $data['name'] ?? 'New Sequence',
        'prefix' => $data['prefix'] ?? 'SEQ-',
        'padding' => (int)($data['padding'] ?? 4),
        'current_value' => (int)($data['start_value'] ?? 1),
        'increment' => (int)($data['increment'] ?? 1),
        'active' => (bool)($data['active'] ?? true),
        'description' => $data['description'] ?? ''
    ];
    
    $config['sequences'][] = $newSequence;
    return saveSequencesConfig($config);
}

/**
 * Update an existing sequence
 */
function updateSequence(string $sequenceId, array $data): bool {
    $config = getSequencesConfig();
    $found = false;
    
    foreach ($config['sequences'] as &$seq) {
        if ($seq['id'] === $sequenceId) {
            // Update fields
            if (isset($data['name'])) $seq['name'] = $data['name'];
            if (isset($data['prefix'])) $seq['prefix'] = $data['prefix'];
            if (isset($data['padding'])) $seq['padding'] = (int)$data['padding'];
            if (isset($data['increment'])) $seq['increment'] = (int)$data['increment'];
            if (isset($data['active'])) $seq['active'] = (bool)$data['active'];
            if (isset($data['description'])) $seq['description'] = $data['description'];
            
            // Allow resetting current value
            if (isset($data['current_value'])) {
                $seq['current_value'] = (int)$data['current_value'];
            }
            
            $found = true;
            break;
        }
    }
    
    if ($found) {
        return saveSequencesConfig($config);
    }
    
    return false;
}

/**
 * Delete a sequence
 */
function deleteSequence(string $sequenceId): bool {
    $config = getSequencesConfig();
    $originalCount = count($config['sequences']);
    
    $config['sequences'] = array_values(array_filter($config['sequences'], function($seq) use ($sequenceId) {
        return $seq['id'] !== $sequenceId;
    }));
    
    if (count($config['sequences']) < $originalCount) {
        return saveSequencesConfig($config);
    }
    
    return false;
}

/**
 * Generate the next ID from a sequence
 */
function generateNextId(string $sequenceId): ?string {
    $config = getSequencesConfig();
    
    foreach ($config['sequences'] as &$seq) {
        if ($seq['id'] === $sequenceId && $seq['active']) {
            $id = $seq['prefix'] . str_pad($seq['current_value'], $seq['padding'], '0', STR_PAD_LEFT);
            $seq['current_value'] += $seq['increment'];
            
            saveSequencesConfig($config);
            return $id;
        }
    }
    
    return null;
}

/**
 * Generate next candidate ID using active sequence
 */
function generateCandidateIdFromSequence(): string {
    $sequence = getActiveCandidateSequence();
    
    if ($sequence) {
        $id = $sequence['prefix'] . str_pad($sequence['current_value'], $sequence['padding'], '0', STR_PAD_LEFT);
        
        // Update the sequence
        $config = getSequencesConfig();
        foreach ($config['sequences'] as &$seq) {
            if ($seq['id'] === $sequence['id']) {
                $seq['current_value'] += $seq['increment'];
                break;
            }
        }
        saveSequencesConfig($config);
        
        return $id;
    }
    
    // Fallback to random ID if no active sequence
    return 'CAND' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Set the default sequence
 */
function setDefaultSequence(string $sequenceId): bool {
    $config = getSequencesConfig();
    
    // Verify sequence exists
    $found = false;
    foreach ($config['sequences'] as $seq) {
        if ($seq['id'] === $sequenceId) {
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        return false;
    }
    
    $config['settings']['default_sequence'] = $sequenceId;
    return saveSequencesConfig($config);
}

/**
 * Activate/deactivate a sequence
 */
function toggleSequenceStatus(string $sequenceId, bool $active): bool {
    return updateSequence($sequenceId, ['active' => $active]);
}

/**
 * Reset sequence to a specific value
 */
function resetSequence(string $sequenceId, int $newValue): bool {
    return updateSequence($sequenceId, ['current_value' => $newValue]);
}

/**
 * Get the next ID without advancing (preview)
 */
function peekNextId(string $sequenceId): ?string {
    $sequence = getSequence($sequenceId);
    
    if ($sequence && $sequence['active']) {
        return $sequence['prefix'] . str_pad($sequence['current_value'], $sequence['padding'], '0', STR_PAD_LEFT);
    }
    
    return null;
}

/**
 * Check if a sequence ID is unique
 */
function isUniqueId(string $id, string $excludeKey = 'candidates'): bool {
    // Check against candidates
    $candidatesFile = __DIR__ . '/../database/candidates.json';
    if (file_exists($candidatesFile)) {
        $candidates = json_decode(file_get_contents($candidatesFile), true);
        if (isset($candidates[$id])) {
            return false;
        }
    }
    
    // Check against other sequences
    $sequences = getAllSequences();
    foreach ($sequences as $seq) {
        if ($seq['active']) {
            $generatedId = $seq['prefix'] . str_pad($seq['current_value'], $seq['padding'], '0', STR_PAD_LEFT);
            if ($generatedId === $id) {
                return false;
            }
        }
    }
    
    return true;
}

/**
 * Import sequences from JSON
 */
function importSequences(string $json): bool {
    $imported = json_decode($json, true);
    if (!$imported || !isset($imported['sequences'])) {
        return false;
    }
    
    $config = getSequencesConfig();
    
    foreach ($imported['sequences'] as $importSeq) {
        // Check if sequence with same ID exists, if so, update it
        $found = false;
        foreach ($config['sequences'] as &$existingSeq) {
            if ($existingSeq['id'] === $importSeq['id']) {
                $existingSeq = array_merge($existingSeq, $importSeq);
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $config['sequences'][] = $importSeq;
        }
    }
    
    if (isset($imported['settings'])) {
        $config['settings'] = array_merge($config['settings'] ?? [], $imported['settings']);
    }
    
    return saveSequencesConfig($config);
}

/**
 * Export all sequences as JSON
 */
function exportSequences(): string {
    return json_encode(getSequencesConfig(), JSON_PRETTY_PRINT);
}

/**
 * Get sequence statistics
 */
function getSequenceStats(): array {
    $sequences = getAllSequences();
    $stats = [
        'total_sequences' => count($sequences),
        'active_sequences' => 0,
        'sequences' => []
    ];
    
    foreach ($sequences as $seq) {
        $lastId = $seq['prefix'] . str_pad($seq['current_value'] - $seq['increment'], $seq['padding'], '0', STR_PAD_LEFT);
        $nextId = $seq['prefix'] . str_pad($seq['current_value'], $seq['padding'], '0', STR_PAD_LEFT);
        
        $stats['sequences'][] = [
            'id' => $seq['id'],
            'name' => $seq['name'],
            'prefix' => $seq['prefix'],
            'current_value' => $seq['current_value'],
            'last_id' => $lastId,
            'next_id' => $nextId,
            'active' => $seq['active']
        ];
        
        if ($seq['active']) {
            $stats['active_sequences']++;
        }
    }
    
    return $stats;
}

