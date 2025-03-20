<?php
// Start session
session_start();

// Include database connection
include 'includes/db.php';

// Check if running in web context (not CLI)
if (php_sapi_name() === 'cli') {
    die("This script is intended to be run via a web server, not the CLI. Access it through your browser.");
}

// Initialize messages
$error = '';
$success = '';

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    $success = "Logged out successfully.";
    header('Location: index.php');
    exit; // Ensure script stops after redirect
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $username = isset($_POST['username']) ? filter_var($_POST['username'], FILTER_SANITIZE_STRING) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    // Basic validation
    if (empty($username) || empty($password)) {
        $error = "Username and password are required.";
    } else {
        // Use prepared statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password'])) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        var_dump($_SESSION); // Debug
                        $redirect = $user['role'] === 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php';
                        header('Location: ' . $redirect);
                        exit; // Ensure script stops after redirect
                    } else {
                        $error = "Invalid password.";
                    }
                } else {
                    $error = "Invalid username.";
                }
            } else {
                $error = "Error executing query: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Exam Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <?php if (isset($_SESSION['user_id'])): ?>
            <h2>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
            <p>You are already logged in.</p>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="admin/dashboard.php"><button>Go to Admin Dashboard</button></a>
                <a href="admin/register_admin.php"><button>Register New Admin</button></a>
            <?php else: ?>
                <a href="user/dashboard.php"><button>Go to Dashboard</button></a>
            <?php endif; ?>
            <a href="index.php?logout=1"><button>Logout</button></a>
        <?php else: ?>
            <h2>Login</h2>
            <?php if (!empty($success)): ?>
                <div style="color: green; margin-bottom: 15px;"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if (!empty($error)): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Enter username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>

                <button type="submit">Login</button>
            </form>
            <p>Not registered? <a href="user/register.php">Register here</a></p>
            <p>Admins: Log in to register new admin accounts.</p>
        <?php endif; ?>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>