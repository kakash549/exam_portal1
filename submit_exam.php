<?php
session_start();
include '../includes/db.php';

// Debug: Log the start of submit_exam.php
file_put_contents('submit_debug.log', "submit_exam.php accessed with user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n", FILE_APPEND);

if (!isset($_SESSION['user_id'])) {
    file_put_contents('submit_debug.log', "Unauthorized access\n", FILE_APPEND);
    echo "Unauthorized access.";
    exit;
}

$user_id = $_SESSION['user_id'];
$exam_id = isset($_POST['exam_id']) ? (int)$_POST['exam_id'] : 0;

// Debug: Log the received exam_id
file_put_contents('submit_debug.log', "Received exam_id: $exam_id\n", FILE_APPEND);

if ($exam_id <= 0) {
    file_put_contents('submit_debug.log', "Invalid exam ID: $exam_id\n", FILE_APPEND);
    echo "Invalid exam ID.";
    exit;
}

// Validate exam_id exists in the exams table
$sql = "SELECT * FROM exams WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$exam) {
    file_put_contents('submit_debug.log', "Exam not found for exam_id: $exam_id\n", FILE_APPEND);
    echo "Exam not found.";
    exit;
}

// Validate user is assigned to this exam
$sql = "SELECT * FROM user_exams WHERE user_id = ? AND exam_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $exam_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    file_put_contents('submit_debug.log', "User not authorized for exam_id: $exam_id\n", FILE_APPEND);
    echo "You are not authorized to submit this exam.";
    exit;
}
$stmt->close();

// Start transaction to ensure data consistency
$conn->begin_transaction();

try {
    // Insert into exam_results
    $stmt = $conn->prepare("INSERT INTO exam_results (user_id, exam_id, total_score, status) VALUES (?, ?, 0, 'submitted')");
    $stmt->bind_param("ii", $user_id, $exam_id);
    $stmt->execute();
    $exam_result_id = $conn->insert_id;
    $stmt->close();

    // Fetch questions
    $stmt = $conn->prepare("SELECT id, question_type, correct_answer, max_score FROM questions WHERE exam_id = ?");
    $stmt->bind_param("i", $exam_id);
    $stmt->execute();
    $questions = $stmt->get_result();
    $stmt->close();

    $total_auto_score = 0;
    $needs_manual_grading = false;

    // Insert into question_responses
    $stmt = $conn->prepare("INSERT INTO question_responses (exam_result_id, question_id, user_answer, auto_score, graded) VALUES (?, ?, ?, ?, ?)");
    while ($q = $questions->fetch_assoc()) {
        $question_id = $q['id'];
        $user_answer = $_POST["question_{$question_id}"] ?? ''; // Match the form field name in test_exam.php
        $auto_score = 0;
        $graded = false;

        // Debug: Log the user answer for this question
        file_put_contents('submit_debug.log', "Question ID: $question_id, User Answer: $user_answer, Correct Answer: " . $q['correct_answer'] . "\n", FILE_APPEND);

        switch ($q['question_type']) {
            case 'multiple_choice':
            case 'true_false':
            case 'fill_in_the_blank':
                if ($user_answer === $q['correct_answer']) {
                    $auto_score = $q['max_score'];
                    $total_auto_score += $auto_score;
                }
                $graded = true;
                break;
            case 'short_answer':
                if (strtolower(trim($user_answer)) === strtolower(trim($q['correct_answer']))) {
                    $auto_score = $q['max_score'];
                    $total_auto_score += $auto_score;
                    $graded = true;
                } else {
                    $needs_manual_grading = true; // Partial match or incorrect answer needs review
                }
                break;
            case 'essay':
                $needs_manual_grading = true; // Always requires manual grading
                break;
        }

        $stmt->bind_param("iisii", $exam_result_id, $question_id, $user_answer, $auto_score, $graded);
        $stmt->execute();
    }
    $stmt->close();

    // Update exam_results with total_auto_score and status
    $status = $needs_manual_grading ? 'submitted' : 'fully_graded';
    $stmt = $conn->prepare("UPDATE exam_results SET total_score = ?, status = ? WHERE id = ?");
    $stmt->bind_param("isi", $total_auto_score, $status, $exam_result_id);
    $stmt->execute();
    $stmt->close();

    // Update user_exams status to 'completed'
    $stmt = $conn->prepare("UPDATE user_exams SET status = 'completed' WHERE user_id = ? AND exam_id = ?");
    $stmt->bind_param("ii", $user_id, $exam_id);
    $stmt->execute();
    $stmt->close();

    // Commit transaction
    $conn->commit();

    // Debug: Log successful submission
    file_put_contents('submit_debug.log', "Exam submitted successfully for exam_id: $exam_id, score: $total_auto_score, status: $status\n", FILE_APPEND);

    // Return auto-graded score to the frontend
    echo "Exam submitted successfully! Your score: $total_auto_score" . ($needs_manual_grading ? " (Some answers require manual grading)" : "");
} catch (Exception $e) {
    // Roll back transaction on error
    $conn->rollback();
    file_put_contents('submit_debug.log', "Error submitting exam: " . $e->getMessage() . "\n", FILE_APPEND);
    echo "Error submitting exam: " . $e->getMessage();
}
?>