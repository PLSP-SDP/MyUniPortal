<?php
// Include student home backend functions
require_once '../../Controllers/student-page/home.backend.php';

// Get student ID from session
$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;

if ($user_id) {
    // Convert user_id to student_id
    $student_id = getStudentIdFromUserId($pdo, $user_id);
}

// Initialize dashboard data
$dashboardData = [
    'student_profile' => [],
    'enrolled_courses_count' => 0,
    'completed_courses_count' => 0,
    'pending_bills_count' => 0,
    'overdue_bills_count' => 0,
    'recent_announcements' => [],
    'current_enrollments' => [],
    'billing_overview' => [],
    'recent_grades' => []
];

if ($student_id) {
    // Get all dashboard data using the new backend functions
    $dashboardData = getStudentDashboardData($pdo, $student_id);
}

// Extract data for easier access in the template
$studentProfile = $dashboardData['student_profile'];
$enrolledCoursesCount = $dashboardData['enrolled_courses_count'];
$completedCoursesCount = $dashboardData['completed_courses_count'];
$pendingBillingsCount = $dashboardData['pending_bills_count'];
$overdueBillingsCount = $dashboardData['overdue_bills_count'];
$recentAnnouncements = $dashboardData['recent_announcements'];
$studentEnrollments = $dashboardData['current_enrollments'];
$studentBillings = $dashboardData['billing_overview'];
$studentGrades = $dashboardData['recent_grades'];

// Ensure student profile has minimum required fields if empty
if (empty($studentProfile) || !isset($studentProfile['student_id'])) {
    $studentProfile = array_merge([
        'student_id' => $student_id ?? 'Unknown',
        'full_name' => 'Student Name Not Found',
        'first_name' => 'Student',
        'last_name' => 'User',
        'email' => 'No email on file',
        'phone_number' => '',
        'year_level' => '',
        'enrollment_date' => '',
        'program_name' => 'No Program Assigned',
        'program_code' => '',
        'advisor_name' => ''
    ], $studentProfile);
}

// Ensure we have numeric values for counts (only use array count as fallback if database count is 0)
$enrolledCoursesCount = is_numeric($enrolledCoursesCount) ? $enrolledCoursesCount : 0;
$completedCoursesCount = is_numeric($completedCoursesCount) ? $completedCoursesCount : 0;
$pendingBillingsCount = is_numeric($pendingBillingsCount) ? $pendingBillingsCount : 0;
$overdueBillingsCount = is_numeric($overdueBillingsCount) ? $overdueBillingsCount : 0;

// Debug output - add ?debug=1 to URL to see data
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<div class='alert alert-info'>";
    echo "<h5>Debug Information</h5>";
    echo "<p><strong>User ID (from session):</strong> " . htmlspecialchars($user_id ?? 'Not set') . "</p>";
    echo "<p><strong>Student ID (converted):</strong> " . htmlspecialchars($student_id ?? 'Not found') . "</p>";
    echo "<p><strong>Database Dashboard Data:</strong></p>";
    echo "<pre>" . print_r($dashboardData, true) . "</pre>";
    echo "<p><strong>Processed Data Counts:</strong></p>";
    echo "<ul>";
    echo "<li>Enrolled Courses: " . $enrolledCoursesCount . "</li>";
    echo "<li>Completed Courses: " . $completedCoursesCount . "</li>";
    echo "<li>Pending Bills: " . $pendingBillingsCount . "</li>";
    echo "<li>Overdue Bills: " . $overdueBillingsCount . "</li>";
    echo "<li>Announcements: " . count($recentAnnouncements) . "</li>";
    if (!empty($recentAnnouncements)) {
        echo "<li>First announcement: " . htmlspecialchars($recentAnnouncements[0]['title'] ?? 'No title') . "</li>";
    } else {
        echo "<li>No announcements found - check error log for details</li>";
    }
    echo "<li>Enrollments: " . count($studentEnrollments) . "</li>";
    echo "<li>Billings: " . count($studentBillings) . "</li>";
    echo "<li>Grades: " . count($studentGrades) . "</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<div>
    <!-- Top Section: Mini Profile Card -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <div class="d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-circle" style="width: 80px; height: 80px;">
                                <i class="bi bi-person-fill fs-1"></i>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h3 class="card-title mb-2"><?= htmlspecialchars($studentProfile['full_name'] ?? 'Student Name') ?></h3>
                            <h6 class="text-white-50 mb-1">Student ID: <?= htmlspecialchars($studentProfile['student_id'] ?? 'N/A') ?></h6>
                            <p class="mb-1">
                                <i class="bi bi-mortarboard me-2"></i>
                                <?= htmlspecialchars($studentProfile['program_name'] ?? 'No Program') ?>
                                <?php if (!empty($studentProfile['year_level'])): ?>
                                    - Year <?= htmlspecialchars($studentProfile['year_level']) ?>
                                <?php endif; ?>
                            </p>
                            <p class="mb-0">
                                <i class="bi bi-envelope me-2"></i><?= htmlspecialchars($studentProfile['email'] ?? 'No Email') ?>
                            </p>
                        </div>
                        <div class="col text-end">
                            <div class="text-white-50 mb-0">Philippine Standard Time</div>
                            <h5 id="time" class="text-white mb-0"></h5>
                            <?php if (!empty($studentProfile['advisor_name'])): ?>
                                <small class="text-white-50 d-block mt-2">
                                    <i class="bi bi-person-badge me-1"></i>Advisor: <?= htmlspecialchars($studentProfile['advisor_name']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Overview Cards -->
    <div class="row g-4 mb-4">
        <!-- Enrolled Courses Card -->
        <div class="col-md-3">
            <div class="card text-dark bg-success-subtle h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-book-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Enrolled Courses</h5>
                        <h3 class="card-text"><?= number_format($enrolledCoursesCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Courses Card -->
        <div class="col-md-3">
            <div class="card text-dark bg-info-subtle h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Completed Courses</h5>
                        <h3 class="card-text"><?= number_format($completedCoursesCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Bills Card -->
        <div class="col-md-3">
            <div class="card text-dark bg-warning-subtle h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Pending Bills</h5>
                        <h3 class="card-text"><?= number_format($pendingBillingsCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Bills Card -->
        <div class="col-md-3">
            <div class="card text-dark bg-danger-subtle h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-cash-coin fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Overdue Bills</h5>
                        <h3 class="card-text"><?= number_format($overdueBillingsCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>    <!-- Main Content Row -->
    <div class="row g-4 mb-4">
        <!-- Left Column: Updates & Calendar -->
        <div class="col-lg-8">
            <!-- Recent Updates Section -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bell-fill me-2 text-primary"></i>Recent Updates</h5>
                    <small class="text-muted">Latest announcements</small>
                </div>                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($recentAnnouncements)): ?>
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                            <div class="d-flex mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0 me-3">
                                    <div class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-circle" style="width: 40px; height: 40px;">
                                        <i class="bi bi-megaphone-fill"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($announcement['title']) ?></h6>
                                    <p class="mb-1 text-muted"><?= htmlspecialchars($announcement['content']) ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= date('M d, Y g:i A', strtotime($announcement['created_at'])) ?>
                                        <?php if (!empty($announcement['author_name'])): ?>
                                            • by <?= htmlspecialchars($announcement['author_name']) ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox fs-1 text-muted"></i>
                            <p class="text-muted mt-2">No recent updates available.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>            <!-- Academic Calendar Section -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-calendar-fill me-2 text-success"></i>Academic Calendar</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary">Current Term</h6>
                            <p class="mb-1">
                                <?php 
                                $currentTerm = !empty($studentEnrollments) ? $studentEnrollments[0]['term_name'] ?? 'No current term' : 'No current term';
                                $currentYear = !empty($studentEnrollments) ? $studentEnrollments[0]['academic_year'] ?? '' : '';
                                echo htmlspecialchars($currentTerm);
                                if ($currentYear) echo " - " . htmlspecialchars($currentYear);
                                ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-info">Enrollment Status</h6>
                            <p class="mb-1">
                                <?php if (!empty($studentEnrollments)): ?>
                                    <span class="badge <?= getEnrollmentStatusBadgeClass($studentEnrollments[0]['status']) ?>">
                                        <?= ucfirst($studentEnrollments[0]['status']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Enrolled</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="p-2">
                                <h5 class="text-success mb-1"><?= number_format($enrolledCoursesCount) ?></h5>
                                <small class="text-muted">Active Courses</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2">
                                <h5 class="text-info mb-1"><?= number_format($completedCoursesCount) ?></h5>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="p-2">
                                <h5 class="text-warning mb-1"><?= number_format($pendingBillingsCount + $overdueBillingsCount) ?></h5>
                                <small class="text-muted">Pending Bills</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Grades Section -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-clipboard-data me-2 text-success"></i>Recent Grades</h5>
                    <small class="text-muted">Latest course results</small>
                </div>
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Course</th>
                                <th>Term</th>
                                <th>Grade</th>
                                <th>Instructor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentGrades)): ?>
                                <?php foreach (array_slice($studentGrades, 0, 6) as $grade): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($grade['course_code']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($grade['course_name']) ?></small>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($grade['term_name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($grade['academic_year']) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($grade['grade'])): ?>
                                                <span class="badge <?= getGradeBadgeClass($grade['grade'], $grade['numeric_grade']) ?>">
                                                    <?= htmlspecialchars($grade['grade']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?= htmlspecialchars($grade['instructor_name'] ?? 'TBD') ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="bi bi-clipboard-x fs-2 text-muted"></i>
                                        <p class="text-muted mt-2">No grades available yet.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($studentGrades) > 6): ?>
                    <div class="card-footer text-center">
                        <a href="?page=grade" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye me-1"></i>View All Grades
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Quick Links & Current Status -->
        <div class="col-lg-4">
            <!-- Quick Links Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-link-45deg me-2 text-info"></i>Quick Links</h5>
                </div>
                <div class="card-body p-2">
                    <div class="list-group list-group-flush">
                        <a href="?page=grade" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-clipboard-data-fill me-3 text-success"></i>
                            <div>
                                <h6 class="mb-0">Grades</h6>
                                <small class="text-muted">Check your grades</small>
                            </div>
                        </a>
                        <a href="https://plsp.edu.ph/academics/enrollment-services/downloadable-forms/" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-file-earmark-arrow-down-fill me-3 text-warning"></i>
                            <div>
                                <h6 class="mb-0">Form Downloadables</h6>
                                <small class="text-muted">For easy access to important forms</small>
                            </div>
                        </a>
                        <a href="https://drive.google.com/file/d/1v5-07Eqq8hqLl1r7NuPzvrkHF3ApDagb/view?usp=sharing" class="list-group-item list-group-item-action d-flex align-items-center">
                            <i class="bi bi-google-play me-3 text-success"></i>
                            <div>
                                <h6 class="mb-0">PLSP Mobile App</h6>
                                <small class="text-muted">View Grades with ease</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>            <!-- Current Status Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="bi bi-activity me-2 text-danger"></i>Current Status</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6 class="text-primary">Academic Standing</h6>
                        <p class="mb-1">
                            <?php if ($completedCoursesCount > 0): ?>
                                <span class="badge bg-success">Active Student</span>
                            <?php else: ?>
                                <span class="badge bg-info">New Student</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-info">Billing Status</h6>
                        <p class="mb-1">
                            <?php if ($overdueBillingsCount > 0): ?>
                                <span class="badge bg-danger">Overdue Bills</span>
                            <?php elseif ($pendingBillingsCount > 0): ?>
                                <span class="badge bg-warning text-dark">Pending Bills</span>
                            <?php else: ?>
                                <span class="badge bg-success">Up to Date</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <div class="mb-3">
                        <h6 class="text-success">Recent Activity</h6>
                        <small class="text-muted">
                            <?php if (!empty($studentGrades)): ?>
                                Last grade: <?= htmlspecialchars($studentGrades[0]['course_code']) ?>
                            <?php elseif (!empty($studentEnrollments)): ?>
                                Last enrollment: <?= date('M d, Y', strtotime($studentEnrollments[0]['enrollment_date'])) ?>
                            <?php else: ?>
                                No recent activity
                            <?php endif; ?>
                        </small>
                    </div>

                    <?php if (!empty($studentProfile['enrollment_date'])): ?>
                        <div class="mt-3 pt-3 border-top">
                            <small class="text-muted">
                                <i class="bi bi-calendar-event me-1"></i>
                                Student since <?= date('M Y', strtotime($studentProfile['enrollment_date'])) ?>
                            </small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Billing Summary Section -->
            <div class="card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-receipt me-2 text-warning"></i>Billing Summary</h5>
                    <small class="text-muted">Recent bills</small>
                </div>
                <div class="card-body p-0" style="max-height: 350px; overflow-y: auto;">
                    <?php if (!empty($studentBillings)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($studentBillings, 0, 6) as $billing): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= htmlspecialchars($billing['term_name']) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($billing['academic_year']) ?></small>
                                        </div>
                                        <div class="text-end">
                                            <div class="mb-1">
                                                <span class="badge <?= getBillingStatusBadgeClass($billing['status']) ?>">
                                                    <?= ucfirst($billing['status']) ?>
                                                </span>
                                            </div>
                                            <div class="small">
                                                <strong>₱<?= number_format($billing['balance'], 2) ?></strong>
                                                <small class="text-muted d-block">
                                                    Due: <?= date('M d, Y', strtotime($billing['due_date'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="bi bi-receipt fs-2 text-muted"></i>
                            <p class="text-muted mt-2">No billing records found.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (count($studentBillings) > 6): ?>
                    <div class="card-footer text-center">
                        <a href="?page=billing" class="btn btn-outline-warning btn-sm">
                            <i class="bi bi-eye me-1"></i>View All Bills
                        </a>
                    </div>
                <?php endif; ?>
            </div>        </div>
    </div>
</div>

<script>
    // Update time display
    function updateTime() {
        const now = new Date();
        const options = {
            timeZone: 'Asia/Manila',
            hour12: true,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        };
        document.getElementById('time').textContent = now.toLocaleTimeString('en-US', options);
    }
    
    // Update time immediately and then every second
    updateTime();
    setInterval(updateTime, 1000);
</script>
