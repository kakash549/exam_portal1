<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/exam_portal1/assets/css/style.css">
</head>
<body>
    <header>
        <div class="header-container">
            <h1>Exam Portal</h1>
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <nav>
                    <a href="admin/register_admin.php">Register New Admin</a>
                </nav>
            <?php endif; ?>
        </div>
    </header>