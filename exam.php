<?php
session_start();

// Debug: Log the start of test_exam.php
file_put_contents('debug.log', "test_exam.php accessed with user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n", FILE_APPEND);

// Include database connection
include '../includes/db.php';

// Verify user session and exam access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    file_put_contents('debug.log', "User not logged in or not a user\n", FILE_APPEND);
    header('Location: ../index.php');
    exit;
}

$link = $_GET['link'] ?? '';
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Debug: Log the received parameters
file_put_contents('debug.log', "link: $link, exam_id: $exam_id\n", FILE_APPEND);

if (empty($link)) {
    file_put_contents('debug.log', "Invalid exam link\n", FILE_APPEND);
    die("Invalid exam link.");
}
if ($exam_id <= 0) {
    file_put_contents('debug.log', "Invalid exam ID\n", FILE_APPEND);
    die("Invalid exam ID.");
}

// Check if the user is assigned this exam
$sql = "SELECT * FROM user_exams WHERE user_id = ? AND exam_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $_SESSION['user_id'], $exam_id);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log the result of user_exams check
file_put_contents('debug.log', "user_exams rows: " . $result->num_rows . "\n", FILE_APPEND);

if ($result->num_rows == 0) {
    file_put_contents('debug.log', "User not authorized\n", FILE_APPEND);
    die("You are not authorized to take this exam.");
}
$stmt->close();

// Fetch exam and questions
$sql = "SELECT * FROM exams WHERE id = ? AND link = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $exam_id, $link);
$stmt->execute();
$exam = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Debug: Log the result of exams check
file_put_contents('debug.log', "Exam found: " . ($exam ? 'yes' : 'no') . "\n", FILE_APPEND);

if (!$exam) {
    file_put_contents('debug.log', "Exam not found\n", FILE_APPEND);
    die("Exam not found.");
}

$sql = "SELECT * FROM questions WHERE exam_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $exam_id);
$stmt->execute();
$questions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Debug: Log the number of questions
file_put_contents('debug.log', "Questions found: " . count($questions) . "\n", FILE_APPEND);
?>

<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($exam['title']); ?> - Test Environment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background-color: #e0f7fa; padding: 20px; }
        .question { margin-bottom: 20px; }
        .options { margin-left: 20px; }
        .options input { margin-right: 10px; }
        #timer { font-size: 1.2em; color: red; }
        .proctoring-message { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border: 2px solid #ff4444; z-index: 1000; }
        .pagination { text-align: center; margin: 20px 0; }
        .pagination span { display: inline-block; width: 10px; height: 10px; background: #ccc; border-radius: 50%; margin: 0 5px; }
        .pagination span.active { background: #000; }
        #webcam { position: fixed; bottom: 10px; right: 10px; width: 200px; height: 150px; }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/face-api.js/0.22.2/face-api.min.js"></script>
    <script>
        window.currentUserId = <?php echo json_encode($_SESSION['user_id']); ?>;
    </script>
    <script src="../assets/js/proctoring.js"></script>
    <script>
        let timeLeft = 3600; // 1 hour in seconds (adjust as needed)
        let timerInterval;
        let violationCount = 0;
        const maxViolations = 3; // Max allowed violations before termination

        function startTimer() {
            timerInterval = setInterval(() => {
                let minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;
                document.getElementById('timer').textContent = `Time Left: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    alert("Time's up! Submitting exam...");
                    submitExam();
                }
                timeLeft--;
            }, 1000);
        }

        function logViolation(violationType) {
            violationCount++;
            fetch('log_violation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `user_id=<?php echo $_SESSION['user_id']; ?>&exam_id=<?php echo $exam_id; ?>&violation_type=${encodeURIComponent(violationType)}`
            });
            showProctoringMessage(`${violationType.replace('_', ' ')} detected! This violation has been logged.`);
            if (violationCount >= maxViolations) {
                alert("Too many violations! Exam terminated.");
                submitExam();
            }
        }

        // Continuous proctoring
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                logViolation('tab_switch');
            }
        });

        window.onblur = () => logViolation('tab_switch');
        window.onfocus = () => console.log('Focus regained');

        // Prevent right-click and keyboard shortcuts
        document.addEventListener('contextmenu', (e) => e.preventDefault());
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.altKey || e.key === 'F12') {
                e.preventDefault();
                logViolation('keyboard_shortcut');
            }
        });

        window.onload = startTimer;

        function submitExam() {
            const formData = new FormData(document.getElementById('examForm'));
            fetch('submit_exam.php', {
                method: 'POST',
                body: formData
            }).then(response => response.text()).then(text => {
                alert(text);
                window.close();
            });
        }
    </script>
</head>
<body>
    <h2><?php echo htmlspecialchars($exam['title']); ?></h2>
    <div id="timer">Time Left: 60:00</div>
    <form id="examForm" method="POST" action="submit_exam.php">
        <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
        <?php foreach ($questions as $index => $question): ?>
            <div class="question">
                <p><?php echo htmlspecialchars($question['question_text']) . " (" . $question['max_score'] . " points)"; ?></p>
                <?php
                if ($question['question_type'] === 'true_false' || $question['question_type'] === 'multiple_choice') {
                    $options = json_decode($question['options'], true);
                    if (is_array($options)) {
                        foreach ($options as $option) {
                            echo "<div class='options'><input type='radio' name='question_{$question['id']}' value='" . htmlspecialchars($option) . "' required> " . htmlspecialchars($option) . "</div>";
                        }
                    } else {
                        echo "<p>Error: Options not available for this question.</p>";
                    }
                } elseif ($question['question_type'] === 'fill_in_the_blank' || $question['question_type'] === 'short_answer') {
                    echo "<input type='text' name='question_{$question['id']}' required placeholder='Your answer'>";
                } elseif ($question['question_type'] === 'essay') {
                    echo "<textarea name='question_{$question['id']}' rows='4' required placeholder='Your answer'></textarea>";
                }
                ?>
            </div>
        <?php endforeach; ?>
        <div class="pagination">
            <?php for ($i = 0; $i < count($questions); $i++): ?>
                <span class="<?php echo $i === 0 ? 'active' : ''; ?>"></span>
            <?php endfor; ?>
        </div>
        <button type="button" onclick="submitExam()">Submit Exam</button>
    </form>
    <video id="webcam" autoplay muted></video>
</body>
</html>