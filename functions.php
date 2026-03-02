<?php
session_start();

function get_db() {
    $conn = new mysqli('localhost', 'root', '');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $conn->query("CREATE DATABASE IF NOT EXISTS housekeeping_db");
    $conn->select_db('housekeeping_db');

    $conn->query("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(30) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','staff') DEFAULT 'staff',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            room_number VARCHAR(10) NOT NULL,
            task_type ENUM('Cleaning','Inspection','Laundry','Restocking','Maintenance','Turndown') NOT NULL,
            priority ENUM('Low','Medium','High','Urgent') DEFAULT 'Medium',
            status ENUM('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
            assigned_to VARCHAR(30),
            due_date DATE,
            notes TEXT,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id)
        )
    ");

    return $conn;
}



function validate_signup($username, $email, $password, $confirm) {
    $errors = [];
    if (empty($username) || empty($email) || empty($password) || empty($confirm)) {
        $errors[] = "All fields are required.";
        return $errors;
    }
    if (strlen($username) < 3 || strlen($username) > 30)
        $errors[] = "Username must be between 3 and 30 characters.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";
    if (strlen($password) < 8)
        $errors[] = "Password must be at least 8 characters.";
    if ($password !== $confirm)
        $errors[] = "Passwords do not match.";
    return $errors;
}

function validate_login($email, $password) {
    $errors = [];
    if (empty($email) || empty($password)) {
        $errors[] = "Email and password are required.";
        return $errors;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Enter a valid email address.";
    if (strlen($password) < 8)
        $errors[] = "Password must be at least 8 characters.";
    return $errors;
}



function handle_signup($username, $email, $password) {
    $conn = get_db();
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close(); $conn->close();
        return ["Username or email already exists."];
    }
    $stmt->close();
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $stmt   = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashed);
    $ok = $stmt->execute();
    $stmt->close(); $conn->close();
    return $ok ? [] : ["Signup failed. Please try again."];
}

function handle_login($email, $password, $remember) {
    $conn = get_db();
    $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['login_time'] = date('H:i  d M Y');

            // Visible in DevTools → Application → Cookies
            setcookie('session_user_id',    $user['id'],         0, '/');
            setcookie('session_username',   $user['username'],   0, '/');
            setcookie('session_role',       $user['role'],       0, '/');
            setcookie('session_login_time', date('H:i d M Y'), 0, '/');

            if ($remember) {
                setcookie('remembered_email', $email, time() + (30 * 24 * 3600), '/');
            } else {
                setcookie('remembered_email', '', time() - 3600, '/');
            }
            $stmt->close(); $conn->close();
            return true;
        }
    }
    $stmt->close(); $conn->close();
    return false;
}

function handle_logout() {
    $_SESSION = [];
    session_destroy();
    setcookie('remembered_email',   '', time() - 3600, '/');
    setcookie('session_user_id',    '', time() - 3600, '/');
    setcookie('session_username',   '', time() - 3600, '/');
    setcookie('session_role',       '', time() - 3600, '/');
    setcookie('session_login_time', '', time() - 3600, '/');
    header("Location: index.php");
    exit();
}



function get_all_tasks($filter_status = '') {
    $conn = get_db();
    if ($filter_status && $filter_status !== 'All') {
        $stmt = $conn->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY 
            FIELD(priority,'Urgent','High','Medium','Low'), due_date ASC");
        $stmt->bind_param("s", $filter_status);
    } else {
        $stmt = $conn->prepare("SELECT * FROM tasks ORDER BY 
            FIELD(priority,'Urgent','High','Medium','Low'), due_date ASC");
    }
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close(); $conn->close();
    return $tasks;
}

function get_task_by_id($id) {
    $conn = get_db();
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    $stmt->close(); $conn->close();
    return $task;
}

function get_task_counts() {
    $conn   = get_db();
    $result = $conn->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status");
    $counts = ['Pending' => 0, 'In Progress' => 0, 'Completed' => 0, 'Cancelled' => 0, 'Total' => 0];
    while ($row = $result->fetch_assoc()) {
        $counts[$row['status']] = $row['count'];
        $counts['Total'] += $row['count'];
    }
    $conn->close();
    return $counts;
}

function validate_task($data) {
    $errors = [];
    if (empty($data['room_number'])) $errors[] = "Room number is required.";
    if (empty($data['task_type']))   $errors[] = "Task type is required.";
    if (empty($data['due_date']))    $errors[] = "Due date is required.";
    return $errors;
}

function handle_add_task($data) {
    $errors = validate_task($data);
    if (!empty($errors)) return $errors;
    $conn = get_db();
    $stmt = $conn->prepare("
        INSERT INTO tasks (room_number, task_type, priority, status, assigned_to, due_date, notes, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssssi",
        $data['room_number'], $data['task_type'], $data['priority'],
        $data['status'], $data['assigned_to'], $data['due_date'],
        $data['notes'], $_SESSION['user_id']
    );
    $ok = $stmt->execute();
    $stmt->close(); $conn->close();
    return $ok ? [] : ["Failed to add task."];
}

function handle_edit_task($id, $data) {
    $errors = validate_task($data);
    if (!empty($errors)) return $errors;
    $conn = get_db();
    $stmt = $conn->prepare("
        UPDATE tasks SET room_number=?, task_type=?, priority=?, status=?,
        assigned_to=?, due_date=?, notes=? WHERE id=?
    ");
    $stmt->bind_param("sssssssi",
        $data['room_number'], $data['task_type'], $data['priority'],
        $data['status'], $data['assigned_to'], $data['due_date'],
        $data['notes'], $id
    );
    $ok = $stmt->execute();
    $stmt->close(); $conn->close();
    return $ok ? [] : ["Failed to update task."];
}

function handle_delete_task($id) {
    $conn = get_db();
    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close(); $conn->close();
}



function is_logged_in() { return isset($_SESSION['user_id']); }

function safe($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }

function priority_class($p) {
    return match($p) {
        'Urgent' => 'badge-urgent',
        'High'   => 'badge-high',
        'Medium' => 'badge-medium',
        'Low'    => 'badge-low',
        default  => ''
    };
}

function status_class($s) {
    return match($s) {
        'Pending'     => 'badge-pending',
        'In Progress' => 'badge-inprogress',
        'Completed'   => 'badge-completed',
        'Cancelled'   => 'badge-cancelled',
        default       => ''
    };
}
?>