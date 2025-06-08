<?php
// Include the profile backend controller
require_once '../../Controllers/student-page/profile.backend.php';

// Get student ID from session
$user_id = $_SESSION['user_id'] ?? '';

if (empty($user_id)) {
    echo '<div class="alert alert-danger">User ID not found in session</div>';
    return;
}

// Get the actual student_id from the students table using user_id
$stmt = $pdo->prepare("SELECT student_id FROM students WHERE user_id = ?");
$stmt->execute([$user_id]);
$student_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student_data) {
    echo '<div class="alert alert-danger">Student record not found</div>';
    return;
}

$student_id = $student_data['student_id'];

// Fetch profile data
$studentProfile = getStudentFullProfile($pdo, $student_id);
$academicStats = getStudentAcademicStats($pdo, $student_id);
$enrollmentHistory = getStudentEnrollmentHistory($pdo, $student_id);

// Get notification from session
$notification = $_SESSION['profile_response'] ?? null;
unset($_SESSION['profile_response']);
?>

<div class="container-fluid">
    <?php if ($notification): ?>
        <div class="alert alert-<?php echo $notification['success'] ? 'success' : 'danger'; ?> alert-dismissible fade show">
            <?php echo htmlspecialchars($notification['message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card text-dark">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2 class="mb-1">
                                <i class="bi bi-person-circle me-2"></i>
                                Student Profile
                            </h2>
                            <p class="mb-0">Manage your personal information and academic details</p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="text-dark-50">
                                <small>
                                    <i class="bi bi-calendar-event me-1"></i>
                                    Last updated: <?= date('M d, Y g:i A') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Content Row -->
    <div class="row g-4">
        <!-- Left Column: Personal Information -->
        <div class="col-lg-8">
            <!-- Basic Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-fill me-2 text-primary"></i>
                        Personal Information
                    </h5>
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                        <i class="bi bi-pencil-square me-1"></i>Edit
                    </button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Student ID</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['student_id']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">First Name</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['first_name']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Last Name</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['last_name']) ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Date of Birth</label>
                                <p class="h6 mb-0">
                                    <?= $studentProfile['date_of_birth'] ? date('F d, Y', strtotime($studentProfile['date_of_birth'])) : 'Not provided' ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Gender</label>
                                <p class="h6 mb-0"><?= ucfirst($studentProfile['gender']) ?: 'Not specified' ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Email Address</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['email']) ?: 'Not provided' ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Phone Number</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['phone_number']) ?: 'Not provided' ?></p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Year Level</label>
                                <p class="h6 mb-0">
                                    <?php 
                                    $yearLevel = $studentProfile['year_level'];
                                    if ($yearLevel) {
                                        $yearText = '';
                                        switch ($yearLevel) {
                                            case 1: $yearText = '1st Year'; break;
                                            case 2: $yearText = '2nd Year'; break;
                                            case 3: $yearText = '3rd Year'; break;
                                            case 4: $yearText = '4th Year'; break;
                                            case 5: $yearText = '5th Year'; break;
                                            default: $yearText = $yearLevel . 'th Year'; break;
                                        }
                                        echo $yearText;
                                    } else {
                                        echo 'Not assigned';
                                    }
                                    ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Enrollment Date</label>
                                <p class="h6 mb-0">
                                    <?= $studentProfile['enrollment_date'] ? date('F d, Y', strtotime($studentProfile['enrollment_date'])) : 'Not available' ?>
                                </p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Login ID</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['login_id']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-geo-alt-fill me-2 text-success"></i>
                        Address Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Street Address</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['address']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label text-muted small">City</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['city']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label text-muted small">State/Province</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['state']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Postal Code</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['postal_code']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Country</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['country']) ?: 'Philippines' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>            <!-- Emergency Contact Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-telephone-fill me-2 text-warning"></i>
                        Emergency Contact
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Contact Name</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['emergency_contact_name']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label text-muted small">Contact Phone</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['emergency_contact_phone']) ?: 'Not provided' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enrollment History Card -->
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2 text-info"></i>
                        Enrollment History
                    </h5>
                    <small class="text-muted">All records</small>
                </div>
                <div class="card-body">
                    <?php if (!empty($enrollmentHistory)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Term</th>
                                        <th>Courses</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrollmentHistory as $enrollment): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($enrollment['term_name']) ?></div>
                                                <small class="text-muted"><?= htmlspecialchars($enrollment['academic_year']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= $enrollment['total_courses'] ?></span>
                                            </td>
                                            <td>
                                                <span class="badge <?= getEnrollmentStatusBadgeClass($enrollment['enrollment_status']) ?>">
                                                    <?= ucfirst($enrollment['enrollment_status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-journal-x fs-4 text-muted"></i>
                            <p class="text-muted mt-2 mb-0 small">No enrollment history found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Academic & Account Information -->
        <div class="col-lg-4">
            <!-- Academic Statistics Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2 text-info"></i>
                        Academic Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="p-2">
                                <h4 class="text-primary mb-1"><?= $academicStats['total_enrolled'] ?></h4>
                                <small class="text-muted">Total Enrolled</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2">
                                <h4 class="text-success mb-1"><?= $academicStats['total_completed'] ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2">
                                <h4 class="text-info mb-1"><?= $academicStats['total_units'] ?></h4>
                                <small class="text-muted">Units Earned</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2">
                                <h4 class="text-warning mb-1"><?= number_format($academicStats['gpa'], 2) ?></h4>
                                <small class="text-muted">GPA</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Program Information Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-mortarboard-fill me-2 text-success"></i>
                        Program Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Program</label>
                        <p class="h6 mb-1"><?= htmlspecialchars($studentProfile['program_name']) ?: 'Not assigned' ?></p>
                        <?php if ($studentProfile['program_code']): ?>
                            <small class="text-muted"><?= htmlspecialchars($studentProfile['program_code']) ?></small>
                        <?php endif; ?>
                    </div>
                    <?php if ($studentProfile['program_description']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Description</label>
                            <p class="small mb-0"><?= htmlspecialchars($studentProfile['program_description']) ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="row">
                        <?php if ($studentProfile['duration_years']): ?>
                            <div class="col-6">
                                <label class="form-label text-muted small">Duration</label>
                                <p class="h6 mb-0"><?= $studentProfile['duration_years'] ?> years</p>
                            </div>
                        <?php endif; ?>
                        <?php if ($studentProfile['program_total_units']): ?>
                            <div class="col-6">
                                <label class="form-label text-muted small">Total Units</label>
                                <p class="h6 mb-0"><?= $studentProfile['program_total_units'] ?> units</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Academic Advisor Card -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge me-2 text-primary"></i>
                        Academic Advisor
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($studentProfile['advisor_name']): ?>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Advisor Name</label>
                            <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['advisor_name']) ?></p>
                        </div>
                        <?php if ($studentProfile['advisor_email']): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Email</label>
                                <p class="h6 mb-0">
                                    <a href="mailto:<?= htmlspecialchars($studentProfile['advisor_email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($studentProfile['advisor_email']) ?>
                                    </a>
                                </p>
                            </div>
                        <?php endif; ?>
                        <?php if ($studentProfile['advisor_phone']): ?>
                            <div class="mb-3">
                                <label class="form-label text-muted small">Phone</label>
                                <p class="h6 mb-0"><?= htmlspecialchars($studentProfile['advisor_phone']) ?></p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-person-x fs-2 text-muted"></i>
                            <p class="text-muted mt-2 mb-0">No advisor assigned</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Account Information Card -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check me-2 text-danger"></i>
                        Account Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Account Status</label>
                        <p class="mb-0">
                            <span class="badge <?= getAccountStatusBadgeClass($studentProfile['account_status']) ?>">
                                <?= ucfirst($studentProfile['account_status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Account Created</label>
                        <p class="h6 mb-0">
                            <?= $studentProfile['account_created'] ? date('F d, Y', strtotime($studentProfile['account_created'])) : 'Not available' ?>
                        </p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Last Login</label>
                        <p class="h6 mb-0">
                            <?= $studentProfile['last_login'] ? date('F d, Y g:i A', strtotime($studentProfile['last_login'])) : 'Never' ?>
                        </p>
                    </div>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="bi bi-key me-1"></i>Change Password
                        </button>
                    </div>
                </div>
            </div>
        </div>    </div>
</div>

<!-- Modals would go here for editing profile and changing password -->
<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">
                    <i class="bi bi-pencil-square me-2"></i>Edit Profile
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="dashboard.php?page=profile&action=update_profile">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($studentProfile['first_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($studentProfile['last_name']) ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($studentProfile['email']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" 
                                       value="<?= htmlspecialchars($studentProfile['phone_number']) ?>">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?= htmlspecialchars($studentProfile['address']) ?></textarea>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?= htmlspecialchars($studentProfile['city']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="state" class="form-label">State/Province</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?= htmlspecialchars($studentProfile['state']) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" 
                                       value="<?= htmlspecialchars($studentProfile['postal_code']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                       value="<?= htmlspecialchars($studentProfile['emergency_contact_name']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                <input type="text" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                       value="<?= htmlspecialchars($studentProfile['emergency_contact_phone']) ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changePasswordModalLabel">
                    <i class="bi bi-key me-2"></i>Change Password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="dashboard.php?page=profile&action=change_password">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>
