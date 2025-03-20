<?php
// Start session at the top
session_start();

// Include database connection
include '../includes/db.php';

// Redirect if not logged in as admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize exam title
    $title = mysqli_real_escape_string($conn, filter_var($_POST['title'], FILTER_SANITIZE_STRING));
    if (empty($title)) {
        die("Error: Exam title is required.");
    }

    // Generate unique exam link
    $link = uniqid('exam_');
    $created_by = (int) $_SESSION['user_id'];

    // Validate session user_id
    if (!$created_by) {
        die("Error: Invalid user ID. Please log in again.");
    }

    // Insert exam into exams table
    $sql = "INSERT INTO exams (title, link, created_by) VALUES ('$title', '$link', $created_by)";
    if (!mysqli_query($conn, $sql)) {
        die("Error inserting exam: " . mysqli_error($conn));
    }
    $exam_id = mysqli_insert_id($conn);

    // Assign the exam to all users with role 'user'
    $sql = "SELECT id FROM users WHERE role = 'user'";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($user = mysqli_fetch_assoc($result)) {
            $user_id = (int) $user['id'];
            $sql = "INSERT INTO user_exams (user_id, exam_id, status) VALUES ($user_id, $exam_id, 'pending')";
            if (!mysqli_query($conn, $sql)) {
                die("Error assigning exam to user: " . mysqli_error($conn));
            }
        }
    }

    // Insert questions
    $question_texts = $_POST['question_text'] ?? [];
    $question_types = $_POST['question_type'] ?? [];
    $options = $_POST['options'] ?? [];
    $correct_answers = $_POST['correct_answer'] ?? [];
    $max_scores = $_POST['max_score'] ?? [];

    if (empty($question_texts)) {
        die("Error: At least one question is required.");
    }

    $max_length = max(count($question_texts), count($question_types), count($options), count($correct_answers), count($max_scores));
    if (count($question_texts) !== $max_length || count($question_types) !== $max_length) {
        die("Error: Inconsistent number of questions and types.");
    }

    for ($i = 0; $i < count($question_texts); $i++) {
        if (!empty($question_texts[$i])) {
            $q_type = mysqli_real_escape_string($conn, $question_types[$i]);
            $q_text = mysqli_real_escape_string($conn, filter_var($question_texts[$i], FILTER_SANITIZE_STRING));
            $q_answer = isset($correct_answers[$i]) ? mysqli_real_escape_string($conn, filter_var($correct_answers[$i], FILTER_SANITIZE_STRING)) : '';
            $q_score = isset($max_scores[$i]) ? (int) $max_scores[$i] : 1;

            if ($q_score < 1) {
                $q_score = 1;
            }

            $q_options = 'NULL';
            if ($q_type === 'multiple_choice' || $q_type === 'true_false') {
                if (isset($options[$i]) && !empty(trim($options[$i]))) {
                    $options_array = explode(',', trim($options[$i]));
                    $options_array = array_map('trim', $options_array);
                    if ($q_type === 'true_false') {
                        $expected_options = ['True', 'False'];
                        if (count(array_diff($options_array, $expected_options)) !== 0 || count($options_array) !== 2) {
                            die("Error: For True/False, options must be 'True, False'.");
                        }
                        if (!in_array($q_answer, ['True', 'False'])) {
                            die("Error: Correct answer for True/False must be 'True' or 'False'.");
                        }
                    }
                    $q_options = "'" . mysqli_real_escape_string($conn, json_encode($options_array)) . "'";
                }
            } elseif ($q_type === 'fill_in_the_blank' || $q_type === 'short_answer' || $q_type === 'essay') {
                $q_options = 'NULL';
                if ($q_type === 'essay' && !empty($q_answer)) {
                    // Optional: Allow essay answers to be stored
                }
            }

            $sql = "INSERT INTO questions (exam_id, question_type, question_text, options, correct_answer, max_score) VALUES (
                $exam_id, '$q_type', '$q_text', $q_options, '$q_answer', $q_score
            )";
            if (!mysqli_query($conn, $sql)) {
                die("Error inserting question: " . mysqli_error($conn));
            }
        }
    }

    // Updated success message to point to test_exam.php
    echo "Exam created successfully! Link: <a href='../user/test_exam.php?link=$link&exam_id=$exam_id' target='_blank'>$link</a>";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Exam</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script>
        function addQuestion() {
            // Fix: Correct the ID to match the HTML (lowercase)
            const container = document.getElementById('questions-container');
            if (!container) {
                console.error("Questions container not found!");
                return;
            }
            const questionDiv = document.createElement('div');
            questionDiv.className = 'question-entry';
            questionDiv.innerHTML = `
                <label>Question Text:</label>
                <input type="text" name="question_text[]" required>
                <label>Question Type:</label>
                <select name="question_type[]" onchange="toggleOptions(this)">
                    <option value="multiple_choice">Multiple Choice</option>
                    <option value="true_false">True/False</option>
                    <option value="fill_in_the_blank">Fill in the Blank</option>
                    <option value="short_answer">Short Answer</option>
                    <option value="essay">Essay</option>
                </select>
                <div class="options" style="display: none;">
                    <label>Options (comma-separated):</label>
                    <input type="text" name="options[]" placeholder="e.g., A, B, C, D for Multiple Choice; True, False for True/False">
                </div>
                <label>Correct Answer:</label>
                <input type="text" name="correct_answer[]" required placeholder="e.g., A or True/False">
                <label>Max Score:</label>
                <input type="number" name="max_score[]" value="1" min="1">
                <button type="button" onclick="this.parentElement.remove()">Remove</button>
                <hr>
            `;
            container.appendChild(questionDiv);

            // Call toggleOptions for the newly added select element
            const newSelect = questionDiv.querySelector('select[name="question_type[]"]');
            toggleOptions(newSelect);
        }

        function toggleOptions(select) {
            const optionsDiv = select.parentElement.querySelector('.options');
            const isMultipleChoiceOrTrueFalse = select.value === 'multiple_choice' || select.value === 'true_false';
            optionsDiv.style.display = isMultipleChoiceOrTrueFalse ? 'block' : 'none';

            if (select.value === 'true_false') {
                const optionsInput = optionsDiv.querySelector('input[name="options[]"]');
                if (optionsInput.value.trim() === '') {
                    optionsInput.value = 'True, False';
                }
                const correctAnswerInput = select.parentElement.querySelector('input[name="correct_answer[]"]');
                correctAnswerInput.setAttribute('pattern', '^(True|False)$');
                correctAnswerInput.setAttribute('title', 'Must be "True" or "False"');
            } else {
                const correctAnswerInput = select.parentElement.querySelector('input[name="correct_answer[]"]');
                correctAnswerInput.removeAttribute('pattern');
                correctAnswerInput.removeAttribute('title');
            }
        }

        // Remove this line since there are no select elements initially
        // document.querySelectorAll('select[name="question_type[]"]').forEach(select => toggleOptions(select));
    </script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container">
        <h2>Create Exam</h2>
        <form method="POST">
            <label>Exam Title:</label>
            <input type="text" name="title" placeholder="Exam Title" required>
            <div id="questions-container">
                <!-- Questions will be added here dynamically -->
            </div>
            <button type="button" onclick="addQuestion()">Add Question</button>
            <button type="submit">Create Exam</button>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>