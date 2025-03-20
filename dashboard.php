<?php
session_start();
include '../includes/db.php';

// Redirect if not logged in as a user
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch assigned (pending) exams
$stmt = $conn->prepare("SELECT e.title, e.link FROM exams e JOIN user_exams ue ON e.id = ue.exam_id WHERE ue.user_id = ? AND ue.status = 'pending'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$assigned_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch submitted exams from exam_results
$stmt = $conn->prepare("SELECT e.title, r.total_score, r.submitted_at, r.status FROM exam_results r JOIN exams e ON r.exam_id = e.id WHERE r.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$submitted_exams = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Exam History - Exam Portal</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Your Exam History</h2>

        <?php if (empty($assigned_exams) && empty($submitted_exams)): ?>
            <p>No exam history available.</p>
        <?php else: ?>
            <?php if (!empty($assigned_exams)): ?>
                <h3>Pending Exams</h3>
                <?php foreach ($assigned_exams as $exam): ?>
                    <p>
                        <?php echo htmlspecialchars($exam['title']); ?> - 
                        <a href="start_exam.php?link=<?php echo htmlspecialchars($exam['link']); ?>">Start Exam</a>
                    </p>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($submitted_exams)): ?>
                <h3>Submitted Exams</h3>
                <table border="1">
                    <tr>
                        <th>Exam Title</th>
                        <th>Score</th>
                        <th>Submitted At</th>
                        <th>Status</th>
                    </tr>
                    <?php foreach ($submitted_exams as $exam): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                            <td>
                                <?php 
                                echo htmlspecialchars($exam['total_score'] ?? 'N/A'); 
                                if (isset($exam['status']) && $exam['status'] !== 'fully_graded') {
                                    echo ' (Pending final grading)';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($exam['submitted_at'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($exam['status'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>