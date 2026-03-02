<?php
require_once 'functions.php';

$action = $_GET['action'] ?? ($_POST['action'] ?? 'login');

if ($action === 'logout') handle_logout();

if ($action === 'dashboard' && !is_logged_in()) {
    header("Location: index.php?action=login"); exit();
}
if (in_array($action, ['login', 'signup']) && is_logged_in()) {
    header("Location: index.php?action=dashboard"); exit();
}

$errors  = [];
$success = '';
$prefill_email = $_COOKIE['remembered_email'] ?? '';



if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($action === 'signup') {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $errors   = validate_signup($username, $email, $password, $confirm);
        if (empty($errors)) {
            $errors = handle_signup($username, $email, $password);
            if (empty($errors)) { $success = "Account created! You can now log in."; $action = 'login'; }
        }
    }

    elseif ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember_me']);
        $errors   = validate_login($email, $password);
        if (empty($errors)) {
            if (handle_login($email, $password, $remember)) {
                header("Location: index.php?action=dashboard"); exit();
            } else {
                $errors[] = "Invalid email or password.";
            }
        }
    }

    elseif ($action === 'add_task' && is_logged_in()) {
        $data = [
            'room_number' => trim($_POST['room_number'] ?? ''),
            'task_type'   => $_POST['task_type'] ?? '',
            'priority'    => $_POST['priority'] ?? 'Medium',
            'status'      => $_POST['status'] ?? 'Pending',
            'assigned_to' => trim($_POST['assigned_to'] ?? ''),
            'due_date'    => $_POST['due_date'] ?? '',
            'notes'       => trim($_POST['notes'] ?? ''),
        ];
        $errors = handle_add_task($data);
        if (empty($errors)) { $success = "Task added successfully."; }
        $action = 'dashboard';
    }

    elseif ($action === 'edit_task' && is_logged_in()) {
        $id   = (int)($_POST['task_id'] ?? 0);
        $data = [
            'room_number' => trim($_POST['room_number'] ?? ''),
            'task_type'   => $_POST['task_type'] ?? '',
            'priority'    => $_POST['priority'] ?? 'Medium',
            'status'      => $_POST['status'] ?? 'Pending',
            'assigned_to' => trim($_POST['assigned_to'] ?? ''),
            'due_date'    => $_POST['due_date'] ?? '',
            'notes'       => trim($_POST['notes'] ?? ''),
        ];
        $errors = handle_edit_task($id, $data);
        if (empty($errors)) { $success = "Task updated successfully."; }
        $action = 'dashboard';
    }
}



if ($action === 'delete_task' && is_logged_in()) {
    handle_delete_task((int)($_GET['id'] ?? 0));
    header("Location: index.php?action=dashboard&success=deleted"); exit();
}

$tasks       = [];
$task_counts = [];
$edit_task   = null;
$filter      = $_GET['filter'] ?? 'All';

if (is_logged_in() && in_array($action, ['dashboard', 'add_task', 'edit_task'])) {
    $tasks       = get_all_tasks($filter);
    $task_counts = get_task_counts();
    if ($action === 'edit_task' && isset($_GET['id'])) {
        $edit_task = get_task_by_id((int)$_GET['id']);
    }
}

if (isset($_GET['success']) && $_GET['success'] === 'deleted') {
    $success = "Task deleted successfully.";
    $action  = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php
            if (in_array($action, ['dashboard','add_task','edit_task'])) echo 'Dashboard';
            elseif ($action === 'signup') echo 'Sign Up';
            else echo 'Login';
        ?>
        &mdash; HouseKeeper
    </title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body<?= in_array($action, ['dashboard','add_task','edit_task']) ? ' class="dash"' : '' ?>>

<?php /* ===================== DASHBOARD ===================== */ ?>
<?php if (in_array($action, ['dashboard','add_task','edit_task'])): ?>

<nav class="navbar">
    <div class="brand"><i class="fa-solid fa-broom"></i> House<span>Keeper</span></div>
    <div class="nav-right">
        <span class="nav-user">Hello, <strong><?= safe($_SESSION['username']) ?></strong>
            <em class="role-badge"><?= safe($_SESSION['role']) ?></em>
        </span>
        <a href="index.php?action=logout" class="btn-logout">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </div>
</nav>

<main class="dash-main">

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin-bottom:18px;">
            <ul><?php foreach ($errors as $e): ?><li><?= safe($e) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if (!empty($success)): ?>
        <div class="alert alert-success" style="margin-bottom:18px;">
            <i class="fa-solid fa-circle-check"></i> <?= safe($success) ?>
        </div>
    <?php endif; ?>

   
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-list-check"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $task_counts['Total'] ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $task_counts['Pending'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-spinner"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $task_counts['In Progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div class="stat-info">
                <div class="stat-num"><?= $task_counts['Completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
    </div>

    
    <div class="section-box">
        <h3>
            <?= $action === 'edit_task'
                ? '<i class="fa-solid fa-pen-to-square"></i> Edit Task'
                : '<i class="fa-solid fa-plus"></i> Add New Task' ?>
        </h3>
        <form action="index.php?action=<?= $action === 'edit_task' ? 'edit_task' : 'add_task' ?>" method="POST" class="task-form">
            <input type="hidden" name="action" value="<?= $action === 'edit_task' ? 'edit_task' : 'add_task' ?>">
            <?php if ($action === 'edit_task' && $edit_task): ?>
                <input type="hidden" name="task_id" value="<?= $edit_task['id'] ?>">
            <?php endif; ?>

            <div class="form-row">
                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" placeholder="e.g. 101"
                        value="<?= safe($edit_task['room_number'] ?? '') ?>" maxlength="10">
                </div>
                <div class="form-group">
                    <label>Task Type</label>
                    <select name="task_type">
                        <?php foreach (['Cleaning','Inspection','Laundry','Restocking','Maintenance','Turndown'] as $t): ?>
                            <option value="<?= $t ?>" <?= ($edit_task['task_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority">
                        <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                            <option value="<?= $p ?>" <?= ($edit_task['priority'] ?? 'Medium') === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <?php foreach (['Pending','In Progress','Completed','Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= ($edit_task['status'] ?? 'Pending') === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Assigned To</label>
                    <input type="text" name="assigned_to" placeholder="Staff name (optional)"
                        value="<?= safe($edit_task['assigned_to'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Due Date</label>
                    <input type="date" name="due_date" value="<?= safe($edit_task['due_date'] ?? '') ?>">
                </div>
                <div class="form-group" style="flex:2;">
                    <label>Notes</label>
                    <input type="text" name="notes" placeholder="Optional notes..."
                        value="<?= safe($edit_task['notes'] ?? '') ?>">
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $action === 'edit_task'
                        ? '<i class="fa-solid fa-floppy-disk"></i> Save Changes'
                        : '<i class="fa-solid fa-plus"></i> Add Task' ?>
                </button>
                <?php if ($action === 'edit_task'): ?>
                    <a href="index.php?action=dashboard" class="btn btn-cancel">
                        <i class="fa-solid fa-xmark"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    
    <div class="section-box">
        <div class="table-header">
            <h3><i class="fa-solid fa-table-list"></i> Task List</h3>
            <div class="filter-tabs">
                <?php foreach (['All','Pending','In Progress','Completed','Cancelled'] as $f): ?>
                    <a href="index.php?action=dashboard&filter=<?= urlencode($f) ?>"
                       class="filter-tab <?= $filter === $f ? 'active' : '' ?>"><?= $f ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (empty($tasks)): ?>
            <div class="empty-state">
                <i class="fa-solid fa-broom"></i>
                No tasks found. Add one above!
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table class="task-table">
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Type</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th>Due Date</th>
                        <th>Notes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($tasks as $t): ?>
                    <tr>
                        <td><strong><?= safe($t['room_number']) ?></strong></td>
                        <td><?= safe($t['task_type']) ?></td>
                        <td><span class="badge <?= priority_class($t['priority']) ?>"><?= safe($t['priority']) ?></span></td>
                        <td><span class="badge <?= status_class($t['status']) ?>"><?= safe($t['status']) ?></span></td>
                        <td><?= $t['assigned_to'] ? safe($t['assigned_to']) : '<span class="muted">—</span>' ?></td>
                        <td><?= $t['due_date'] ? date('M d, Y', strtotime($t['due_date'])) : '<span class="muted">—</span>' ?></td>
                        <td class="notes-cell"><?= $t['notes'] ? safe($t['notes']) : '<span class="muted">—</span>' ?></td>
                        <td class="actions-cell">
                            <a href="index.php?action=edit_task&id=<?= $t['id'] ?>" class="btn-action btn-edit">
                                <i class="fa-solid fa-pen"></i> Edit
                            </a>
                            <a href="index.php?action=delete_task&id=<?= $t['id'] ?>"
                               class="btn-action btn-delete"
                               onclick="return confirm('Delete this task?')">
                                <i class="fa-solid fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</main>

<?php /* ===================== LOGIN / SIGNUP ===================== */ ?>
<?php else: ?>

<div class="card">
    <div class="card-header">
        <h1><i class="fa-solid fa-broom"></i> HouseKeeper</h1>
        <p>Housekeeping Task Management System</p>
    </div>

    <div class="tabs">
        <button class="tab-btn <?= $action !== 'signup' ? 'active' : '' ?>"
                onclick="location.href='index.php?action=login'">
            <i class="fa-solid fa-right-to-bracket"></i> Login
        </button>
        <button class="tab-btn <?= $action === 'signup' ? 'active' : '' ?>"
                onclick="location.href='index.php?action=signup'">
            <i class="fa-solid fa-user-plus"></i> Sign Up
        </button>
    </div>

    <div class="card-body">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <ul><?php foreach ($errors as $e): ?><li><?= safe($e) ?></li><?php endforeach; ?></ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check"></i> <?= safe($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($action !== 'signup'): ?>
        
        <form action="index.php?action=login" method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com"
                       value="<?= safe($prefill_email) ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Your password">
            </div>
            <div class="checkbox-row">
                <input type="checkbox" id="remember_me" name="remember_me"
                       <?= !empty($_COOKIE['remembered_email']) ? 'checked' : '' ?>>
                <label for="remember_me">Remember my email for 30 days</label>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-right-to-bracket"></i> Log In
            </button>
        </form>

        <?php else: ?>
        
        <form action="index.php?action=signup" method="POST">
            <input type="hidden" name="action" value="signup">
            <div class="form-group">
                <label for="username"><i class="fa-solid fa-user"></i> Username</label>
                <input type="text" id="username" name="username" placeholder="e.g. johndoe"
                       maxlength="30" value="<?= safe($_POST['username'] ?? '') ?>">
                <p class="hint">3–30 characters</p>
            </div>
            <div class="form-group">
                <label for="email"><i class="fa-solid fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" placeholder="you@example.com"
                       value="<?= safe($_POST['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="password"><i class="fa-solid fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="At least 8 characters">
                <p class="hint">Minimum 8 characters</p>
            </div>
            <div class="form-group">
                <label for="confirm_password"><i class="fa-solid fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       placeholder="Re-enter password">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<?php endif; ?>
</body>
</html>