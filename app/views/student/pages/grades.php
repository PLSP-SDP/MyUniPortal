<?php
// Include student grades backend functions
require_once '../../Controllers/student-page/grades.backend.php';

// Get student ID from session
$user_id = $_SESSION['user_id'] ?? null;
$student_id = null;

if ($user_id) {
    // Convert user_id to student_id
    $student_id = getStudentIdFromUserId($pdo, $user_id);
}

// Initialize grade data
$currentGrades = [];
$gradeHistory = [];
$academicSummary = [
    'total_completed_courses' => 0,
    'total_completed_units' => 0,
    'overall_gpa' => 0,
    'weighted_gpa' => 0,
    'current_enrolled_courses' => 0,
    'current_enrolled_units' => 0,
    'passed_courses' => 0,
    'failed_courses' => 0,
    'incomplete_courses' => 0,
    'semester_gpa' => []
];

if ($student_id) {
    // Get all grade data using the backend functions
    $currentGrades = getStudentCurrentGrades($pdo, $student_id);
    $gradeHistory = getStudentGradeHistory($pdo, $student_id);
    $academicSummary = getStudentAcademicSummary($pdo, $student_id);
}



// Debug output - add ?debug=1 to URL to see data
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<div class='alert alert-info'>";
    echo "<h5>Debug Information</h5>";
    echo "<p><strong>Student ID:</strong> " . htmlspecialchars($student_id ?? 'Not found') . "</p>";
    echo "<p><strong>Current Grades Count:</strong> " . count($currentGrades) . "</p>";
    echo "<p><strong>Grade History Count:</strong> " . count($gradeHistory) . "</p>";
    echo "<p><strong>Academic Summary:</strong></p>";
    echo "<pre>" . print_r($academicSummary, true) . "</pre>";
    echo "</div>";
}
?>

<div>
    <!-- Page Header -->
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card text-dark">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h2 class="card-title mb-2">
                                <i class="bi bi-mortarboard-fill me-2"></i>Academic Grades
                            </h2>
                            <p class="mb-0">View your current semester grades, complete academic history, and GPA calculations</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="row text-center">
                                <div class="col-4">
                                    <h4 class="<?= getGpaStatusClass($academicSummary['weighted_gpa']) ?> mb-0">
                                        <?= number_format($academicSummary['weighted_gpa'], 2) ?>
                                    </h4>
                                    <small class="text-dark-50">Overall GPA</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-dark mb-0"><?= $academicSummary['total_completed_units'] ?></h4>
                                    <small class="text-dark-50">Units Completed</small>
                                </div>
                                <div class="col-4">
                                    <h4 class="text-dark mb-0"><?= $academicSummary['passed_courses'] ?></h4>
                                    <small class="text-dark-50">Courses Passed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <ul class="nav nav-tabs" id="gradesTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button" role="tab">
                        <i class="bi bi-calendar-check me-2"></i>Current Semester
                        <span class="badge bg-secondary ms-2"><?= count($currentGrades) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button" role="tab">
                        <i class="bi bi-clock-history me-2"></i>Grade History
                        <span class="badge bg-secondary ms-2"><?= count($gradeHistory) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                        <i class="bi bi-graph-up me-2"></i>Academic Summary
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content" id="gradesTabContent">
        
        <!-- Current Semester Grades Tab -->
        <div class="tab-pane fade show active" id="current" role="tabpanel">
            <?php if (!empty($currentGrades)): ?>
                <div class="row g-4">
                    <?php foreach ($currentGrades as $course): ?>
                        <div class="col-lg-6 col-xl-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0 fw-bold"><?= htmlspecialchars($course['course_code']) ?></h6>
                                    <?php if ($course['grade']): ?>
                                        <span class="badge <?= getGradeBadgeClass($course['grade'], $course['numeric_grade']) ?> rounded-pill">
                                            <?= htmlspecialchars($course['grade']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info rounded-pill"><?= htmlspecialchars($course['grade_status']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body">
                                    <h6 class="card-title"><?= htmlspecialchars($course['course_name']) ?></h6>
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <small class="text-muted">Units:</small>
                                            <div class="fw-semibold"><?= $course['units'] ?></div>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Section:</small>
                                            <div class="fw-semibold"><?= htmlspecialchars($course['section']) ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Instructor:</small>
                                        <div class="fw-semibold"><?= htmlspecialchars($course['instructor_name'] ?: 'TBA') ?></div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block">Schedule:</small>
                                        <div class="small"><?= htmlspecialchars($course['schedule']) ?></div>
                                        <?php if ($course['room']): ?>
                                            <div class="small text-muted">Room: <?= htmlspecialchars($course['room']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($course['numeric_grade']): ?>
                                        <div class="mt-3 pt-2 border-top">
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">Numeric Grade:</small>
                                                    <div class="fw-bold <?= getGpaStatusClass($course['numeric_grade']) ?>">
                                                        <?= number_format($course['numeric_grade'], 2) ?>
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">Status:</small>
                                                    <div class="fw-semibold text-<?= $course['numeric_grade'] >= 3.0 ? 'success' : 'danger' ?>">
                                                        <?= $course['numeric_grade'] >= 3.0 ? 'Passed' : 'Failed' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted">No Current Enrollments</h4>
                    <p class="text-muted">You are not currently enrolled in any courses for this semester.</p>
                    <a href="?page=courses" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Browse Available Courses
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Grade History Tab -->
        <div class="tab-pane fade" id="history" role="tabpanel">
            <?php if (!empty($gradeHistory)): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Complete Academic History</h5>
                        <div>
                            <button class="btn btn-outline-primary btn-sm" onclick="downloadTranscript()">
                                <i class="bi bi-download me-2"></i>Download Transcript
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Term</th>
                                        <th>Course Code</th>
                                        <th>Course Name</th>
                                        <th>Units</th>
                                        <th>Instructor</th>
                                        <th>Grade</th>
                                        <th>Numeric</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $current_term = '';
                                    foreach ($gradeHistory as $course): 
                                        $term_display = $course['term_name'] . ' ' . $course['academic_year'];
                                        $show_term = ($current_term !== $term_display);
                                        $current_term = $term_display;
                                    ?>
                                        <tr>
                                            <td>
                                                <?php if ($show_term): ?>
                                                    <strong><?= htmlspecialchars($term_display) ?></strong>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-semibold"><?= htmlspecialchars($course['course_code']) ?></td>
                                            <td><?= htmlspecialchars($course['course_name']) ?></td>
                                            <td><?= $course['units'] ?></td>
                                            <td><?= htmlspecialchars($course['instructor_name'] ?: 'N/A') ?></td>
                                            <td>
                                                <span class="badge <?= getGradeBadgeClass($course['grade'], $course['numeric_grade']) ?>">
                                                    <?= htmlspecialchars($course['grade']) ?>
                                                </span>
                                            </td>
                                            <td class="<?= getGpaStatusClass($course['numeric_grade']) ?>">
                                                <?= $course['numeric_grade'] ? number_format($course['numeric_grade'], 2) : 'N/A' ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $course['grade_status'] === 'Passed' ? 'success' : ($course['grade_status'] === 'Failed' ? 'danger' : 'warning') ?> bg-opacity-10 text-<?= $course['grade_status'] === 'Passed' ? 'success' : ($course['grade_status'] === 'Failed' ? 'danger' : 'warning') ?>">
                                                    <?= htmlspecialchars($course['grade_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-file-earmark-text text-muted" style="font-size: 4rem;"></i>
                    <h4 class="mt-3 text-muted">No Grade History</h4>
                    <p class="text-muted">You don't have any completed courses with grades yet.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Academic Summary Tab -->
        <div class="tab-pane fade" id="summary" role="tabpanel">
            <div class="row g-4">
                <!-- Overall Statistics -->
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Academic Performance Overview</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3">
                                        <h3 class="<?= getGpaStatusClass($academicSummary['weighted_gpa']) ?> mb-1">
                                            <?= number_format($academicSummary['weighted_gpa'], 2) ?>
                                        </h3>
                                        <h6 class="text-muted mb-0">Weighted GPA</h6>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3">
                                        <h3 class="text-primary mb-1"><?= $academicSummary['total_completed_units'] ?></h3>
                                        <h6 class="text-muted mb-0">Units Completed</h6>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3">
                                        <h3 class="text-success mb-1"><?= $academicSummary['passed_courses'] ?></h3>
                                        <h6 class="text-muted mb-0">Courses Passed</h6>
                                    </div>
                                </div>
                                <div class="col-md-3 text-center">
                                    <div class="border rounded p-3">
                                        <h3 class="text-info mb-1"><?= $academicSummary['current_enrolled_courses'] ?></h3>
                                        <h6 class="text-muted mb-0">Current Courses</h6>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Status Breakdown -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Course Status Breakdown</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge bg-success rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                        <div>
                                            <div class="fw-semibold"><?= $academicSummary['passed_courses'] ?></div>
                                            <small class="text-muted">Passed</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge bg-danger rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                        <div>
                                            <div class="fw-semibold"><?= $academicSummary['failed_courses'] ?></div>
                                            <small class="text-muted">Failed</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge bg-warning rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                        <div>
                                            <div class="fw-semibold"><?= $academicSummary['incomplete_courses'] ?></div>
                                            <small class="text-muted">Incomplete</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="d-flex align-items-center">
                                        <div class="badge bg-info rounded-circle me-2" style="width: 12px; height: 12px;"></div>
                                        <div>
                                            <div class="fw-semibold"><?= $academicSummary['current_enrolled_courses'] ?></div>
                                            <small class="text-muted">In Progress</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semester GPA History -->
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-2"></i>Semester GPA Trend</h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($academicSummary['semester_gpa'])): ?>
                                <div class="d-flex flex-column gap-2">
                                    <?php foreach (array_slice($academicSummary['semester_gpa'], 0, 5) as $semester): ?>
                                        <div class="d-flex justify-content-between align-items-center py-1">
                                            <div>
                                                <small class="fw-semibold"><?= htmlspecialchars($semester['term_name'] . ' ' . $semester['academic_year']) ?></small>
                                                <div class="small text-muted"><?= $semester['courses_taken'] ?> courses</div>
                                            </div>
                                            <div class="text-end">
                                                <div class="fw-bold <?= getGpaStatusClass($semester['weighted_gpa']) ?>">
                                                    <?= number_format($semester['weighted_gpa'], 2) ?>
                                                </div>
                                                <small class="text-muted"><?= $semester['units_taken'] ?> units</small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-graph-down"></i>
                                    <div>No semester data available</div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grade Details Modal -->
<div class="modal fade" id="gradeDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Grade Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="gradeDetailsContent">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
// Function to download transcript
function downloadTranscript() {
    // Show loading state
    const originalText = event.target.innerHTML;
    event.target.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Generating...';
    event.target.disabled = true;
    
    // Make AJAX request to generate transcript
    fetch(`dashboard.php?page=grades&action=download_transcript`, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.blob())
    .then(blob => {
        // Create download link
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = 'transcript.pdf';
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    })
    .catch(error => {
        console.error('Error downloading transcript:', error);
        alert('Error downloading transcript. Please try again.');
    })
    .finally(() => {
        // Restore button state
        event.target.innerHTML = originalText;
        event.target.disabled = false;
    });
}

// Function to view grade details
function viewGradeDetails(detailId) {
    fetch(`dashboard.php?page=grades&action=get_grade_details&detail_id=${detailId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Populate modal with grade details
            document.getElementById('gradeDetailsContent').innerHTML = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6>Course Information</h6>
                        <p><strong>Course:</strong> ${data.course_code} - ${data.course_name}</p>
                        <p><strong>Units:</strong> ${data.units}</p>
                        <p><strong>Section:</strong> ${data.section}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Grade Information</h6>
                        <p><strong>Letter Grade:</strong> <span class="badge bg-primary">${data.grade}</span></p>
                        <p><strong>Numeric Grade:</strong> ${data.numeric_grade}</p>
                        <p><strong>Status:</strong> ${data.status}</p>
                    </div>
                    <div class="col-12">
                        <h6>Class Details</h6>
                        <p><strong>Instructor:</strong> ${data.instructor_name}</p>
                        <p><strong>Schedule:</strong> ${data.schedule}</p>
                        <p><strong>Term:</strong> ${data.term_name} ${data.academic_year}</p>
                    </div>
                </div>
            `;
            
            // Show modal
            new bootstrap.Modal(document.getElementById('gradeDetailsModal')).show();
        } else {
            alert('Error loading grade details: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading grade details. Please try again.');
    });
}

// Initialize Bootstrap tabs
document.addEventListener('DOMContentLoaded', function() {
    // Activate the first tab by default
    const firstTab = document.querySelector('#gradesTab .nav-link');
    if (firstTab) {
        new bootstrap.Tab(firstTab);
    }
});
</script>
