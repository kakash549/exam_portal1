<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as a user
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit;
}

$link = $_GET['link'] ?? '';
if (empty($link)) {
    die("Invalid exam link.");
}

// Validate exam link and check if assigned to user
$stmt = $conn->prepare("SELECT e.id FROM exams e JOIN user_exams ue ON e.id = ue.exam_id WHERE e.link = ? AND ue.user_id = ? AND ue.status = 'pending'");
$stmt->bind_param("si", $link, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Invalid or unassigned exam link.");
}

$exam = $result->fetch_assoc();
$exam_id = $exam['id'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Exam - Exam Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2><?php echo htmlspecialchars($link); ?></h2>
        <p>Click below to start the exam. Ensure your webcam and microphone are enabled.</p>
        <a href="exam.php?link=<?php echo htmlspecialchars($link); ?>&exam_id=<?php echo htmlspecialchars($exam_id); ?>"><button>Start Exam</button></a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>