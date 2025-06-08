<?php
// Check for response from session (set by dashboard.php after processing)
$student_response = ['status' => false, 'message' => ''];
if (isset($_SESSION['student_response'])) {
    $student_response = $_SESSION['student_response'];
    unset($_SESSION['student_response']); // Clear it after use
}

$staffId = getStaffIdByUserId($userID);

// Get student data
$student_data = data_Student($pdo);

// Get programs for dropdown
$programs_data = getPrograms($pdo);

// Get advisors for dropdown
$advisors_data = getAdvisors($pdo);
?>

<div>
  <!-- Header -->
  <div class="navbg p-3 rounded rounded-3 shadow-sm">
    <div class="d-flex justify-content-between flex-wrap align-items-center">
      <h5 class="mb-0">Student Management</h5>
      <div>
        <button type="button" class="btn btn-outline-secondary me-2" id="refreshStudentBtn">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
          <i class="bi bi-plus-lg"></i> Add Student
        </button>
      </div>
    </div>
  </div>

  <!-- Alert Container for system messages -->
  <div id="alertContainer" class="mt-3">
    <?php if (!empty($student_response['message'])): ?>
      <div
        class="alert <?= $student_response['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show"
        role="alert">
        <?= $student_response['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
  </div>

  <!-- Filters -->
  <div class="card mt-3">
    <div class="card-body">
      <form method="GET" class="row g-3" id="filterForm">
        <input type="hidden" name="page" value="<?= isset($_GET['page']) ? $_GET['page'] : '' ?>">
        <input type="hidden" name="subpage" value="<?= isset($_GET['subpage']) ? $_GET['subpage'] : '' ?>">
        
        <div class="col-md-3">
          <label for="search" class="form-label">Search</label>
          <input type="text" class="form-control" id="search" name="search" 
                 value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>" 
                 placeholder="Name, ID, Email...">
        </div>
        
        <div class="col-md-2">
          <label for="program" class="form-label">Program</label>
          <select class="form-select" id="program" name="program">
            <option value="">All Programs</option>
            <?php if ($programs_data['status']): ?>
              <?php foreach ($programs_data['data'] as $program): ?>
                <option value="<?= $program['program_id'] ?>" 
                        <?= (isset($_GET['program']) && $_GET['program'] == $program['program_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        
        <div class="col-md-2">
          <label for="year_level" class="form-label">Year Level</label>
          <select class="form-select" id="year_level" name="year_level">
            <option value="">All Years</option>
            <option value="1" <?= (isset($_GET['year_level']) && $_GET['year_level'] == '1') ? 'selected' : '' ?>>1st Year</option>
            <option value="2" <?= (isset($_GET['year_level']) && $_GET['year_level'] == '2') ? 'selected' : '' ?>>2nd Year</option>
            <option value="3" <?= (isset($_GET['year_level']) && $_GET['year_level'] == '3') ? 'selected' : '' ?>>3rd Year</option>
            <option value="4" <?= (isset($_GET['year_level']) && $_GET['year_level'] == '4') ? 'selected' : '' ?>>4th Year</option>
          </select>
        </div>
        
        <div class="col-md-2">
          <label for="status" class="form-label">Status</label>
          <select class="form-select" id="status" name="status">
            <option value="">All Status</option>
            <option value="active" <?= (isset($_GET['status']) && $_GET['status'] == 'active') ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= (isset($_GET['status']) && $_GET['status'] == 'inactive') ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= (isset($_GET['status']) && $_GET['status'] == 'suspended') ? 'selected' : '' ?>>Suspended</option>
          </select>
        </div>
        
        <div class="col-md-2">
          <label for="gender" class="form-label">Gender</label>
          <select class="form-select" id="gender" name="gender">
            <option value="">All Genders</option>
            <option value="male" <?= (isset($_GET['gender']) && $_GET['gender'] == 'male') ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= (isset($_GET['gender']) && $_GET['gender'] == 'female') ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= (isset($_GET['gender']) && $_GET['gender'] == 'other') ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        
        <div class="col-md-1">
          <label class="form-label">&nbsp;</label>
          <div>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-search"></i> Filter
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Students Table -->
  <div class="card mt-3">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-hover">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Login ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Program</th>
              <th>Year Level</th>
              <th>Status</th>
              <th>Enrollment Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($student_data['status'] && !empty($student_data['data'])): ?>
              <?php foreach ($student_data['data'] as $student): ?>
                <tr>
                  <td><?= htmlspecialchars($student['student_id']) ?></td>
                  <td><?= htmlspecialchars($student['login_id']) ?></td>
                  <td><?= htmlspecialchars($student['full_name']) ?></td>
                  <td><?= htmlspecialchars($student['email']) ?></td>
                  <td>
                    <?php if ($student['program_code']): ?>
                      <span class="badge bg-info"><?= htmlspecialchars($student['program_code']) ?></span>
                      <small class="d-block text-muted"><?= htmlspecialchars($student['program_name']) ?></small>
                    <?php else: ?>
                      <span class="text-muted">No Program</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($student['year_level']): ?>
                      <?= htmlspecialchars($student['year_level']) ?><?= $student['year_level'] == 1 ? 'st' : ($student['year_level'] == 2 ? 'nd' : ($student['year_level'] == 3 ? 'rd' : 'th')) ?> Year
                    <?php else: ?>
                      <span class="text-muted">Not Set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php
                    $statusClass = '';
                    switch ($student['status']) {
                      case 'active':
                        $statusClass = 'bg-success';
                        break;
                      case 'inactive':
                        $statusClass = 'bg-secondary';
                        break;
                      case 'suspended':
                        $statusClass = 'bg-danger';
                        break;
                      default:
                        $statusClass = 'bg-warning';
                    }
                    ?>
                    <span class="badge <?= $statusClass ?>"><?= ucfirst(htmlspecialchars($student['status'])) ?></span>
                  </td>
                  <td>
                    <?php if ($student['enrollment_date']): ?>
                      <?= date('M d, Y', strtotime($student['enrollment_date'])) ?>
                    <?php else: ?>
                      <span class="text-muted">Not Set</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="btn-group" role="group">
                      <button type="button" class="btn btn-sm btn-outline-primary" 
                              onclick="viewStudent('<?= $student['student_id'] ?>')" 
                              title="View Details">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-warning" 
                              onclick="editStudent('<?= $student['student_id'] ?>')" 
                              title="Edit Student">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-info" 
                              onclick="resetPassword('<?= $student['student_id'] ?>')" 
                              title="Reset Password">
                        <i class="bi bi-key"></i>
                      </button>
                      <button type="button" class="btn btn-sm btn-outline-danger" 
                              onclick="deleteStudent('<?= $student['student_id'] ?>')" 
                              title="Delete Student">
                        <i class="bi bi-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="9" class="text-center">
                  <?php if (!$student_data['status']): ?>
                    <div class="alert alert-danger">Error loading students: <?= htmlspecialchars($student_data['message']) ?></div>
                  <?php else: ?>
                    <div class="text-muted">No students found matching the criteria.</div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addStudentModalLabel">Add New Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=add">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_first_name" class="form-label">First Name *</label>
                <input type="text" class="form-control" id="add_first_name" name="first_name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_last_name" class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="add_last_name" name="last_name" required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="add_email" name="email" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_phone_number" class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="add_phone_number" name="phone_number">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="add_date_of_birth" name="date_of_birth">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_gender" class="form-label">Gender</label>
                <select class="form-select" id="add_gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="add_address" class="form-label">Address</label>
            <textarea class="form-control" id="add_address" name="address" rows="2"></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label for="add_city" class="form-label">City</label>
                <input type="text" class="form-control" id="add_city" name="city">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="add_state" class="form-label">State/Province</label>
                <input type="text" class="form-control" id="add_state" name="state">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="add_postal_code" class="form-label">Postal Code</label>
                <input type="text" class="form-control" id="add_postal_code" name="postal_code">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_program_id" class="form-label">Program *</label>
                <select class="form-select" id="add_program_id" name="program_id" required>
                  <option value="">Select Program</option>
                  <?php if ($programs_data['status']): ?>
                    <?php foreach ($programs_data['data'] as $program): ?>
                      <option value="<?= $program['program_id'] ?>">
                        <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_year_level" class="form-label">Year Level</label>
                <select class="form-select" id="add_year_level" name="year_level">
                  <option value="1" selected>1st Year</option>
                  <option value="2">2nd Year</option>
                  <option value="3">3rd Year</option>
                  <option value="4">4th Year</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_academic_advisor_id" class="form-label">Academic Advisor</label>
                <select class="form-select" id="add_academic_advisor_id" name="academic_advisor_id">
                  <option value="">Select Advisor</option>
                  <?php if ($advisors_data['status']): ?>
                    <?php foreach ($advisors_data['data'] as $advisor): ?>
                      <option value="<?= $advisor['staff_id'] ?>">
                        <?= htmlspecialchars($advisor['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_enrollment_date" class="form-label">Enrollment Date</label>
                <input type="date" class="form-control" id="add_enrollment_date" name="enrollment_date" 
                       value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_emergency_contact_name" class="form-label">Emergency Contact Name</label>
                <input type="text" class="form-control" id="add_emergency_contact_name" name="emergency_contact_name">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="add_emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                <input type="text" class="form-control" id="add_emergency_contact_phone" name="emergency_contact_phone">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success">Add Student</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editStudentModalLabel">Edit Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" action="?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=update" id="editStudentForm">
        <input type="hidden" id="edit_student_id" name="student_id">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_first_name" class="form-label">First Name *</label>
                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_last_name" class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_email" class="form-label">Email *</label>
                <input type="email" class="form-control" id="edit_email" name="email" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_phone_number" class="form-label">Phone Number</label>
                <input type="text" class="form-control" id="edit_phone_number" name="phone_number">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="edit_date_of_birth" name="date_of_birth">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_gender" class="form-label">Gender</label>
                <select class="form-select" id="edit_gender" name="gender">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="mb-3">
            <label for="edit_address" class="form-label">Address</label>
            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
          </div>
          
          <div class="row">
            <div class="col-md-4">
              <div class="mb-3">
                <label for="edit_city" class="form-label">City</label>
                <input type="text" class="form-control" id="edit_city" name="city">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="edit_state" class="form-label">State/Province</label>
                <input type="text" class="form-control" id="edit_state" name="state">
              </div>
            </div>
            <div class="col-md-4">
              <div class="mb-3">
                <label for="edit_postal_code" class="form-label">Postal Code</label>
                <input type="text" class="form-control" id="edit_postal_code" name="postal_code">
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_program_id" class="form-label">Program *</label>
                <select class="form-select" id="edit_program_id" name="program_id" required>
                  <option value="">Select Program</option>
                  <?php if ($programs_data['status']): ?>
                    <?php foreach ($programs_data['data'] as $program): ?>
                      <option value="<?= $program['program_id'] ?>">
                        <?= htmlspecialchars($program['program_code'] . ' - ' . $program['program_name']) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_year_level" class="form-label">Year Level</label>
                <select class="form-select" id="edit_year_level" name="year_level">
                  <option value="1">1st Year</option>
                  <option value="2">2nd Year</option>
                  <option value="3">3rd Year</option>
                  <option value="4">4th Year</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_academic_advisor_id" class="form-label">Academic Advisor</label>
                <select class="form-select" id="edit_academic_advisor_id" name="academic_advisor_id">
                  <option value="">Select Advisor</option>
                  <?php if ($advisors_data['status']): ?>
                    <?php foreach ($advisors_data['data'] as $advisor): ?>
                      <option value="<?= $advisor['staff_id'] ?>">
                        <?= htmlspecialchars($advisor['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_status" class="form-label">Status</label>
                <select class="form-select" id="edit_status" name="status">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="suspended">Suspended</option>
                </select>
              </div>
            </div>
          </div>
          
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_emergency_contact_name" class="form-label">Emergency Contact Name</label>
                <input type="text" class="form-control" id="edit_emergency_contact_name" name="emergency_contact_name">
              </div>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label for="edit_emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                <input type="text" class="form-control" id="edit_emergency_contact_phone" name="emergency_contact_phone">
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning">Update Student</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- View Student Modal -->
<div class="modal fade" id="viewStudentModal" tabindex="-1" aria-labelledby="viewStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewStudentModalLabel">Student Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="viewStudentContent">
        <!-- Content will be loaded via JavaScript -->
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Reset Password Confirmation Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning">
          <i class="bi bi-exclamation-triangle"></i>
          <strong>Warning!</strong> This will reset the password for student <strong id="resetPasswordStudentId"></strong> to the default password: <code>student123</code>
        </div>
        <p>The student will need to log in with this new password and should change it immediately.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-warning" id="confirmResetPasswordBtn">
          <i class="bi bi-key"></i> Reset Password
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Delete Student Confirmation Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1" aria-labelledby="deleteStudentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteStudentModalLabel">Delete Student</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-danger">
          <i class="bi bi-exclamation-triangle"></i>
          <strong>Danger!</strong> This action cannot be undone.
        </div>
        <p>Are you sure you want to delete student <strong id="deleteStudentId"></strong>?</p>
        <p><small class="text-muted">Note: Students with enrollment records cannot be deleted.</small></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
          <i class="bi bi-trash"></i> Delete Student
        </button>
      </div>
    </div>
  </div>
</div>

<script>
// Refresh function
document.getElementById('refreshStudentBtn').addEventListener('click', function() {
    window.location.reload();
});

// View student function
function viewStudent(studentId) {
    // Show loading state immediately
    document.getElementById('viewStudentContent').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Loading student details...</p></div>';
    
    // Show the modal
    var viewModal = new bootstrap.Modal(document.getElementById('viewStudentModal'));
    viewModal.show();
    
    // Fetch student data from backend
    fetch('?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=view&id=' + studentId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })    .then(response => {
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        return response.json();
    })
    .then(result => {
        if (result.status && result.data) {
            loadStudentDetails(result.data);
        } else {
            document.getElementById('viewStudentContent').innerHTML = 
                '<div class="alert alert-danger">Error: ' + (result.message || 'Student not found') + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('viewStudentContent').innerHTML = 
            '<div class="alert alert-danger">Error loading student details</div>';
    });
}

// Load student details into view modal
function loadStudentDetails(studentData) {
    const formatDate = (dateStr) => {
        if (!dateStr) return 'N/A';
        return new Date(dateStr).toLocaleDateString();
    };
    
    const formatStatus = (status) => {
        const statusClass = status === 'active' ? 'success' : 
                          status === 'inactive' ? 'secondary' : 'warning';
        return `<span class="badge bg-${statusClass}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
    };
    
    document.getElementById('viewStudentContent').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="mb-3"><i class="bi bi-person"></i> Personal Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Student ID:</strong></td><td>${studentData.student_id || 'N/A'}</td></tr>
                    <tr><td><strong>Login ID:</strong></td><td>${studentData.login_id || 'N/A'}</td></tr>
                    <tr><td><strong>First Name:</strong></td><td>${studentData.first_name || 'N/A'}</td></tr>
                    <tr><td><strong>Last Name:</strong></td><td>${studentData.last_name || 'N/A'}</td></tr>
                    <tr><td><strong>Email:</strong></td><td>${studentData.email || 'N/A'}</td></tr>
                    <tr><td><strong>Phone:</strong></td><td>${studentData.phone_number || 'N/A'}</td></tr>
                    <tr><td><strong>Date of Birth:</strong></td><td>${formatDate(studentData.date_of_birth)}</td></tr>
                    <tr><td><strong>Gender:</strong></td><td>${studentData.gender ? studentData.gender.charAt(0).toUpperCase() + studentData.gender.slice(1) : 'N/A'}</td></tr>
                    <tr><td><strong>Status:</strong></td><td>${formatStatus(studentData.status || 'active')}</td></tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="mb-3"><i class="bi bi-mortarboard"></i> Academic Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Program:</strong></td><td>${studentData.program_code ? studentData.program_code + ' - ' + (studentData.program_name || '') : 'N/A'}</td></tr>
                    <tr><td><strong>Department:</strong></td><td>${studentData.program_department || 'N/A'}</td></tr>
                    <tr><td><strong>Year Level:</strong></td><td>${studentData.year_level ? studentData.year_level + getSuffix(studentData.year_level) + ' Year' : 'N/A'}</td></tr>
                    <tr><td><strong>Enrollment Date:</strong></td><td>${formatDate(studentData.enrollment_date)}</td></tr>
                    <tr><td><strong>Academic Advisor:</strong></td><td>${studentData.advisor_name || 'N/A'}</td></tr>
                    <tr><td><strong>Advisor Email:</strong></td><td>${studentData.advisor_email || 'N/A'}</td></tr>
                    <tr><td><strong>Last Login:</strong></td><td>${formatDate(studentData.last_login)}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="mb-3"><i class="bi bi-geo-alt"></i> Address Information</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Address:</strong></td><td>${studentData.address || 'N/A'}</td></tr>
                    <tr><td><strong>City:</strong></td><td>${studentData.city || 'N/A'}</td></tr>
                    <tr><td><strong>State/Province:</strong></td><td>${studentData.state || 'N/A'}</td></tr>
                    <tr><td><strong>Postal Code:</strong></td><td>${studentData.postal_code || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="mb-3"><i class="bi bi-telephone"></i> Emergency Contact</h6>
                <table class="table table-sm table-borderless">
                    <tr><td><strong>Contact Name:</strong></td><td>${studentData.emergency_contact_name || 'N/A'}</td></tr>
                    <tr><td><strong>Contact Phone:</strong></td><td>${studentData.emergency_contact_phone || 'N/A'}</td></tr>
                </table>
            </div>
        </div>
    `;
}

// Helper function for year suffix
function getSuffix(num) {
    const j = num % 10;
    const k = num % 100;
    if (j == 1 && k != 11) return 'st';
    if (j == 2 && k != 12) return 'nd';
    if (j == 3 && k != 13) return 'rd';
    return 'th';
}

// Edit student function
function editStudent(studentId) {
    // Show loading in the form (disable fields temporarily)
    const form = document.getElementById('editStudentForm');
    const inputs = form.querySelectorAll('input, select');
    inputs.forEach(input => input.disabled = true);
    
    // Show the modal first
    var editModal = new bootstrap.Modal(document.getElementById('editStudentModal'));
    editModal.show();
    
    // Fetch student data from backend
    fetch('?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=view&id=' + studentId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.status && result.data) {
            populateEditForm(result.data);
        } else {
            alert('Error loading student data: ' + (result.message || 'Student not found'));
            editModal.hide();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error loading student data');
        editModal.hide();
    })
    .finally(() => {
        // Re-enable form fields
        inputs.forEach(input => input.disabled = false);
    });
}

// Populate edit form with student data
function populateEditForm(studentData) {
    document.getElementById('edit_student_id').value = studentData.student_id || '';
    document.getElementById('edit_first_name').value = studentData.first_name || '';
    document.getElementById('edit_last_name').value = studentData.last_name || '';
    document.getElementById('edit_email').value = studentData.email || '';
    document.getElementById('edit_phone_number').value = studentData.phone_number || '';
    document.getElementById('edit_date_of_birth').value = studentData.date_of_birth || '';
    document.getElementById('edit_gender').value = studentData.gender || '';
    document.getElementById('edit_address').value = studentData.address || '';
    document.getElementById('edit_city').value = studentData.city || '';
    document.getElementById('edit_state').value = studentData.state || '';
    document.getElementById('edit_postal_code').value = studentData.postal_code || '';
    document.getElementById('edit_program_id').value = studentData.program_id || '';
    document.getElementById('edit_year_level').value = studentData.year_level || '1';
    document.getElementById('edit_academic_advisor_id').value = studentData.academic_advisor_id || '';
    document.getElementById('edit_status').value = studentData.status || 'active';
    document.getElementById('edit_emergency_contact_name').value = studentData.emergency_contact_name || '';
    document.getElementById('edit_emergency_contact_phone').value = studentData.emergency_contact_phone || '';
}

// Reset password function
function resetPassword(studentId) {
    document.getElementById('resetPasswordStudentId').textContent = studentId;
    document.getElementById('confirmResetPasswordBtn').onclick = function() {
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Resetting...';
        
        fetch('?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=reset_password&id=' + studentId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.status) {
                // Show success message
                showAlert('success', result.message || 'Password reset successfully');
                // Hide modal
                bootstrap.Modal.getInstance(document.getElementById('resetPasswordModal')).hide();
            } else {
                showAlert('danger', result.message || 'Error resetting password');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Error resetting password');
        })
        .finally(() => {
            // Reset button state
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-key"></i> Reset Password';
        });
    };
    
    var resetModal = new bootstrap.Modal(document.getElementById('resetPasswordModal'));
    resetModal.show();
}

// Delete student function
function deleteStudent(studentId) {
    document.getElementById('deleteStudentId').textContent = studentId;
    document.getElementById('confirmDeleteBtn').onclick = function() {
        // Show loading state
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...';
        
        fetch('?page=<?= $_GET['page'] ?>&subpage=<?= $_GET['subpage'] ?>&action=delete&id=' + studentId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.status) {
                // Show success message
                showAlert('success', result.message || 'Student deleted successfully');
                // Hide modal
                bootstrap.Modal.getInstance(document.getElementById('deleteStudentModal')).hide();
                // Refresh the page to update the table
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showAlert('danger', result.message || 'Error deleting student');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'Error deleting student');
        })
        .finally(() => {
            // Reset button state
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-trash"></i> Delete Student';
        });
    };
    
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteStudentModal'));
    deleteModal.show();
}

// Helper function to show alerts
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.setAttribute('role', 'alert');
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    // Clear existing alerts
    alertContainer.innerHTML = '';
    alertContainer.appendChild(alert);
    
    // Auto-hide success alerts after 3 seconds
    if (type === 'success') {        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 3000);
    }
}

// Form submission handlers
document.getElementById('addStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Student added successfully');
});

document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    submitForm(this, 'Student updated successfully');
});

function submitForm(form, successMessage) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    
    // Show loading state
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    const formData = new FormData(form);
    
    fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(result => {
        if (result.status) {
            showAlert('success', result.message || successMessage);
            // Hide modal
            const modal = form.closest('.modal');
            if (modal) {
                bootstrap.Modal.getInstance(modal).hide();
            }
            // Reset form
            form.reset();
            // Refresh the page to update the table
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showAlert('danger', result.message || 'An error occurred');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while saving');
    })
    .finally(() => {
        // Reset button state
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    });
}
</script>