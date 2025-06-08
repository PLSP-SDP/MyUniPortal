<?php
$announcement_response = process_Announcement_Request($pdo);
$staffId = getStaffIdByUserId($userID);

// Debug filter values
$visibility_filter = isset($_GET['visibility']) ? $_GET['visibility'] : 'not set';
$role_filter = isset($_GET['role']) ? $_GET['role'] : 'not set';

// Get announcement data
$Announcement_data = data_Announcement($pdo);
?>

<div>
  <!-- Header -->
  <div class="navbg p-3 rounded rounded-3 shadow-sm">
    <div class="d-flex justify-content-between flex-wrap align-items-center">
      <h5 class="mb-0">Announcement Management</h5>
      <div>
        <button type="button" class="btn btn-outline-secondary me-2" id="refreshAnnouncementBtn">
          <i class="bi bi-arrow-clockwise"></i> Refresh
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEnrollmentModal">
          <i class="bi bi-plus-lg"></i> Post Announcement
        </button>
      </div>
    </div>
  </div>

  <!-- Alert Container for system messages -->
  <div id="alertContainer" class="mt-3">
    <?php if (!empty($announcement_response['message'])): ?>
      <div
        class="alert <?= $announcement_response['status'] ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show"
        role="alert">
        <?= $announcement_response['message'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
      </div>
    <?php endif; ?>
  </div>
  <!-- Debug Info -->
  <?php if (isset($_GET['visibility']) || isset($_GET['role'])): ?>
    <div class="alert alert-info alert-dismissible fade show" role="alert">
      <small>
        <strong>Filters applied:</strong>
        <?php if (isset($_GET['visibility'])): ?>
          Visibility: <span class="badge bg-secondary"><?= $_GET['visibility'] === '1' ? 'Public' : 'Private' ?></span>
        <?php endif; ?>

        <?php if (isset($_GET['role'])): ?>
          | Target: <span class="badge bg-secondary"><?= ucfirst($_GET['role']) ?></span>
        <?php endif; ?>

        <span class="ms-2">
          (Showing <?= count($Announcement_data) ?> filtered announcements)
        </span>
      </small>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Search and Filter Controls -->
  <div class="card shadow-sm mb-3 mt-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <label for="filterVisibility" class="form-label">Filter by Visibility</label>
          <select class="form-select form-select-sm" id="filterVisibility">
            <option value="">All</option>
            <option value="1" <?= isset($_GET['visibility']) && $_GET['visibility'] === '1' ? 'selected' : '' ?>>Public
            </option>
            <option value="0" <?= isset($_GET['visibility']) && $_GET['visibility'] === '0' ? 'selected' : '' ?>>Private
            </option>
          </select>
        </div>
        <div class="col-md-6">
          <label for="filterRole" class="form-label">Filter by Target</label>
          <select class="form-select form-select-sm" id="filterRole">
            <option value="">All Targets</option>
            <option value="all" <?= isset($_GET['role']) && $_GET['role'] === 'all' ? 'selected' : '' ?>>All Users</option>
            <option value="students" <?= isset($_GET['role']) && $_GET['role'] === 'students' ? 'selected' : '' ?>>Students
              Only</option>
            <option value="staff" <?= isset($_GET['role']) && $_GET['role'] === 'staff' ? 'selected' : '' ?>>Staff Only
            </option>
          </select>
        </div>
      </div>
    </div>
  </div> <!-- Table -->

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>ID</th>
              <th>Title</th>
              <th>Content</th>
              <th>Created By</th>
              <th>Created At</th>
              <th>Date Range</th>
              <th>Visibility/Target</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (count($Announcement_data) > 0): ?>
              <?php foreach ($Announcement_data as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['announcement_id']) ?></td>
                  <td class="fw-bold"><?= htmlspecialchars($row['title']) ?></td>
                  <td>
                    <div class="text-truncate" style="max-width: 200px;" data-bs-toggle="tooltip"
                      title="<?= htmlspecialchars($row['content']) ?>">
                      <?= htmlspecialchars(substr($row['content'], 0, 50)) . (strlen($row['content']) > 50 ? '...' : '') ?>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($row['created_by_name'] ?? $row['created_by']) ?></td>
                  <td><?= date('M d, Y', strtotime($row['created_at'])) ?></td>
                  <td>
                    <?php if ($row['start_date'] && $row['end_date']): ?>
                      <?= date('M d', strtotime($row['start_date'])) ?> - <?= date('M d, Y', strtotime($row['end_date'])) ?>
                    <?php elseif ($row['start_date']): ?>
                      From <?= date('M d, Y', strtotime($row['start_date'])) ?>
                    <?php elseif ($row['end_date']): ?>
                      Until <?= date('M d, Y', strtotime($row['end_date'])) ?>
                    <?php else: ?>
                      No date restriction
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= $row['is_public'] == 1 ? 'success' : 'secondary' ?>">
                      <?= $row['is_public'] == 1 ? 'Public' : 'Private' ?>
                    </span>
                    <?php if ($row['target_role'] != 'all'): ?>
                      <br>
                      <small class="text-muted"><?= ucfirst($row['target_role']) ?> only</small>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group btn-group-sm">
                      <button type="button" class="btn btn-outline-primary viewAnnouncementBtn" data-bs-toggle="modal"
                        data-bs-target="#viewAnnouncementModal"
                        data-announcementid="<?= htmlspecialchars($row['announcement_id']) ?>"
                        data-title="<?= htmlspecialchars($row['title']) ?>"
                        data-content="<?= htmlspecialchars($row['content']) ?>"
                        data-createdby="<?= htmlspecialchars($row['created_by_name'] ?? $row['created_by']) ?>"
                        data-createdat="<?= htmlspecialchars($row['created_at']) ?>"
                        data-startdate="<?= htmlspecialchars($row['start_date'] ?? '') ?>"
                        data-enddate="<?= htmlspecialchars($row['end_date'] ?? '') ?>"
                        data-ispublic="<?= htmlspecialchars($row['is_public']) ?>"
                        data-targetrole="<?= htmlspecialchars($row['target_role']) ?>">
                        <i class="bi bi-eye"></i>
                      </button>
                      <button type="button" class="btn btn-outline-success editAnnouncementBtn" data-bs-toggle="modal"
                        data-bs-target="#editAnnouncementModal"
                        data-announcementid="<?= htmlspecialchars($row['announcement_id']) ?>"
                        data-title="<?= htmlspecialchars($row['title']) ?>"
                        data-content="<?= htmlspecialchars($row['content']) ?>"
                        data-createdby="<?= htmlspecialchars($row['created_by_name'] ?? $row['created_by']) ?>"
                        data-createdat="<?= htmlspecialchars($row['created_at']) ?>"
                        data-startdate="<?= htmlspecialchars($row['start_date'] ?? '') ?>"
                        data-enddate="<?= htmlspecialchars($row['end_date'] ?? '') ?>"
                        data-ispublic="<?= htmlspecialchars($row['is_public']) ?>"
                        data-targetrole="<?= htmlspecialchars($row['target_role']) ?>">
                        <i class="bi bi-pencil"></i>
                      </button>
                      <a href="?page=Manage&subpage=Announcement&action=delete&id=<?= htmlspecialchars($row['announcement_id']) ?>"
                        class="btn btn-outline-danger"
                        onclick="return confirm('Are you sure you want to delete this announcement?')">
                        <i class="bi bi-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>

            <?php else: ?>
              <tr>
                <td colspan="8" class="text-center py-4">
                  <div class="d-flex flex-column align-items-center">
                    <i class="bi bi-search fs-1 text-muted mb-2"></i>
                    <p class="mb-0">No Announcement found.</p>
                    <p class="text-muted small">Try adjusting your search criteria or add a new Announcement.</p>
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
          <small class="text-muted">Showing <?= count($Announcement_data) ?> announcements</small>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- View Announcement Modal -->
<div class="modal fade" id="viewAnnouncementModal" tabindex="-1" aria-labelledby="viewAnnouncementModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header navbg">
        <h5 class="modal-title" id="viewAnnouncementModalLabel">Announcement Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row mb-4">
          <div class="col-md-8">
            <h5 id="view_title" class="fw-bold"></h5>
            <p class="text-muted small">Announcement ID: <span id="view_announcement_id" class="fw-bold"></span></p>
          </div>
          <div class="col-md-4 text-end">
            <span id="view_visibility" class="badge bg-success mb-2 fs-6"></span>
            <div id="view_target_badge" class="mt-2"></div>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-12">
            <div class="card">
              <div class="card-body">
                <div class="mb-1 text-muted small">Content:</div>
                <p id="view_content" style="white-space: pre-wrap;" class="p-3 bg-light rounded"></p>
              </div>
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <h6 class="fw-bold">Publication Information</h6>
            <table class="table table-sm table-borderless">
              <tr>
                <td class="text-muted" style="width: 40%">Start Date:</td>
                <td id="view_start_date"></td>
              </tr>
              <tr>
                <td class="text-muted">End Date:</td>
                <td id="view_end_date"></td>
              </tr>
              <tr>
                <td class="text-muted">Target Audience:</td>
                <td id="view_target"></td>
              </tr>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold">Author Information</h6>
            <table class="table table-sm table-borderless">
              <tr>
                <td class="text-muted" style="width: 40%">Created By:</td>
                <td id="view_created_by"></td>
              </tr>
              <tr>
                <td class="text-muted">Created At:</td>
                <td id="view_created_at"></td>
              </tr>
            </table>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary editFromViewBtn" data-bs-toggle="modal"
          data-bs-target="#editAnnouncementModal">Edit</button>
      </div>
    </div>
  </div>
</div>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addEnrollmentModal" tabindex="-1" aria-labelledby="addEnrollmentModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header navbg">
        <h5 class="modal-title" id="addEnrollmentModalLabel">Post New Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="addAnnouncementForm" method="post" action="?page=Manage&subpage=Announcement&action=add"
          class="needs-validation" novalidate>
          <!-- Hidden staff_id input -->
          <input type="hidden" name="staff_id" value="<?php echo $staffId ?>">

          <div class="row mb-3">
            <div class="col-12">
              <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="title" name="title" required>
              <div class="invalid-feedback">Please provide a title for the announcement.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-12">
              <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
              <textarea class="form-control" id="content" name="content" rows="5" required></textarea>
              <div class="invalid-feedback">Please provide content for the announcement.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="start_date" name="start_date" value="<?= date('Y-m-d') ?>"
                required>
              <div class="invalid-feedback">Please provide a start date.</div>
            </div>
            <div class="col-md-6">
              <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="end_date" name="end_date"
                value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
              <div class="invalid-feedback">Please provide an end date.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="is_public" class="form-label">Visibility <span class="text-danger">*</span></label>
              <select class="form-select" id="is_public" name="is_public" required>
                <option value="1">Public</option>
                <option value="0">Private</option>
              </select>
              <div class="invalid-feedback">Please select visibility setting.</div>
            </div>
            <div class="col-md-6">
              <label for="target_role" class="form-label">Target Role <span class="text-danger">*</span></label>
              <select class="form-select" id="target_role" name="target_role" required>
                <option value="all">All</option>
                <option value="students">Students</option>
                <option value="staff">Staff</option>
              </select>
              <div class="invalid-feedback">Please select a target role.</div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="addAnnouncementForm" class="btn btn-success">Post Announcement</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1" aria-labelledby="editAnnouncementModalLabel"
  aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header navbg">
        <h5 class="modal-title" id="editAnnouncementModalLabel">Edit Announcement</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editAnnouncementForm" method="post" action="?page=Manage&subpage=Announcement&action=update"
          class="needs-validation" novalidate>
          <input type="hidden" id="edit_announcement_id" name="announcement_id">

          <div class="row mb-3">
            <div class="col-12">
              <label for="edit_title" class="form-label">Title <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="edit_title" name="title" required>
              <div class="invalid-feedback">Please provide a title for the announcement.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-12">
              <label for="edit_content" class="form-label">Content <span class="text-danger">*</span></label>
              <textarea class="form-control" id="edit_content" name="content" rows="5" required></textarea>
              <div class="invalid-feedback">Please provide content for the announcement.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="edit_start_date" name="start_date" required>
              <div class="invalid-feedback">Please provide a start date.</div>
            </div>
            <div class="col-md-6">
              <label for="edit_end_date" class="form-label">End Date <span class="text-danger">*</span></label>
              <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
              <div class="invalid-feedback">Please provide an end date.</div>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label for="edit_is_public" class="form-label">Visibility <span class="text-danger">*</span></label>
              <select class="form-select" id="edit_is_public" name="is_public" required>
                <option value="1">Public</option>
                <option value="0">Private</option>
              </select>
              <div class="invalid-feedback">Please select visibility setting.</div>
            </div>
            <div class="col-md-6">
              <label for="edit_target_role" class="form-label">Target Role <span class="text-danger">*</span></label>
              <select class="form-select" id="edit_target_role" name="target_role" required>
                <option value="all">All</option>
                <option value="students">Students</option>
                <option value="staff">Staff</option>
              </select>
              <div class="invalid-feedback">Please select a target role.</div>
            </div>
          </div>
          <div class="row mt-4">
            <div class="col-12">
              <div class="card bg-light">
                <div class="card-body">
                  <h6 class="card-subtitle mb-2 text-muted">Announcement Information</h6>
                  <div class="row">
                    <div class="col-md-6">
                      <p class="mb-1"><strong>Announcement ID:</strong> <span id="display_announcement_id"
                          class="badge bg-secondary"></span></p>
                      <p class="mb-1"><strong>Created By:</strong> <span id="display_created_by"></span></p>
                      <p class="mb-1"><strong>Created At:</strong> <span id="display_created_at"></span></p>
                    </div>
                    <div class="col-md-6">
                      <p class="mb-1"><strong>Current Status:</strong>
                        <span id="display_visibility" class="badge"></span>
                        <span id="display_target" class="ms-2 badge bg-light text-dark"></span>
                      </p>
                      <p class="mb-1"><strong>Current Date Range:</strong> <span id="display_date_range"></span></p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" form="editAnnouncementForm" class="btn btn-primary">Update Announcement</button>
      </div>
    </div>
  </div>
</div>

<!-- JavaScript to handle the functionality -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Initialize tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

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
    });    // Initialize filter elements
    const filterVisibility = document.getElementById('filterVisibility');
    const filterRole = document.getElementById('filterRole');
    const refreshBtn = document.getElementById('refreshAnnouncementBtn');    // Set filters based on URL parameters or restore from session storage
    const urlParams = new URLSearchParams(window.location.search);

    // First check URL parameters
    if (urlParams.has('visibility') && filterVisibility) {
      filterVisibility.value = urlParams.get('visibility');
    }
    // If no URL parameters, check session storage
    else if (filterVisibility && sessionStorage.getItem('announcementVisibility')) {
      filterVisibility.value = sessionStorage.getItem('announcementVisibility');
    }

    if (urlParams.has('role') && filterRole) {
      filterRole.value = urlParams.get('role');
    }
    else if (filterRole && sessionStorage.getItem('announcementRole')) {
      filterRole.value = sessionStorage.getItem('announcementRole');
    }

    // If we have session storage values but no URL parameters, apply the filters
    if (!urlParams.has('visibility') && !urlParams.has('role') &&
      ((filterVisibility && filterVisibility.value) || (filterRole && filterRole.value))) {
      // Use setTimeout to ensure DOM is fully loaded
      setTimeout(applyFilters, 100);
    }    // Handle edit announcement modal
    const editAnnouncementModal = document.getElementById('editAnnouncementModal');
    if (editAnnouncementModal) {
      editAnnouncementModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;

        // Extract data from data attributes
        const announcementId = button.getAttribute('data-announcementid');
        const title = button.getAttribute('data-title');
        const content = button.getAttribute('data-content');
        const createdBy = button.getAttribute('data-createdby');
        const createdAt = button.getAttribute('data-createdat');
        const startDate = button.getAttribute('data-startdate');
        const endDate = button.getAttribute('data-enddate');
        const isPublic = button.getAttribute('data-ispublic');
        const targetRole = button.getAttribute('data-targetrole');

        // Update the modal's content
        const modal = editAnnouncementModal;
        modal.querySelector('#edit_announcement_id').value = announcementId;
        modal.querySelector('#edit_title').value = title;
        modal.querySelector('#edit_content').value = content;
        modal.querySelector('#edit_start_date').value = formatDateForInput(startDate);
        modal.querySelector('#edit_end_date').value = formatDateForInput(endDate);
        modal.querySelector('#edit_is_public').value = isPublic;
        modal.querySelector('#edit_target_role').value = targetRole;

        // Update the display information
        modal.querySelector('#display_announcement_id').textContent = announcementId;
        modal.querySelector('#display_created_by').textContent = createdBy;
        modal.querySelector('#display_created_at').textContent = formatDate(createdAt);

        // Set visibility badge
        const visibilityBadge = modal.querySelector('#display_visibility');
        visibilityBadge.textContent = isPublic === '1' ? 'Public' : 'Private';
        visibilityBadge.className = isPublic === '1' ? 'badge bg-success' : 'badge bg-secondary';

        // Set target badge
        modal.querySelector('#display_target').textContent = capitalize(targetRole);

        // Format date range display
        let dateRangeText = '';
        if (startDate && endDate) {
          dateRangeText = formatDate(startDate) + ' to ' + formatDate(endDate);
        } else if (startDate) {
          dateRangeText = 'From ' + formatDate(startDate);
        } else if (endDate) {
          dateRangeText = 'Until ' + formatDate(endDate);
        } else {
          dateRangeText = 'No date restriction';
        }
        modal.querySelector('#display_date_range').textContent = dateRangeText;
      });
    }    // Handle view announcement modal
    const viewAnnouncementModal = document.getElementById('viewAnnouncementModal');
    if (viewAnnouncementModal) {
      const viewButtons = document.querySelectorAll('.viewAnnouncementBtn');
      Array.from(viewButtons).forEach(button => {
        button.addEventListener('click', function () {
          const announcementId = this.getAttribute('data-announcementid');
          const title = this.getAttribute('data-title');
          const content = this.getAttribute('data-content');
          const createdBy = this.getAttribute('data-createdby');
          const createdAt = this.getAttribute('data-createdat');
          const startDate = this.getAttribute('data-startdate');
          const endDate = this.getAttribute('data-enddate');
          const isPublic = this.getAttribute('data-ispublic');
          const targetRole = this.getAttribute('data-targetrole');

          // Update basic information
          document.getElementById('view_announcement_id').textContent = announcementId;
          document.getElementById('view_title').textContent = title;
          document.getElementById('view_content').textContent = content;
          document.getElementById('view_start_date').textContent = formatDate(startDate);
          document.getElementById('view_end_date').textContent = formatDate(endDate);
          document.getElementById('view_created_by').textContent = createdBy;
          document.getElementById('view_created_at').textContent = formatDate(createdAt);

          // Update target audience information
          document.getElementById('view_target').textContent = capitalize(targetRole);

          // Update target badge
          const targetBadgeContainer = document.getElementById('view_target_badge');
          targetBadgeContainer.innerHTML = '';
          if (targetRole !== 'all') {
            const targetBadge = document.createElement('span');
            targetBadge.className = 'badge bg-info';
            targetBadge.textContent = capitalize(targetRole) + ' Only';
            targetBadgeContainer.appendChild(targetBadge);
          } else {
            const targetBadge = document.createElement('span');
            targetBadge.className = 'badge bg-light text-dark';
            targetBadge.textContent = 'All Users';
            targetBadgeContainer.appendChild(targetBadge);
          }

          // Update visibility badge
          const visibilityBadge = document.getElementById('view_visibility');
          if (isPublic === '1') {
            visibilityBadge.textContent = 'Public';
            visibilityBadge.className = 'badge bg-success fs-6';
          } else {
            visibilityBadge.textContent = 'Private';
            visibilityBadge.className = 'badge bg-secondary fs-6';
          }

          // Store announcement ID for edit button
          const editFromViewBtn = viewAnnouncementModal.querySelector('.editFromViewBtn');
          if (editFromViewBtn) {
            editFromViewBtn.setAttribute('data-announcementid', announcementId);
            editFromViewBtn.setAttribute('data-title', title);
            editFromViewBtn.setAttribute('data-content', content);
            editFromViewBtn.setAttribute('data-createdby', createdBy);
            editFromViewBtn.setAttribute('data-createdat', createdAt);
            editFromViewBtn.setAttribute('data-startdate', startDate);
            editFromViewBtn.setAttribute('data-enddate', endDate);
            editFromViewBtn.setAttribute('data-ispublic', isPublic);
            editFromViewBtn.setAttribute('data-targetrole', targetRole);
          }
        });
      });
    }    // Edit from View button
    const editFromViewBtn = document.querySelector('.editFromViewBtn');
    if (editFromViewBtn) {
      editFromViewBtn.addEventListener('click', function () {
        // Get the data from attributes to pass to the edit modal
        const announcementId = this.getAttribute('data-announcementid');
        const title = this.getAttribute('data-title');
        const content = this.getAttribute('data-content');
        const createdBy = this.getAttribute('data-createdby');
        const createdAt = this.getAttribute('data-createdat');
        const startDate = this.getAttribute('data-startdate');
        const endDate = this.getAttribute('data-enddate');
        const isPublic = this.getAttribute('data-ispublic');
        const targetRole = this.getAttribute('data-targetrole');

        // Close the view modal manually
        const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewAnnouncementModal'));
        viewModal.hide();

        // Populate the edit modal with the data
        const editModal = document.getElementById('editAnnouncementModal');
        if (editModal) {
          editModal.querySelector('#edit_announcement_id').value = announcementId;
          editModal.querySelector('#edit_title').value = title;
          editModal.querySelector('#edit_content').value = content;
          editModal.querySelector('#edit_start_date').value = formatDateForInput(startDate);
          editModal.querySelector('#edit_end_date').value = formatDateForInput(endDate);
          editModal.querySelector('#edit_is_public').value = isPublic;
          editModal.querySelector('#edit_target_role').value = targetRole;

          // Update display information
          editModal.querySelector('#display_announcement_id').textContent = announcementId;
          editModal.querySelector('#display_created_by').textContent = createdBy;
          editModal.querySelector('#display_created_at').textContent = formatDate(createdAt);

          // Set visibility badge
          const visibilityBadge = editModal.querySelector('#display_visibility');
          visibilityBadge.textContent = isPublic === '1' ? 'Public' : 'Private';
          visibilityBadge.className = isPublic === '1' ? 'badge bg-success' : 'badge bg-secondary';

          // Set target badge
          editModal.querySelector('#display_target').textContent = capitalize(targetRole);

          // Format date range display
          let dateRangeText = '';
          if (startDate && endDate) {
            dateRangeText = formatDate(startDate) + ' to ' + formatDate(endDate);
          } else if (startDate) {
            dateRangeText = 'From ' + formatDate(startDate);
          } else if (endDate) {
            dateRangeText = 'Until ' + formatDate(endDate);
          } else {
            dateRangeText = 'No date restriction';
          }
          editModal.querySelector('#display_date_range').textContent = dateRangeText;
        }
      });
    }

    // Set up filter functionality
    if (filterVisibility && filterRole && refreshBtn) {
      // Add event listeners for immediate filter application
      filterVisibility.addEventListener('change', applyFilters);
      filterRole.addEventListener('change', applyFilters);

      // Refresh button functionality
      refreshBtn.addEventListener('click', function () {
        // Clear both the UI and session storage
        filterVisibility.value = '';
        filterRole.value = '';

        // Clear session storage
        sessionStorage.removeItem('announcementVisibility');
        sessionStorage.removeItem('announcementRole');

        // Redirect to clean URL
        window.location.href = '?page=Manage&subpage=Announcement';
      });
    }

    // Store filter values in session storage
    function storeFilters() {
      if (filterVisibility.value) {
        sessionStorage.setItem('announcementVisibility', filterVisibility.value);
      } else {
        sessionStorage.removeItem('announcementVisibility');
      }

      if (filterRole.value) {
        sessionStorage.setItem('announcementRole', filterRole.value);
      } else {
        sessionStorage.removeItem('announcementRole');
      }
    }

    function applyFilters() {
      const visibilityValue = filterVisibility.value;
      const roleValue = filterRole.value;

      // Store filter values before navigating
      storeFilters();

      // Build the query parameters
      let queryParams = ['page=Manage', 'subpage=Announcement'];
      if (visibilityValue) queryParams.push('visibility=' + encodeURIComponent(visibilityValue));
      if (roleValue) queryParams.push('role=' + encodeURIComponent(roleValue));

      // Navigate to the filtered URL
      window.location.href = '?' + queryParams.join('&');
    }

    // Helper functions
    function formatDate(dateString) {
      if (!dateString) return '—';
      const date = new Date(dateString);
      const options = { year: 'numeric', month: 'short', day: 'numeric' };
      return date.toLocaleDateString('en-US', options);
    }

    function formatDateForInput(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toISOString().split('T')[0];
    }

    function capitalize(string) {
      if (!string) return '';
      return string.charAt(0).toUpperCase() + string.slice(1);
    }
  });
</script>
</div>