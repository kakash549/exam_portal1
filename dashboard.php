<?php
// Start session
session_start();

// Include database connection
include '../includes/db.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Optional: Fetch username to display (already set in session by login)
$username = $_SESSION['username'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Exam Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
        <p>Admin Dashboard</p>
        <a href="create_exam.php"><button>Create New Exam</button></a>
        <a href="manage_users.php"><button>Manage Users</button></a>
        <a href="register_admin.php"><button>Register New Admin</button></a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>