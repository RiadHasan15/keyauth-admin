<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php';

// Load apps for dropdown
$appRes = $mysqli->query("SELECT * FROM apps ORDER BY name ASC");
$apps = $appRes ? $appRes->fetch_all(MYSQLI_ASSOC) : [];

// Helper function to hash passwords
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add user
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;

        if ($username === '' || $password === '') {
            $error = "Username and password are required.";
        } elseif ($app_id <= 0) {
            $error = "Please select a valid App.";
        } else {
            // Validate expiration date format or set null
            if ($expires_at) {
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $expires_at);
                if (!$date) {
                    $error = "Invalid expiration date format.";
                } else {
                    $expires_at = $date->format('Y-m-d H:i:s');
                }
            }

            if (!isset($error)) {
                $hashed_password = hashPassword($password);

                $stmt = $mysqli->prepare("INSERT INTO users (username, password, expires_at, banned, app_id) VALUES (?, ?, ?, 0, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssi", $username, $hashed_password, $expires_at, $app_id);
                    $stmt->execute();
                    $stmt->close();

                    logAction("User added: $username");
                    header("Location: users.php");
                    exit;
                } else {
                    $error = "Database error: " . $mysqli->error;
                }
            }
        }
    }

    // Delete user
    if (isset($_POST['delete_user'])) {
        $id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();

            logAction("User deleted: ID $id");
            header("Location: users.php");
            exit;
        }
    }

    // Edit user
    if (isset($_POST['edit_user'])) {
        $id = intval($_POST['user_id']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;
        $app_id = isset($_POST['app_id']) ? intval($_POST['app_id']) : 0;

        if ($username === '') {
            $error = "Username is required.";
        } elseif ($app_id <= 0) {
            $error = "Please select a valid App.";
        } else {
            // Validate expiration date format or set null
            if ($expires_at) {
                $date = DateTime::createFromFormat('Y-m-d\TH:i', $expires_at);
                if (!$date) {
                    $error = "Invalid expiration date format.";
                } else {
                    $expires_at = $date->format('Y-m-d H:i:s');
                }
            }

            if (!isset($error)) {
                if ($password !== '') {
                    // Update with new password
                    $hashed_password = hashPassword($password);
                    $stmt = $mysqli->prepare("UPDATE users SET username = ?, password = ?, expires_at = ?, app_id = ? WHERE id = ?");
                    $stmt->bind_param("sssii", $username, $hashed_password, $expires_at, $app_id, $id);
                } else {
                    // Update without password (keep old)
                    $stmt = $mysqli->prepare("UPDATE users SET username = ?, expires_at = ?, app_id = ? WHERE id = ?");
                    $stmt->bind_param("siii", $username, $expires_at, $app_id, $id);
                }

                if ($stmt) {
                    $stmt->execute();
                    $stmt->close();

                    logAction("User edited: ID $id");
                    header("Location: users.php");
                    exit;
                } else {
                    $error = "Database error: " . $mysqli->error;
                }
            }
        }
    }

    // Toggle ban/unban user
    if (isset($_POST['toggle_ban'])) {
        $id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare("SELECT banned FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row) {
                $new_status = $row['banned'] ? 0 : 1;
                $stmtUpdate = $mysqli->prepare("UPDATE users SET banned = ? WHERE id = ?");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param("ii", $new_status, $id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    logAction(($new_status ? 'Banned' : 'Unbanned') . " User ID $id");
                }
            }
        }
        header("Location: users.php");
        exit;
    }

    // Reset HWID
    if (isset($_POST['reset_hwid'])) {
        $id = intval($_POST['user_id']);
        $stmt = $mysqli->prepare("UPDATE users SET hwid = NULL WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            logAction("HWID Reset for User ID $id");
        }
        header("Location: users.php");
        exit;
    }
}

// Get filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$app_filter = isset($_GET['app_filter']) ? intval($_GET['app_filter']) : 0;
$status_filter = isset($_GET['status_filter']) ? $_GET['status_filter'] : '';

// Build query with filters
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR hwid LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'ss';
}

if ($app_filter > 0) {
    $where_conditions[] = "app_id = ?";
    $params[] = $app_filter;
    $param_types .= 'i';
}

if ($status_filter === 'banned') {
    $where_conditions[] = "banned = 1";
} elseif ($status_filter === 'active') {
    $where_conditions[] = "banned = 0";
} elseif ($status_filter === 'expired') {
    $where_conditions[] = "expires_at IS NOT NULL AND expires_at < NOW()";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$query = "SELECT * FROM users $where_clause ORDER BY id DESC";

if (!empty($params)) {
    $stmt = $mysqli->prepare($query);
    if ($stmt && !empty($param_types)) {
        $stmt->bind_param($param_types, ...$params);
        $stmt->execute();
        $userRes = $stmt->get_result();
        $users = $userRes ? $userRes->fetch_all(MYSQLI_ASSOC) : [];
        $stmt->close();
    } else {
        $users = [];
    }
} else {
    $userRes = $mysqli->query($query);
    $users = $userRes ? $userRes->fetch_all(MYSQLI_ASSOC) : [];
}

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - KeyAuth Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-inline {
            display: inline-block;
        }
        .error {
            color: red;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .hwid-cell {
            max-width: 150px;
            word-break: break-all;
            font-size: 0.85em;
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .btn-sm {
                font-size: 0.7rem;
                padding: 0.2rem 0.4rem;
            }
            
            .hwid-cell {
                max-width: 100px;
                font-size: 0.7em;
            }
            
            .mobile-stack {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .mobile-stack .btn {
                width: 100%;
                margin: 0;
            }
            
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            
            .mobile-hide {
                display: none;
            }
            
            .card-body {
                padding: 0.75rem;
            }
            
            .container {
                padding-left: 0.5rem;
                padding-right: 0.5rem;
            }
        }
        
        /* Desktop styles for action buttons */
        @media (min-width: 769px) {
            .action-buttons {
                display: flex;
                gap: 4px;
                flex-wrap: wrap;
            }
        }
        
        .search-filters {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .user-card {
            display: none;
        }
        
        @media (max-width: 768px) {
            .table-view {
                display: none;
            }
            
            .user-card {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 1rem;
                background: white;
            }
            
            .user-card-header {
                display: flex;
                justify-content: between;
                align-items-center;
                margin-bottom: 0.5rem;
                font-weight: bold;
            }
            
            .user-card-body {
                font-size: 0.9rem;
            }
            
            .user-card-actions {
                margin-top: 1rem;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
        .filter-badge {
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
<div class="container-fluid mt-3">
    <!-- Header -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2">
                <h2 class="mb-0">
                    <i class="fas fa-users me-2"></i>User Management
                    <?php if (!empty($search) || $app_filter > 0 || !empty($status_filter)): ?>
                        <span class="badge bg-info filter-badge">Filtered</span>
                    <?php endif; ?>
                </h2>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left me-1"></i>Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-danger btn-sm">
                        <i class="fas fa-sign-out-alt me-1"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <!-- Search and Filters -->
    <div class="card mb-3">
        <div class="card-body search-filters">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">
                        <i class="fas fa-search me-1"></i>Search Users
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="Username or HWID..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-filter me-1"></i>Filter by App
                    </label>
                    <select name="app_filter" class="form-select">
                        <option value="">All Apps</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?= $app['id'] ?>" <?= ($app_filter == $app['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($app['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">
                        <i class="fas fa-user-check me-1"></i>Filter by Status
                    </label>
                    <select name="status_filter" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= ($status_filter === 'active') ? 'selected' : '' ?>>Active</option>
                        <option value="banned" <?= ($status_filter === 'banned') ? 'selected' : '' ?>>Banned</option>
                        <option value="expired" <?= ($status_filter === 'expired') ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end gap-1">
                    <button type="submit" class="btn btn-primary flex-fill">
                        <i class="fas fa-search me-1"></i>Filter
                    </button>
                    <a href="users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-user-plus me-2"></i>Add New User
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Expiration Date</label>
                    <input type="datetime-local" name="expires_at" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">App</label>
                    <select name="app_id" class="form-select" required>
                        <option value="">Select App</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?= $app['id'] ?>"><?= htmlspecialchars($app['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" name="add_user">
                        <i class="fas fa-plus me-1"></i>Add
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Count -->
    <div class="mb-3">
        <small class="text-muted">
            <i class="fas fa-info-circle me-1"></i>
            Showing <?= count($users) ?> user(s)
        </small>
    </div>

    <!-- Desktop Table View -->
    <div class="card table-view">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>App</th>
                            <th class="mobile-hide">HWID</th>
                            <th class="mobile-hide">Expires At</th>
                            <th class="mobile-hide">Created At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="fas fa-users-slash fa-2x mb-2"></i><br>
                                No users found matching your criteria
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong><?= $user['id'] ?></strong></td>
                                <td>
                                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    $appName = 'Unknown'; 
                                    foreach ($apps as $app) {
                                        if ($app['id'] == $user['app_id']) {
                                            $appName = htmlspecialchars($app['name']);
                                            break;
                                        }
                                    }
                                    echo '<span class="badge bg-secondary">' . $appName . '</span>';
                                    ?>
                                </td>
                                <td class="hwid-cell mobile-hide">
                                    <?= !empty($user['hwid']) ? '<code class="small">' . htmlspecialchars(substr($user['hwid'], 0, 20)) . '...</code>' : '<em class="text-muted">None</em>' ?>
                                </td>
                                <td class="mobile-hide">
                                    <?= $user['expires_at'] ? '<small>' . htmlspecialchars(date('M j, Y H:i', strtotime($user['expires_at']))) . '</small>' : '<em class="text-muted">Never</em>' ?>
                                </td>
                                <td class="mobile-hide">
                                    <small><?= htmlspecialchars(date('M j, Y', strtotime($user['created_at']))) ?></small>
                                </td>
                                <td>
                                    <?php 
                                    $isExpired = $user['expires_at'] && strtotime($user['expires_at']) < time();
                                    if (!empty($user['banned'])): ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-ban me-1"></i>Banned
                                        </span>
                                    <?php elseif ($isExpired): ?>
                                        <span class="badge bg-warning">
                                            <i class="fas fa-clock me-1"></i>Expired
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check me-1"></i>Active
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>

                                        <form method="POST" class="form-inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button class="btn btn-sm btn-danger" name="delete_user">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>

                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button class="btn btn-sm <?= (!empty($user['banned'])) ? 'btn-success' : 'btn-info' ?>" name="toggle_ban">
                                                <i class="fas fa-<?= (!empty($user['banned'])) ? 'unlock' : 'ban' ?>"></i>
                                            </button>
                                        </form>

                                        <form method="POST" class="form-inline" onsubmit="return confirm('Reset HWID for user <?= htmlspecialchars($user['username']) ?>?');">
                                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                            <button class="btn btn-sm btn-secondary" name="reset_hwid">
                                                <i class="fas fa-redo"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mobile Card View -->
    <?php foreach ($users as $user): ?>
        <div class="user-card">
            <div class="user-card-header">
                <div>
                    <strong><?= htmlspecialchars($user['username']) ?></strong>
                    <small class="text-muted">#<?= $user['id'] ?></small>
                </div>
                <div>
                    <?php 
                    $isExpired = $user['expires_at'] && strtotime($user['expires_at']) < time();
                    if (!empty($user['banned'])): ?>
                        <span class="badge bg-danger">Banned</span>
                    <?php elseif ($isExpired): ?>
                        <span class="badge bg-warning">Expired</span>
                    <?php else: ?>
                        <span class="badge bg-success">Active</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="user-card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <small class="text-muted">App:</small><br>
                        <span class="badge bg-secondary">
                            <?php 
                            foreach ($apps as $app) {
                                if ($app['id'] == $user['app_id']) {
                                    echo htmlspecialchars($app['name']);
                                    break;
                                }
                            }
                            ?>
                        </span>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Created:</small><br>
                        <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">HWID:</small><br>
                        <code class="small"><?= !empty($user['hwid']) ? htmlspecialchars(substr($user['hwid'], 0, 30)) . '...' : 'None' ?></code>
                    </div>
                    <div class="col-12">
                        <small class="text-muted">Expires:</small><br>
                        <small><?= $user['expires_at'] ? date('M j, Y H:i', strtotime($user['expires_at'])) : 'Never' ?></small>
                    </div>
                </div>
            </div>
            
            <div class="user-card-actions">
                <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">
                    <i class="fas fa-edit me-1"></i>Edit User
                </button>
                
                <div class="row g-1">
                    <div class="col-4">
                        <form method="POST" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-danger btn-sm w-100" name="delete_user">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-4">
                        <form method="POST">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-<?= (!empty($user['banned'])) ? 'success' : 'info' ?> btn-sm w-100" name="toggle_ban">
                                <i class="fas fa-<?= (!empty($user['banned'])) ? 'unlock' : 'ban' ?>"></i>
                            </button>
                        </form>
                    </div>
                    <div class="col-4">
                        <form method="POST" onsubmit="return confirm('Reset HWID?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-secondary btn-sm w-100" name="reset_hwid">
                                <i class="fas fa-redo"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Modals -->
    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-user-edit me-2"></i>Edit User: <?= htmlspecialchars($user['username']) ?>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Password <small class="text-muted">(leave blank to keep current)</small></label>
                                <input type="password" name="password" class="form-control" placeholder="New password">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Expiration Date</label>
                                <input type="datetime-local" name="expires_at" class="form-control" 
                                    value="<?= $user['expires_at'] ? date('Y-m-d\TH:i', strtotime($user['expires_at'])) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">App</label>
                                <select name="app_id" class="form-select" required>
                                    <option value="">Select App</option>
                                    <?php foreach ($apps as $app): ?>
                                        <option value="<?= $app['id'] ?>" <?= ($user['app_id'] == $app['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($app['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">HWID <small class="text-muted">(read-only)</small></label>
                                <textarea class="form-control" rows="2" readonly><?= $user['hwid'] ?? 'No HWID set' ?></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" name="edit_user">
                                <i class="fas fa-save me-1"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto-submit search form on input (with debounce)
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        // Auto-submit could be annoying, so we'll skip this
        // this.form.submit();
    }, 500);
});

// Confirm delete actions
document.querySelectorAll('form[onsubmit*="confirm"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        const confirmMsg = this.getAttribute('onsubmit').match(/confirm\('([^']+)'\)/);
        if (confirmMsg && !confirm(confirmMsg[1])) {
            e.preventDefault();
        }
    });
});

// Mobile-friendly tooltips
if (window.innerWidth <= 768) {
    // Add tooltips for mobile action buttons
    document.querySelectorAll('.action-buttons .btn').forEach(btn => {
        const icon = btn.querySelector('i');
        if (icon) {
            let title = '';
            if (icon.classList.contains('fa-edit')) title = 'Edit';
            else if (icon.classList.contains('fa-trash')) title = 'Delete';
            else if (icon.classList.contains('fa-ban')) title = 'Ban';
            else if (icon.classList.contains('fa-unlock')) title = 'Unban';
            else if (icon.classList.contains('fa-redo')) title = 'Reset HWID';
            
            if (title) btn.setAttribute('title', title);
        }
    });
}

// Enhanced search functionality
function highlightSearchTerm(text, searchTerm) {
    if (!searchTerm) return text;
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<mark>$1</mark>');
}

// Add keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Ctrl/Cmd + K to focus search
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('input[name="search"]').focus();
    }
    
    // Escape to clear search
    if (e.key === 'Escape' && document.activeElement.name === 'search') {
        document.querySelector('input[name="search"]').value = '';
    }
});

// Add loading state to filter form
document.querySelector('form[method="GET"]').addEventListener('submit', function() {
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Loading...';
});

// Auto-focus search input if there are no results
<?php if (empty($users) && !empty($search)): ?>
setTimeout(() => {
    document.querySelector('input[name="search"]').focus();
    document.querySelector('input[name="search"]').select();
}, 100);
<?php endif; ?>
</script>
</body>
</html>