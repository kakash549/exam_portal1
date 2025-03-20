<?php
session_start();
include '../includes/db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? filter_var($_POST['username'], FILTER_SANITIZE_STRING) : '';
    $email = isset($_POST['email']) ? filter_var($_POST['email'], FILTER_SANITIZE_EMAIL) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

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
        $password_hashed = password_hash($password, PASSWORD_DEFAULT);
        $role = 'admin';

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        if ($stmt === false) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("ssss", $username, $email, $password_hashed, $role);
            if ($stmt->execute()) {
                $success = "Admin registered successfully! <a href='register_admin.php'>Register another admin</a> or <a href='dashboard.php'>go to dashboard</a>.";
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
    <title>Register Admin - Exam Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Register Admin</h2>
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

                <button type="submit">Register Admin</button>
            </form>
        <?php endif; ?>
        <p><a href="dashboard.php">Back to Dashboard</a></p>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>