<?php
include '../includes/candidate.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_candidate'])) {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'position' => $_POST['position']
        ];
        $id = createCandidate($data);
        if ($id) {
            $message = 'Candidate created: ' . $id;
        } else {
            $message = 'Failed to create candidate';
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
        <a href="../index.php?page=logout">Logout</a>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <h2>Create Candidate</h2>
        <form method="post" enctype="multipart/form-data">
            <label>Name: <input name="name" required></label>
            <label>Email: <input type="email" name="email" required></label>
            <label>Phone: <input name="phone" required></label>
            <label>Position: <input name="position" required></label>
            <label>Resume: <input type="file" name="resume" required></label>
            <button name="create_candidate">Create</button>
        </form>

        <h2>Candidates</h2>
        <?php foreach ($candidates as $id => $cand): ?>
            <div class="candidate-card">
                <h3><?php echo $cand['name'] . ' (' . $id . ') - Step: ' . $cand['current_step'] . ' - ' . STEPS[$cand['current_step']]; ?></h3>
                <p>Status: <?php echo $cand['status']; ?></p>
                <?php if ($cand['status'] === 'IN_PROGRESS' && $cand['current_step'] < 7): ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="candidate_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="step" value="<?php echo $cand['current_step'] + 1; ?>">
                        <?php
                        $nextStep = $cand['current_step'] + 1;
                        if ($nextStep == 2) {
                            echo '<label><input type="radio" name="choice" value="confirmation" required> Confirmation</label>';
                            echo '<label><input type="radio" name="choice" value="cancellation"> Cancellation</label>';
                            echo '<label>Letter: <input type="file" name="letter"></label>';
                        } elseif ($nextStep == 3) {
                            echo '<label>Documents: <input type="file" name="documents[]" multiple></label>';
                            echo '<label>Verification: <select name="verification"><option>Pending</option><option>Verified</option><option>Rejected</option></select></label>';
                        } elseif ($nextStep == 4 || $nextStep == 6) {
                            echo '<label>Date: <input type="date" name="date" required></label>';
                            echo '<label>Time: <input type="time" name="time" required></label>';
                            echo '<label>Mode: <select name="mode"><option>Online</option><option>Offline</option></select></label>';
                            echo '<label>Interviewer: <input name="interviewer" required></label>';
                        } elseif ($nextStep == 5 || $nextStep == 7) {
                            echo '<label>Result: <select name="result"><option>Selected</option><option>Rejected</option></select></label>';
                            echo '<label>Remarks: <textarea name="remarks" required></textarea></label>';
                        }
                        ?>
                        <button name="move_step">Move to Next Step</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>