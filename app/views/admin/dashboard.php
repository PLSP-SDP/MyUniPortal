<?php
// First check if the requested page exists
include "../../main_proc.php";

// Check for Student-related actions
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Student' && isset($_GET['action'])) {
    $action = $_GET['action'];
    // Handle all student-related actions before any HTML output
    if (in_array($action, ['add', 'update', 'delete', 'reset_password', 'view'])) {
        $response = process_Student_Request($pdo);
        if ($action != 'view') {
            // Store response in session for notification display
            $_SESSION['student_response'] = $response;
            // Redirect back to the Student page
            header('Location: dashboard.php?page=Manage&subpage=Student');
            exit;
        }
        // For view action, the function will send JSON and exit
    }
}

// Handle Student POST actions (form submissions)
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Student' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    error_log("Dashboard: Processing Student POST action '$action' with POST data: " . json_encode($_POST));
    
    if (in_array($action, ['add', 'update'])) {
        error_log("Dashboard: Calling process_Student_Request for POST action '$action'");
        $response = process_Student_Request($pdo);
        // Store response in session for notification display
        $_SESSION['student_response'] = $response;
        // Redirect back to the Student page
        header('Location: dashboard.php?page=Manage&subpage=Student');
        exit;
    }
}

// Check for Professor/Staff-related actions
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Prof' && isset($_GET['action'])) {
    $action = $_GET['action'];
    // Handle all professor-related actions before any HTML output
    if (in_array($action, ['add', 'edit', 'delete', 'get'])) {
        $response = process_Professor_Request($pdo);
        if ($action != 'get') {
            // Store response in session for notification display
            $_SESSION['professor_response'] = $response;
            // Redirect back to the Prof page
            header('Location: dashboard.php?page=Manage&subpage=Prof');
            exit;
        }
        // For get action, the function will send JSON and exit
    }
}

// Handle Professor/Staff POST actions (form submissions)
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Prof' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    error_log("Dashboard: Processing Professor POST action '$action' with POST data: " . json_encode($_POST));
    
    if (in_array($action, ['add', 'edit'])) {
        error_log("Dashboard: Calling process_Professor_Request for POST action '$action'");
        $response = process_Professor_Request($pdo);
        // Store response in session for notification display
        $_SESSION['professor_response'] = $response;
        // Redirect back to the Prof page
        header('Location: dashboard.php?page=Manage&subpage=Prof');
        exit;
    }
}

// Check for Class-related actions first - we need to handle these before any HTML is output
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Class' && isset($_GET['action'])) {
    $action = $_GET['action'];
    // Handle all class-related actions before any HTML output
    if (in_array($action, ['add_class', 'edit_class', 'delete_class', 'get_class'])) {
        $response = process_Class_Request($pdo);
        if ($action != 'get_class') {
            // Store response in session for notification display
            $_SESSION['class_response'] = $response;
            // Redirect back to the Class page
            header('Location: dashboard.php?page=Manage&subpage=Class');
            exit;
        }
        // For get_class action, the function will send JSON and exit
    }
}

// Check for Grade-related actions (both GET and POST)
if (isset($_GET['subpage']) && $_GET['subpage'] == 'Grade') {
    // Handle GET actions (AJAX requests)
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        error_log("Dashboard: Processing Grade GET action '$action' with parameters: " . json_encode($_GET));
        
        // Handle all grade-related actions before any HTML output
        if (in_array($action, ['get_available_classes', 'get_grade_details'])) {
            error_log("Dashboard: Calling process_Grade_Request for action '$action'");
            // These actions return JSON and exit within the function
            $response = process_Grade_Request($pdo);
        } else {
            error_log("Dashboard: Processing non-AJAX Grade GET action '$action'");
            $response = process_Grade_Request($pdo);
            // Store response in session for notification display
            $_SESSION['grade_response'] = $response;
            // Redirect back to the Grade page
            header('Location: dashboard.php?page=Manage&subpage=Grade');
            exit;
        }
    }
    
    // Handle POST actions (form submissions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
        $action = $_GET['action'];
        error_log("Dashboard: Processing Grade POST action '$action' with POST data: " . json_encode($_POST));
        
        if (in_array($action, ['add_grade', 'edit_grade', 'delete_grade'])) {
            error_log("Dashboard: Calling process_Grade_Request for POST action '$action'");
            $response = process_Grade_Request($pdo);
            // Store response in session for notification display
            $_SESSION['grade_response'] = $response;
            // Redirect back to the Grade page
            header('Location: dashboard.php?page=Manage&subpage=Grade');
            exit;
        }
    }
}

// Check if the user is logged in
if (!isLoggedIn()) {
    header("Location: ../../../index.php");
    exit();
} else {
    // Determine the page from the URL, defaulting to 'home'
    $page = isset($_GET['page']) ? $_GET['page'] : 'home';
    
    // List of valid pages
    $validPages = ['home', 'Manage'];

    // Check if the page is valid
    if (!in_array($page, $validPages)) {
        $file = "../err/404.php"; // Custom 404 page if invalid
    } else {
        $file = "./home/{$page}.php";
        
        // Check if the file exists
        if (!file_exists($file)) {
            $file = "../err/404.php"; // Show custom 404 page if file not found
        }
    }
    $currentPage = basename($_SERVER['PHP_SELF']);

    $userID = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    $userDetails = getUserDetails($userID, $role);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PLSP - MyUniPortal</title>
    <link rel="stylesheet" href="../../../src/css/styles.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
</head>

<body>
    <?php include "../modules/nav.php"; ?>


    <div class="container-fluid pt-5 px-3">
        <div class="pt-5"></div>
        <?php
        if (file_exists($file)) {
            include $file;
        }
        ?>
    </div>
    <?php include "../modules/footer.php"; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
        crossorigin="anonymous"></script>
    <script src="../../../src/js/app.js"></script>
</body>

</html>