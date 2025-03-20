<?php
session_start();
include '../includes/db.php';
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $sql = "DELETE FROM users WHERE id = $id AND role = 'user'";
    $conn->query($sql);
}
$sql = "SELECT * FROM users WHERE role = 'user'";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Manage Users</h2>
        <table border="1">
            <tr><th>ID</th><th>Username</th><th>Action</th></tr>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td><a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Are you sure?')">Delete</a></td>
                </tr>
            <?php } ?>
        </table>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>
