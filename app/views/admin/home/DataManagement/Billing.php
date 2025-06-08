<?php
$bill = process_Billing_Request($pdo);
$staffId = getStaffIdByUserId($userID);
// Automatically update overdue status
update_overdue_status($pdo);
// Get billing data
$billingData = get_BillingData($pdo);
// Get students and terms for dropdowns
$students = get_AllStudents($pdo);
$terms = get_AllTerms($pdo);
?>
</style>

<div>
    <!-- Header -->
    <div class="navbg p-3 rounded rounded-3 shadow-sm">
        <div class="d-flex justify-content-between flex-wrap align-items-center">
            <h5 class="mb-0">Billing Management</h5>
            <div>
                <button type="button" class="btn btn-outline-secondary me-2" id="refreshBillingBtn">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addBillingModal">
                    <i class="bi bi-plus-lg"></i> Post Billing
                </button>
            </div>
        </div>
    </div> <!-- Alert Container for system messages -->
    <div id="alertContainer" class="mt-3">
        <?php if (isset($_SESSION['billing_response']) && !empty($_SESSION['billing_response']['message'])): ?>
            <div id="persistentAlert"
                class="alert <?= $_SESSION['billing_response']['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show"
                role="alert">
                <strong><?= $_SESSION['billing_response']['status'] ? 'Success!' : 'Error!' ?></strong>
                <?= $_SESSION['billing_response']['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['billing_response']); // Clear after displaying ?>
        <?php endif; ?>
    </div><!-- Search and Filter Controls -->
    <div class="card shadow-sm mb-3 mt-3">
        <div class="card-body">
            <form id="billingSearchForm" class="row g-3" method="POST">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Filter by Status</label>
                    <select class="form-select form-select-sm" id="statusFilter" name="statusFilter"
                        onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?= isset($_POST['statusFilter']) && $_POST['statusFilter'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="partial" <?= isset($_POST['statusFilter']) && $_POST['statusFilter'] === 'partial' ? 'selected' : '' ?>>Partial</option>
                        <option value="paid" <?= isset($_POST['statusFilter']) && $_POST['statusFilter'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="overdue" <?= isset($_POST['statusFilter']) && $_POST['statusFilter'] === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="sortBy" class="form-label">Sort By</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select form-select-sm" id="sortBy" name="sortBy"
                            onchange="this.form.submit()">
                            <option value="due_date" <?= isset($_POST['sortBy']) && $_POST['sortBy'] === 'due_date' ? 'selected' : '' ?>>Due Date</option>
                            <option value="amount" <?= isset($_POST['sortBy']) && $_POST['sortBy'] === 'amount' ? 'selected' : '' ?>>Amount</option>
                            <option value="created_at" <?= isset($_POST['sortBy']) && $_POST['sortBy'] === 'created_at' ? 'selected' : '' ?>>Created Date</option>
                            <option value="status" <?= isset($_POST['sortBy']) && $_POST['sortBy'] === 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                        <select class="form-select form-select-sm" id="sortDirection" name="sortDirection"
                            onchange="this.form.submit()">
                            <option value="DESC" <?= isset($_POST['sortDirection']) && $_POST['sortDirection'] === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="ASC" <?= isset($_POST['sortDirection']) && $_POST['sortDirection'] === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="searchStudent" class="form-label">Search Student</label>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control" id="searchStudent" name="searchStudent"
                            value="<?= isset($_POST['searchStudent']) ? htmlspecialchars($_POST['searchStudent']) : '' ?>"
                            placeholder="Search by ID, First Name, Last Name">
                        <button type="submit" class="btn btn-outline-success">
                            <i class="bi bi-search"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div><!-- Table -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">ID</th>
                            <th>Student<br><small class="text-muted">ID & Name</small></th>
                            <th>Term<br><small class="text-muted">ID & Name</small></th>
                            <th class="text-end">Amount</th>
                            <th class="text-center">Due Date</th>
                            <th>Description</th>
                            <th class="text-center">Status</th>
                            <th>Posted By<br><small class="text-muted">ID & Name</small></th>
                            <th class="text-center">Created At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="billingTableBody">
                        <!-- PHP Loop Example --> <?php if (count($billingData) > 0): ?>
                            <?php foreach ($billingData as $row):
                                $today = date('Y-m-d');
                                $isOverdue = ($row['status'] !== 'paid' && $today > $row['due_date']);
                                ?>
                                <tr class="<?= $isOverdue && $row['status'] !== 'overdue' ? 'table-danger' : '' ?>"
                                    data-student-id="<?= htmlspecialchars($row['student_id']) ?>"
                                    data-student-name="<?= htmlspecialchars($row['student_name'] ?? '') ?>"
                                    data-term-id="<?= htmlspecialchars($row['term_id']) ?>"
                                    data-term-name="<?= htmlspecialchars($row['term_name'] ?? '') ?>"
                                    data-staff-id="<?= htmlspecialchars($row['created_by']) ?>"
                                    data-staff-name="<?= htmlspecialchars($row['staff_name'] ?? '') ?>">
                                    <td class="text-center"><?= htmlspecialchars($row['billing_id']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['student_id']) ?></strong><br>
                                        <span
                                            class="text-muted"><?= htmlspecialchars($row['student_name'] ?? 'Unknown Student') ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['term_id']) ?></strong><br>
                                        <span
                                            class="text-muted"><?= htmlspecialchars($row['term_name'] ?? 'Unknown Term') ?></span>
                                    </td>
                                    <td class="text-end fw-bold">₱ <?= number_format($row['amount'], 2) ?></td>
                                    <td class="text-center">
                                        <?= date('M d, Y', strtotime($row['due_date'])) ?>
                                        <?php if ($isOverdue && $row['status'] !== 'paid'): ?>
                                            <br><span class="badge bg-danger">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;" data-bs-toggle="tooltip"
                                            title="<?= htmlspecialchars($row['description']) ?>">
                                            <?= htmlspecialchars($row['description'] ?: 'No description') ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-<?= match ($row['status']) {
                                            'pending' => 'warning',
                                            'partial' => 'info',
                                            'paid' => 'success',
                                            'overdue' => 'danger',
                                            default => 'secondary',
                                        } ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['created_by']) ?></strong><br>
                                        <span
                                            class="text-muted"><?= htmlspecialchars($row['staff_name'] ?? 'Unknown Staff') ?></span>
                                    </td>
                                    <td class="text-center"><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm"> <button type="button"
                                                class="btn btn-outline-primary viewBillingBtn" data-bs-toggle="modal"
                                                data-bs-target="#viewBillingModal"
                                                data-billingid="<?= htmlspecialchars($row['billing_id']) ?>"
                                                data-studentid="<?= htmlspecialchars($row['student_id']) ?>"
                                                data-student-name="<?= htmlspecialchars($row['student_name'] ?? '') ?>"
                                                data-termid="<?= htmlspecialchars($row['term_id']) ?>"
                                                data-term-name="<?= htmlspecialchars($row['term_name'] ?? '') ?>"
                                                data-amount="<?= htmlspecialchars($row['amount']) ?>"
                                                data-duedate="<?= htmlspecialchars($row['due_date']) ?>"
                                                data-description="<?= htmlspecialchars($row['description']) ?>"
                                                data-status="<?= htmlspecialchars($row['status']) ?>"
                                                data-createdby="<?= htmlspecialchars($row['created_by']) ?>"
                                                data-staff-name="<?= htmlspecialchars($row['staff_name'] ?? '') ?>"
                                                data-createdat="<?= htmlspecialchars($row['created_at']) ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <button type="button" class="btn btn-outline-secondary edit-billing-btn"
                                                data-bs-toggle="modal" data-bs-target="#editBillingModal"
                                                data-billingid="<?= htmlspecialchars($row['billing_id']) ?>"
                                                data-studentid="<?= htmlspecialchars($row['student_id']) ?>"
                                                data-termid="<?= htmlspecialchars($row['term_id']) ?>"
                                                data-amount="<?= htmlspecialchars($row['amount']) ?>"
                                                data-duedate="<?= htmlspecialchars($row['due_date']) ?>"
                                                data-description="<?= htmlspecialchars($row['description']) ?>"
                                                data-status="<?= htmlspecialchars($row['status']) ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            <a href="?page=Manage&subpage=Billing&action=delete&id=<?= htmlspecialchars($row['billing_id']) ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this billing record?');">
                                                <i class="bi bi-trash"></i>
                                            </a>

                                            <?php if ($row['status'] !== 'paid'): ?>
                                                <a href="?page=Manage&subpage=Billing&action=markPaid&id=<?= htmlspecialchars($row['billing_id']) ?>"
                                                    class="btn btn-success" onclick="return confirm('Mark this billing as paid?');">
                                                    <i class="bi bi-check-lg"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-4">
                                    <div class="d-flex flex-column align-items-center">
                                        <i class="bi bi-search fs-1 text-muted mb-2"></i>
                                        <p class="mb-0">No Billing found.</p>
                                        <p class="text-muted small">Try adjusting your search criteria or add a new billing
                                            record.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <small class="text-muted">Showing <?= count($billingData) ?> billing records</small>
                </div>
                <div>
                    <!-- Pagination could be added here if needed -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- View Billing Modal -->
<div class="modal fade" id="viewBillingModal" tabindex="-1" aria-labelledby="viewBillingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title" id="viewBillingModalLabel">Billing Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="fw-bold">Billing Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Billing ID:</td>
                                <td id="view_billing_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Student:</td>
                                <td id="view_student_id"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Term:</td>
                                <td id="view_term_id"></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="fw-bold">Payment Information</h6>
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted" style="width: 40%">Amount:</td>
                                <td id="view_amount" class="fw-bold"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Due Date:</td>
                                <td id="view_due_date"></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td><span id="view_status" class="badge"></span></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card bg-light mb-3">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">Description</h6>
                        <p id="view_description" class="mb-0"></p>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><small class="text-muted">Posted By:</small> <span id="view_created_by"></span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><small class="text-muted">Created At:</small> <span id="view_created_at"></span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary editFromViewBtn" data-bs-toggle="modal"
                    data-bs-target="#editBillingModal">Edit</button>
            </div>
        </div>
    </div>
</div>

<!-- Post Billing Modal -->
<div class="modal fade" id="addBillingModal" tabindex="-1" aria-labelledby="addBillingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title">Post New Billing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addBillingForm" method="POST" action="?page=Manage&subpage=Billing&action=add"
                    class="needs-validation" novalidate>
                    <input type="hidden" name="created_by" value="<?php echo $staffId ?>">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="student_id" class="form-label">Student <span
                                    class="text-danger">*</span></label>
                            <select id="student_id" name="student_id" class="form-select" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?= htmlspecialchars($student['student_id']) ?>">
                                        <?= htmlspecialchars($student['student_id'] . ' - ' . $student['student_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a student.</div>
                        </div>
                        <div class="col-md-6">
                            <label for="term_id" class="form-label">Term <span class="text-danger">*</span></label>
                            <select id="term_id" name="term_id" class="form-select" required>
                                <option value="">Select Term</option>
                                <?php foreach ($terms as $term): ?>
                                    <option value="<?= htmlspecialchars($term['term_id']) ?>">
                                        <?= htmlspecialchars($term['term_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Please select a term.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="amount" class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" id="amount" name="amount" class="form-control"
                                    required>
                                <div class="invalid-feedback">Please provide a valid amount.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" id="due_date" name="due_date" class="form-control"
                                value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                            <div class="invalid-feedback">Please provide a due date.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"
                            placeholder="Enter billing details..."></textarea>
                        <div class="form-text">Optional: Add details about this billing.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="addBillingForm" class="btn btn-success">Post Billing</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Billing Modal (dynamic fill) -->
<div class="modal fade" id="editBillingModal" tabindex="-1" aria-labelledby="editBillingModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header navbg">
                <h5 class="modal-title">Edit Billing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="editBillingForm" method="POST" action="?page=Manage&subpage=Billing&action=edit"
                    class="needs-validation" novalidate>
                    <input type="hidden" name="billing_id" id="editBillingID">
                    <input type="hidden" name="student_id" id="editStudentID">
                    <input type="hidden" name="term_id" id="editTermID">

                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Editing billing for Student: <strong
                            id="displayStudentID"></strong>, Term: <strong id="displayTermID"></strong>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="editAmount" class="form-label">Amount <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₱</span>
                                <input type="number" step="0.01" min="0" name="amount" id="editAmount"
                                    class="form-control" required>
                                <div class="invalid-feedback">Please provide a valid amount.</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="editDueDate" class="form-label">Due Date <span
                                    class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="editDueDate" class="form-control" required>
                            <div class="invalid-feedback">Please provide a due date.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editDescription" class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="editStatus" class="form-label">Status <span class="text-danger">*</span></label>
                        <select name="status" id="editStatus" class="form-select" required>
                            <option value="pending">Pending</option>
                            <option value="partial">Partial</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                        <div class="invalid-feedback">Please select a status.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="editBillingForm" class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<!-- Script for Billing Functionality -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize tooltips
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        // Handle persistent alerts with auto-hide after a longer period
        const persistentAlert = document.getElementById('persistentAlert');
        if (persistentAlert) {
            // Add animation to make the alert more noticeable
            persistentAlert.style.animation = 'fadeInDown 0.5s';

            // Set a timer to auto-hide the alert after 10 seconds
            setTimeout(function () {
                const bsAlert = new bootstrap.Alert(persistentAlert);
                bsAlert.close();
            }, 10000); // 10 seconds
        }
        // Form validation for all forms
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });
        // Edit button functionality
        document.querySelectorAll('.edit-billing-btn').forEach(button => {
            button.addEventListener('click', function () {
                // Use getAttribute to ensure consistent access to data attributes
                document.getElementById('editBillingID').value = this.getAttribute('data-billingid');
                document.getElementById('editStudentID').value = this.getAttribute('data-studentid');
                document.getElementById('editTermID').value = this.getAttribute('data-termid');
                document.getElementById('displayStudentID').textContent = this.getAttribute('data-studentid');
                document.getElementById('displayTermID').textContent = this.getAttribute('data-termid');
                document.getElementById('editAmount').value = this.getAttribute('data-amount');
                document.getElementById('editDueDate').value = formatDateForInput(this.getAttribute('data-duedate'));
                document.getElementById('editDescription').value = this.getAttribute('data-description') || '';
                document.getElementById('editStatus').value = this.getAttribute('data-status');

                console.log('Edit billing data loaded:', {
                    billingId: this.getAttribute('data-billingid'),
                    studentId: this.getAttribute('data-studentid'),
                    termId: this.getAttribute('data-termid'),
                    amount: this.getAttribute('data-amount'),
                    dueDate: this.getAttribute('data-duedate'),
                    description: this.getAttribute('data-description'),
                    status: this.getAttribute('data-status')
                });
            });
        });

        // View billing details
        document.querySelectorAll('.viewBillingBtn').forEach(button => {
            button.addEventListener('click', function () {
                const billingId = this.getAttribute('data-billingid');
                const studentId = this.getAttribute('data-studentid');
                const termId = this.getAttribute('data-termid');
                const amount = this.getAttribute('data-amount');
                const dueDate = this.getAttribute('data-duedate');
                const description = this.getAttribute('data-description');
                const status = this.getAttribute('data-status');
                const createdBy = this.getAttribute('data-createdby');
                const createdAt = this.getAttribute('data-createdat');
                console.log('View billing data loaded:', {
                    billingId, studentId, termId, amount, dueDate, description, status, createdBy, createdAt
                });
                // Make sure all elements exist before trying to set their content
                if (document.getElementById('view_billing_id')) {
                    document.getElementById('view_billing_id').textContent = billingId || 'N/A';
                } if (document.getElementById('view_student_id')) {
                    // Get student name directly from button attribute
                    const studentName = this.getAttribute('data-student-name');

                    document.getElementById('view_student_id').innerHTML = studentName ?
                        `<strong>${studentName}</strong><br><small class="text-muted">${studentId}</small>` :
                        studentId || 'N/A';
                } if (document.getElementById('view_term_id')) {
                    // Get term name directly from button attribute
                    const termName = this.getAttribute('data-term-name');

                    document.getElementById('view_term_id').innerHTML = termName ?
                        `<strong>${termName}</strong><br><small class="text-muted">${termId}</small>` :
                        termId || 'N/A';
                }

                if (document.getElementById('view_amount')) {
                    const formattedAmount = amount ?
                        '₱ ' + parseFloat(amount).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) :
                        'N/A';
                    document.getElementById('view_amount').textContent = formattedAmount;
                }

                if (document.getElementById('view_due_date')) {
                    document.getElementById('view_due_date').textContent = dueDate ? formatDate(dueDate) : 'N/A';
                }

                if (document.getElementById('view_description')) {
                    document.getElementById('view_description').textContent = description || 'No description provided';
                } if (document.getElementById('view_created_by')) {
                    // Get staff name directly from button attribute
                    const staffName = this.getAttribute('data-staff-name');

                    document.getElementById('view_created_by').innerHTML = staffName ?
                        `<strong>${staffName}</strong><br><small class="text-muted">${createdBy}</small>` :
                        createdBy || 'N/A';
                }

                if (document.getElementById('view_created_at')) {
                    document.getElementById('view_created_at').textContent = createdAt ? formatDate(createdAt) : 'N/A';
                }
                // Set status badge
                const statusBadge = document.getElementById('view_status');
                if (statusBadge && status) {
                    statusBadge.textContent = status.charAt(0).toUpperCase() + status.slice(1);

                    switch (status) {
                        case 'pending':
                            statusBadge.className = 'badge bg-warning';
                            break;
                        case 'partial':
                            statusBadge.className = 'badge bg-info';
                            break;
                        case 'paid':
                            statusBadge.className = 'badge bg-success';
                            break;
                        case 'overdue':
                            statusBadge.className = 'badge bg-danger';
                            break;
                        default:
                            statusBadge.className = 'badge bg-secondary';
                    }
                }

                // Check if overdue
                if (dueDate && document.getElementById('view_due_date')) {
                    const today = new Date();
                    const dueDateObj = new Date(dueDate);
                    if (status !== 'paid' && today > dueDateObj) {
                        document.getElementById('view_due_date').innerHTML = formatDate(dueDate) + ' <span class="badge bg-danger">Overdue</span>';
                    }
                }
            });
        });

        // Edit from View button
        const editFromViewBtn = document.querySelector('.editFromViewBtn');
        if (editFromViewBtn) {
            editFromViewBtn.addEventListener('click', function () {
                // Close the view modal manually
                const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewBillingModal'));
                viewModal.hide();
            });
        }
        // Refresh button functionality
        const refreshBtn = document.getElementById('refreshBillingBtn');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', function () {
                window.location.href = '?page=Manage&subpage=Billing';
            });
        }      // Filter dropdowns auto-submit
        const statusFilter = document.getElementById('statusFilter');
        const sortByFilter = document.getElementById('sortBy');
        const sortDirectionFilter = document.getElementById('sortDirection');

        // Add event listeners to filters
        if (statusFilter) {
            statusFilter.addEventListener('change', function () {
                sessionStorage.setItem('billingStatusFilter', this.value);
                document.getElementById('billingSearchForm').submit();
            });
        }

        if (sortByFilter) {
            sortByFilter.addEventListener('change', function () {
                sessionStorage.setItem('billingSortBy', this.value);
                document.getElementById('billingSearchForm').submit();
            });
        }

        if (sortDirectionFilter) {
            sortDirectionFilter.addEventListener('change', function () {
                sessionStorage.setItem('billingSortDirection', this.value);
                document.getElementById('billingSearchForm').submit();
            });
        }

        // Form handling
        const searchForm = document.getElementById('billingSearchForm');
        if (searchForm) {
            // When the form is submitted, store the filter values in session storage
            searchForm.addEventListener('submit', function () {
                const searchStudent = document.getElementById('searchStudent');
                if (searchStudent) {
                    sessionStorage.setItem('billingSearchStudent', searchStudent.value);
                }

                // The remaining filter values are already stored in their respective change event handlers
            });

            // On page load, check if we have stored filter values and pre-select them
            // Only restore values from POST parameters, not session storage
            if (statusFilter) {
                const currentStatus = '<?= isset($_POST["statusFilter"]) ? $_POST["statusFilter"] : "" ?>';
                if (currentStatus) {
                    statusFilter.value = currentStatus;
                    sessionStorage.setItem('billingStatusFilter', currentStatus);
                }
            }

            if (sortByFilter) {
                const currentSortBy = '<?= isset($_POST["sortBy"]) ? $_POST["sortBy"] : "due_date" ?>';
                if (currentSortBy) {
                    sortByFilter.value = currentSortBy;
                    sessionStorage.setItem('billingSortBy', currentSortBy);
                }
            }

            if (sortDirectionFilter) {
                const currentSortDirection = '<?= isset($_POST["sortDirection"]) ? $_POST["sortDirection"] : "DESC" ?>';
                if (currentSortDirection) {
                    sortDirectionFilter.value = currentSortDirection;
                    sessionStorage.setItem('billingSortDirection', currentSortDirection);
                }
            }

            const searchStudent = document.getElementById('searchStudent');
            if (searchStudent) {
                const currentSearch = '<?= isset($_POST["searchStudent"]) ? htmlspecialchars($_POST["searchStudent"]) : "" ?>';
                if (currentSearch) {
                    searchStudent.value = currentSearch;
                    sessionStorage.setItem('billingSearchStudent', currentSearch);
                }
            }
        }
        // Helper functions
        function formatDate(dateString) {
            if (!dateString) return '—';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    console.error('Invalid date:', dateString);
                    return '—';
                }
                const options = { year: 'numeric', month: 'short', day: 'numeric' };
                return date.toLocaleDateString('en-US', options);
            } catch (error) {
                console.error('Error formatting date:', error);
                return '—';
            }
        }
        function formatDateForInput(dateString) {
            if (!dateString) return '';
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    console.error('Invalid date for input:', dateString);
                    return '';
                }

                // Get year, month and day parts
                const year = date.getFullYear();
                // Month is 0-indexed, so add 1 and pad with leading zero if needed
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                // Pad day with leading zero if needed
                const day = date.getDate().toString().padStart(2, '0');

                // Format as YYYY-MM-DD
                return `${year}-${month}-${day}`;
            } catch (error) {
                console.error('Error formatting date for input:', error);
                return '';
            }
        }
        <?php
        // If a redirect is required after processing actions, do it via JavaScript
        if (isset($bill['redirect']) && $bill['redirect'] === true):
            ?>
            // Add a delay before redirecting to allow the user to see the notification
            setTimeout(function () {
                window.location.href = 'dashboard.php?page=Manage&subpage=Billing';
            }, 2500); // Wait 2.5 seconds before redirecting
        <?php endif; ?>
    });
</script>