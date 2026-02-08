# Sequence Generator Implementation - TODO List

## Phase 1: Configuration & Core Logic
- [x] Create `config/sequences.json` - Store sequence configurations
- [x] Create `includes/sequence.php` - Core sequence generation functions

## Phase 2: Admin Interface
- [x] Create `admin/sequence_manager.php` - Admin UI for managing sequences
- [x] Update `admin/dashboard.php` - Add "Sequences" menu item under Configuration

## Phase 3: Integration
- [x] Update `includes/candidate.php` - Replace random ID with sequence-based ID generation
- [x] Test the complete flow ✅

## Notes
- Support multiple sequences (e.g., KRS-, EMP-, CAND-)
- Generate IDs like KRS-0001, KRS-0002, etc.
- Allow setting prefix, padding, start number, and increment value

