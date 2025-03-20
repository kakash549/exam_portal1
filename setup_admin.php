<?php
session_start();
include 'includes/db.php';

// Check if an admin already exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_row();
$admin_count = $row[0];
$stmt->close();

if ($admin_count > 0) {
    // If an admin already exists, redirect to index.php
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? filter_var($_POST['username'], FILTER_SANITIZE_STRING) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $role = 'admin'; // Force the role to admin for this script

    // Basic validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (strlen($username) < 3) {
        $error = "Username must be at least 3 characters long.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!preg_match("/[A-Z]/", $password) || !preg_match("/[0-9]/", $password)) {
        $error = "Password must contain at least one uppercase letter and one number.";
    } else {
        // Hash the password
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);

        // Insert the admin into the database
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssss", $username, $email, $password_hashed, $role);
            if ($stmt->execute()) {
                $success = "First admin registered successfully! Please <a href='index.php'>log in</a>. Delete or disable this setup script (setup_admin.php) to prevent further admin creation.";
            } else {
                if ($conn->errno === 1062) {
                    $error = "Username or email already exists. Please choose a different one.";
                } else {
                    $error = "Error: " . $stmt->error;
                }
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
    <title>Setup First Admin - Exam Portal</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container">
        <h2>Setup First Admin</h2>
        <p>This page allows you to create the first admin account. Once created, please delete this script (setup_admin.php) to prevent unauthorized admin creation.</p>
        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div style="color: green; margin-bottom: 15px;"><?php echo $success; ?></div>
        <?php endif; ?>
        <?php if (empty($success)): ?>
            <form method="POST">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" placeholder="Enter username" value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="Enter email" value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>" required>

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="Enter password" required>

                <button type="submit">Register First Admin</button>
            </form>
        <?php endif; ?>
        <p><a href="index.php">Back to Login</a></p>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>