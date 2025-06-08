<?php
$subpage = isset($_GET['subpage']) ? $_GET['subpage'] : 'Announcement';
$subfile = "home/DataManagement/" . $subpage . ".php";
?>

<!-- Toggle button for offcanvas on small screens -->
<button class="btn btn-success d-md-none mb-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu">
    <i class="bi bi-list"></i> Menu
</button>

<div class="container-fluid mt-3">
    <div class="row">
        <!-- Sidebar as offcanvas on small screens -->
        <div class="offcanvas offcanvas-start d-md-none" tabindex="-1" id="sidebarMenu" aria-labelledby="sidebarMenuLabel">
            <div class="offcanvas-header">
                <h5 class="offcanvas-title" id="sidebarMenuLabel">System Data Management</h5>
                <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body">
                <div class="btn-group-vertical w-100">
                    <a href="?page=Manage&subpage=Announcement" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Announcement') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-megaphone"></i> Announcements
                    </a>
                    <a href="?page=Manage&subpage=Billing" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Billing') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-receipt"></i> Billing
                    </a>
                    <a href="?page=Manage&subpage=Class" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Class') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-easel"></i> Classes
                    </a>
                    <a href="?page=Manage&subpage=Course" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Course') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-book"></i> Courses
                    </a>
                    <a href="?page=Manage&subpage=Enrollment" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Enrollment') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-person-check"></i> Enrollment
                    </a>
                    <a href="?page=Manage&subpage=Grade" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Grade') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-journal-text"></i> Grades
                    </a>
                    <a href="?page=Manage&subpage=Prof" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Prof') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-person-badge"></i> Professors
                    </a>
                    <a href="?page=Manage&subpage=Student" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Student') ? 'active bg-success text-white' : ''; ?>">
                        <i class="bi bi-people"></i> Students
                    </a>
                </div>
            </div>
        </div>

        <!-- Sidebar for medium and larger screens -->
        <nav class="col-md-3 col-lg-2 d-none d-md-block navbg py-3 rounded-3 shadow-sm">
            <div class="p-2 text-start mb-2">
                <p class="mb-0 fw-bold">System Data Management</p>
            </div>
            <hr class="mb-1 mt-0">
            <div class="btn-group-vertical w-100">
                <a href="?page=Manage&subpage=Announcement" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Announcement') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-megaphone"></i> Announcements
                </a>
                <a href="?page=Manage&subpage=Billing" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Billing') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-receipt"></i> Billing
                </a>
                <a href="?page=Manage&subpage=Class" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Class') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-easel"></i> Classes
                </a>
                <a href="?page=Manage&subpage=Course" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Course') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-book"></i> Courses
                </a>
                <a href="?page=Manage&subpage=Enrollment" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Enrollment') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-person-check"></i> Enrollment
                </a>
                <a href="?page=Manage&subpage=Grade" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Grade') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-journal-text"></i> Grades
                </a>
                <a href="?page=Manage&subpage=Prof" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Prof') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-person-badge"></i> Professors
                </a>
                <a href="?page=Manage&subpage=Student" class="btn text-start rounded-pill mb-1 <?php echo ($subpage === 'Student') ? 'active bg-success text-white' : ''; ?>">
                    <i class="bi bi-people"></i> Students
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-12 col-md-9 col-lg-10">
            <?php
            if (file_exists($subfile)) {
                include $subfile;
            } else {
                echo "<p>Page not found: " . htmlspecialchars($subfile) . "</p>";
            }
            ?>
        </main>
    </div>
</div>
