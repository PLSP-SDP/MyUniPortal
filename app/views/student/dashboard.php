<?php
// First check if the requested page exists
include "../../main_proc.php";

// Check for Profile-related actions
if (isset($_GET['page']) && $_GET['page'] == 'profile' && isset($_GET['action'])) {
    $action = $_GET['action'];
    // Handle all profile-related actions before any HTML output
    if (in_array($action, ['update_profile', 'change_password'])) {
        $response = process_Student_Profile_Request($pdo);
        // Store response in session for notification display
        $_SESSION['profile_response'] = $response;
        // Redirect back to the profile page
        header('Location: dashboard.php?page=profile');
        exit;
    }
}

// Handle Profile POST actions (form submissions)
if (isset($_GET['page']) && $_GET['page'] == 'profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    error_log("Dashboard: Processing Profile POST action '$action' with POST data: " . json_encode($_POST));
    
    if (in_array($action, ['update_profile', 'change_password'])) {
        error_log("Dashboard: Calling process_Student_Profile_Request for POST action '$action'");
        $response = process_Student_Profile_Request($pdo);
        // Store response in session for notification display
        $_SESSION['profile_response'] = $response;
        // Redirect back to the profile page
        header('Location: dashboard.php?page=profile');
        exit;
    }
}

// Check for Course-related actions
if (isset($_GET['page']) && $_GET['page'] == 'courses' && isset($_GET['action'])) {
    $action = $_GET['action'];
    // Handle all course-related actions before any HTML output
    if (in_array($action, ['enroll', 'drop', 'view_details'])) {
        $response = process_Student_Course_Request($pdo);
        if ($action != 'view_details') {
            // Store response in session for notification display
            $_SESSION['course_response'] = $response;
            // Redirect back to the courses page
            header('Location: dashboard.php?page=courses');
            exit;
        }
        // For view_details action, the function will send JSON and exit
    }
}

// Handle Course POST actions (form submissions)
if (isset($_GET['page']) && $_GET['page'] == 'courses' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
    error_log("Dashboard: Processing Course POST action '$action' with POST data: " . json_encode($_POST));
    
    if (in_array($action, ['enroll', 'drop'])) {
        error_log("Dashboard: Calling process_Student_Course_Request for POST action '$action'");
        $response = process_Student_Course_Request($pdo);
        // Store response in session for notification display
        $_SESSION['course_response'] = $response;
        // Redirect back to the courses page
        header('Location: dashboard.php?page=courses');
        exit;
    }
}

// Check for Grade-related actions (both GET and POST)
if (isset($_GET['page']) && $_GET['page'] == 'grades') {
    // Handle GET actions (AJAX requests)
    if (isset($_GET['action'])) {
        $action = $_GET['action'];
        error_log("Dashboard: Processing Grade GET action '$action' with parameters: " . json_encode($_GET));
        
        // Handle all grade-related actions before any HTML output
        if (in_array($action, ['get_grade_details', 'download_transcript'])) {
            error_log("Dashboard: Calling process_Student_Grade_Request for action '$action'");
            // These actions return JSON and exit within the function
            $response = process_Student_Grade_Request($pdo);
        } else {
            error_log("Dashboard: Processing non-AJAX Grade GET action '$action'");
            $response = process_Student_Grade_Request($pdo);
            // Store response in session for notification display
            $_SESSION['grade_response'] = $response;
            // Redirect back to the grades page
            header('Location: dashboard.php?page=grades');
            exit;
        }
    }
    
    // Handle POST actions (form submissions)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
        $action = $_GET['action'];
        error_log("Dashboard: Processing Grade POST action '$action' with POST data: " . json_encode($_POST));
        
        if (in_array($action, ['request_transcript'])) {
            error_log("Dashboard: Calling process_Student_Grade_Request for POST action '$action'");
            $response = process_Student_Grade_Request($pdo);
            // Store response in session for notification display
            $_SESSION['grade_response'] = $response;
            // Redirect back to the grades page
            header('Location: dashboard.php?page=grades');
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
    
    // List of valid pages for students
    $validPages = ['home', 'profile', 'courses', 'grades'];

    // Check if the page is valid
    if (!in_array($page, $validPages)) {
        $file = "../err/404.php"; // Custom 404 page if invalid
    } else {
        $file = "./pages/{$page}.php";
        
        // Check if the file exists
        if (!file_exists($file)) {
            $file = "../err/404.php"; // Show custom 404 page if file not found
        }
    }
    
    // Set variables needed for the navbar
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