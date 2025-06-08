<?php
// Fetch the data from the database
$studentCount = getStudentCount($pdo);
$courseCount = getCourseCount($pdo);
$pendingEnrollmentsCount = getPendingEnrollmentsCount($pdo);
$overdueBillingsCount = getOverdueBillingsCount($pdo);
$recentAnnouncements = getRecentAnnouncements($pdo);
$recentEnrollments = getRecentEnrollmentRequests($pdo);
$recentBillings = getRecentBillings($pdo);
?>

<div>
    <div class="d-flex align-items-center justify-content-between flex-wrap py-4">
        <h2 class="mb-0">Dashboard</h2>
        <div class="text-end">
            <p class="text-muted mb-0">Philippine Standard Time</p>
            <h6 id="time" class="text-muted mb-0"></h6>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-4 mb-4">
        <!-- Students Card -->
        <div class="col-md-3">
            <div class="card text-white bg-primary h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-people-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Students</h5>
                        <h3 class="card-text"><?= number_format($studentCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Courses Card -->
        <div class="col-md-3">
            <div class="card text-white bg-success h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-book-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Courses</h5>
                        <h3 class="card-text"><?= number_format($courseCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Enrollments Card -->
        <div class="col-md-3">
            <div class="card text-white bg-dark h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-person-check-fill fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Pending Enrollments</h5>
                        <h3 class="card-text"><?= number_format($pendingEnrollmentsCount) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overdue Billings Card -->
        <div class="col-md-3">
            <div class="card text-white bg-danger h-100">
                <div class="card-body d-flex align-items-center">
                    <i class="bi bi-cash-coin fs-1 me-3"></i>
                    <div>
                        <h5 class="card-title">Overdue Billings</h5>
                        <h3 class="card-text"><?php echo getOverdueBillingsCount($pdo);  ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Announcements -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <i class="bi bi-megaphone-fill me-2"></i> Recent Announcements
                </div>
                <ul class="list-group list-group-flush">
                    <?php if (!empty($recentAnnouncements)): ?>
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                            <li class="list-group-item">
                                <strong><?= htmlspecialchars($announcement['title']) ?></strong>
                                <p class="mb-1"><?= htmlspecialchars($announcement['content']) ?></p>
                                <small class="text-muted">Posted on
                                    <?= date('M d, Y', strtotime($announcement['created_at'])) ?></small>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="list-group-item">No announcements found.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Enrollment Requests -->
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <i class="bi bi-person-lines-fill me-2"></i> Enrollment Requests
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentEnrollments)): ?>
                                <?php foreach ($recentEnrollments as $enrollment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($enrollment['student_name']) ?></td>
                                        <td><?= htmlspecialchars($enrollment['course_name']) ?></td>
                                        <td><?= date('M d, Y', strtotime($enrollment['requested_at'])) ?></td>
                                        <td><span
                                                class="badge <?= $enrollment['status'] === 'pending' ? 'bg-warning' : 'bg-success' ?>"><?= ucfirst($enrollment['status']) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4">No enrollment requests found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Billing Overview -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-light">
                    <i class="bi bi-receipt me-2"></i>Billing Overview
                </div>
                <div class="table-responsive">
                    <table class="table table-striped mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch recent billings
                            $billings = getRecentBillings($pdo);

                            // Loop through each billing record
                            foreach ($billings as $billing) {
                                // Determine the status badge class
                                switch ($billing['status']) {
                                    case 'pending':
                                        $statusClass = 'bg-warning text-dark'; // Yellow
                                        break;
                                    case 'partial':
                                        $statusClass = 'bg-info text-dark'; // Blue
                                        break;
                                    case 'paid':
                                        $statusClass = 'bg-success'; // Green
                                        break;
                                    case 'overdue':
                                        $statusClass = 'bg-danger'; // Red
                                        break;
                                    default:
                                        $statusClass = 'bg-secondary'; // Default grey
                                }
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($billing['student_name']); ?></td>
                                    <td><?php echo number_format($billing['amount'], 2); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($billing['due_date'])); ?></td>
                                    <td><span
                                            class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($billing['status']); ?></span>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>