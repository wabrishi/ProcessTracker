<?php
include '../includes/helpers.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $users = getUsers();
        $id = 'U' . str_pad(count($users) + 1, 3, '0', STR_PAD_LEFT);
        $users[] = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'password' => $password, 'active' => true];
        saveUsers($users);
        $message = 'User added';
    } elseif (isset($_POST['toggle_user'])) {
        $id = $_POST['user_id'];
        $users = getUsers();
        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user['active'] = !$user['active'];
                break;
            }
        }
        saveUsers($users);
        $message = 'User status updated';
    } elseif (isset($_POST['update_smtp'])) {
        $smtp = [
            'host' => $_POST['host'],
            'port' => (int)$_POST['port'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'from_email' => $_POST['from_email'],
            'from_name' => $_POST['from_name']
        ];
        file_put_contents(__DIR__ . '/../config/smtp.json', json_encode($smtp, JSON_PRETTY_PRINT));
        $message = 'SMTP updated';
    } elseif (isset($_POST['update_template'])) {
        $template = $_POST['template'];
        $content = $_POST['content'];
        file_put_contents(__DIR__ . '/../mail_templates/' . $template . '.html', $content);
        $message = 'Template updated';
    }
}

$users = getUsers();
$candidates = getCandidates();
$smtp = json_decode(file_get_contents(__DIR__ . '/../config/smtp.json'), true);
$templates = ['confirmation', 'cancellation', 'interview_schedule', 'profile_selected'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            background: #e9ecef;
            border: 1px solid #ddd;
            border-bottom: none;
            transition: background 0.3s;
        }
        .tab:hover {
            background: #d1ecf1;
        }
        .tab.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #007bff;
            font-weight: bold;
        }
        .tab-content {
            display: none;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
        }
        .tab-input {
            display: none;
        }
        .tab-input:checked + .tab {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <a href="../index.php?page=logout">Logout</a>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <div class="tabs">
            <input type="radio" name="tab" id="tab1" class="tab-input" checked>
            <label for="tab1" class="tab">Manage HR Users</label>
            <input type="radio" name="tab" id="tab2" class="tab-input">
            <label for="tab2" class="tab">All Candidates</label>
            <input type="radio" name="tab" id="tab3" class="tab-input">
            <label for="tab3" class="tab">SMTP Config</label>
            <input type="radio" name="tab" id="tab4" class="tab-input">
            <label for="tab4" class="tab">Mail Templates</label>
        </div>

        <div class="tab-content" style="display: block;">
            <h2>Manage HR Users</h2>
            <form method="post">
                <label>Name: <input name="name" required></label>
                <label>Email: <input type="email" name="email" required></label>
                <label>Password: <input type="password" name="password" required></label>
                <label>Role: <select name="role"><option value="HR">HR</option></select></label>
                <button name="add_user">Add User</button>
            </form>
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): if ($user['role'] === 'HR') { ?>
                        <tr>
                            <td data-label="Name"><?php echo $user['name']; ?></td>
                            <td data-label="Email"><?php echo $user['email']; ?></td>
                            <td data-label="Status"><?php echo $user['active'] ? 'Active' : 'Disabled'; ?></td>
                            <td data-label="Action">
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button name="toggle_user"><?php echo $user['active'] ? 'Disable' : 'Enable'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content">
            <h2>All Candidates</h2>
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Step</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($candidates as $id => $cand): ?>
                        <tr>
                            <td data-label="Name"><?php echo $cand['name']; ?></td>
                            <td data-label="Step"><?php echo $cand['current_step']; ?></td>
                            <td data-label="Status"><?php echo $cand['status']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="tab-content">
            <h2>SMTP Configuration</h2>
            <form method="post">
                <label>Host: <input name="host" value="<?php echo $smtp['host']; ?>" required></label>
                <label>Port: <input type="number" name="port" value="<?php echo $smtp['port']; ?>" required></label>
                <label>Username: <input name="username" value="<?php echo $smtp['username']; ?>"></label>
                <label>Password: <input type="password" name="password" value="<?php echo $smtp['password']; ?>"></label>
                <label>From Email: <input type="email" name="from_email" value="<?php echo $smtp['from_email']; ?>" required></label>
                <label>From Name: <input name="from_name" value="<?php echo $smtp['from_name']; ?>" required></label>
                <button name="update_smtp">Update SMTP</button>
            </form>
        </div>

        <div class="tab-content">
            <h2>Mail Templates</h2>
            <?php foreach ($templates as $tpl): ?>
                <h3><?php echo ucfirst($tpl); ?></h3>
                <form method="post">
                    <input type="hidden" name="template" value="<?php echo $tpl; ?>">
                    <textarea name="content" rows="10"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/../mail_templates/' . $tpl . '.html')); ?></textarea>
                    <button name="update_template">Update</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.tab-input').forEach((input, index) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.querySelectorAll('.tab-content')[index].style.display = 'block';
            });
        });
    </script>
    </div>
</body>
</html>