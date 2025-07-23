<?php
require_once 'functions.php';
requireLogin();
require_once 'db.php';

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

        if ($username === '' || $password === '') {
            $error = "Username and password are required.";
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

                $stmt = $mysqli->prepare("INSERT INTO users (username, password, expires_at, banned) VALUES (?, ?, ?, 0)");
                if ($stmt) {
                    $stmt->bind_param("sss", $username, $hashed_password, $expires_at);
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

        if ($username === '') {
            $error = "Username is required.";
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
                    $stmt = $mysqli->prepare("UPDATE users SET username = ?, password = ?, expires_at = ? WHERE id = ?");
                    $stmt->bind_param("sssi", $username, $hashed_password, $expires_at, $id);
                } else {
                    // Update without password (keep old)
                    $stmt = $mysqli->prepare("UPDATE users SET username = ?, expires_at = ? WHERE id = ?");
                    $stmt->bind_param("ssi", $username, $expires_at, $id);
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

// Fetch all users
$userRes = $mysqli->query("SELECT * FROM users ORDER BY id DESC");
$users = $userRes ? $userRes->fetch_all(MYSQLI_ASSOC) : [];

?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management - KeyAuth Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        form.inline-form {
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
        }
    </style>
</head>
<body class="bg-light">
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>User Management</h2>
        <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Add User -->
    <div class="card p-3 mb-4">
        <h5>Add New User</h5>
        <form method="POST" class="row g-2 align-items-center">
            <div class="col-md-2">
                <input type="text" name="username" class="form-control" placeholder="Username" required>
            </div>
            <div class="col-md-2">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="col-md-3">
                <input type="datetime-local" name="expires_at" class="form-control" placeholder="Expiration Date (optional)">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" name="add_user">Add User</button>
            </div>
        </form>
    </div>

    <!-- Users Table -->
    <div class="card p-3 mb-4">
        <h5>Users</h5>
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>HWID</th>
                    <th>Expires At</th>
                    <th>Created At</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td class="hwid-cell"><?= !empty($user['hwid']) ? htmlspecialchars($user['hwid']) : '<em>None</em>' ?></td>
                    <td><?= $user['expires_at'] ? htmlspecialchars($user['expires_at']) : '<em>Never</em>' ?></td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td>
                        <?php if (!empty($user['banned'])): ?>
                            <span class="badge bg-danger">Banned</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Edit button triggers a modal -->
                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $user['id'] ?>">Edit</button>

                        <!-- Delete form -->
                        <form method="POST" class="inline-form" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-danger" name="delete_user">Delete</button>
                        </form>

                        <!-- Toggle Ban form -->
                        <form method="POST" class="inline-form" style="margin-left:4px;">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-info" name="toggle_ban">
                                <?= $user['banned'] ? 'Unban' : 'Ban' ?>
                            </button>
                        </form>

                        <!-- Reset HWID form -->
                        <form method="POST" class="inline-form" style="margin-left:4px;" onsubmit="return confirm('Reset HWID for user <?= htmlspecialchars($user['username']) ?>?');">
                            <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                            <button class="btn btn-sm btn-secondary" name="reset_hwid">Reset HWID</button>
                        </form>

                        <!-- Edit Modal -->
                        <div class="modal fade" id="editUserModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?= $user['id'] ?>" aria-hidden="true">
                          <div class="modal-dialog">
                            <div class="modal-content">
                              <form method="POST">
                              <div class="modal-header">
                                <h5 class="modal-title" id="editUserModalLabel<?= $user['id'] ?>">Edit User: <?= htmlspecialchars($user['username']) ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                              </div>
                              <div class="modal-body">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Username</label>
                                        <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password <small>(leave blank to keep current)</small></label>
                                        <input type="password" name="password" class="form-control" placeholder="New password">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Expiration Date</label>
                                        <input type="datetime-local" name="expires_at" class="form-control" 
                                            value="<?= $user['expires_at'] ? date('Y-m-d\TH:i', strtotime($user['expires_at'])) : '' ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">HWID <small>(read-only)</small></label>
                                        <input type="text" class="form-control" value="<?= $user['hwid'] ?? '' ?>" readonly>
                                    </div>
                              </div>
                              <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary" name="edit_user">Save Changes</button>
                              </div>
                              </form>
                            </div>
                          </div>
                        </div>

                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
