
<nav class="navbar navbar-expand-lg navbg shadow p-3 mx-3 mt-2 rounded-2 fixed-top">
    <div class="container-fluid shadows d-flex justify-content-between">

        <a href="#Home" class="navbar-brand">
            <div class="d-flex align-items-center">
                <img src="../../../src/img/PLSP.png" alt="PLSP Enrollment System" width="40">
                <div class="ms-2">
                    <p class="title">PLSP</p>
                    <h6 class="mb-0">MyUniPortal</h6>
                </div>
            </div>
        </a>
        <!--Branding-->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navlinks"
            aria-controls="navlinks" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button> <!--Toggler for navigation bar in mobile view-->        <div class="collapse navbar-collapse" id="navlinks">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0"> <!--Navigation links-->
                <li class="nav-item">
                    <a href="?page=home" class="nav-link">Home</a>
                </li>
                <?php if (isset($userDetails['role']) && $userDetails['role'] === 'admin'): ?>
                <li class="nav-item">
                    <a href="?page=Manage" class="nav-link">Manage</a>
                </li>
                <?php elseif (isset($userDetails['role']) && $userDetails['role'] === 'student'): ?>
                <li class="nav-item">
                    <a href="?page=profile" class="nav-link">Profile</a>
                </li>
                <li class="nav-item">
                    <a href="?page=courses" class="nav-link">Courses</a>
                </li>
                <li class="nav-item">
                    <a href="?page=grades" class="nav-link">Grades</a>
                </li>
                <?php endif; ?>
            </ul>
            <hr class="d-lg-none">
            <div class="text-end text-sm-start">
                <div class="dropdown">
                    <button class="btn dropdown-toggle p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php echo $userDetails['first_name'] . " " . $userDetails['last_name']; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-sm-start">
                        <li>
                            <div class="dropdown-item text-start">
                            <p class="mb-0"><?php echo $userDetails['first_name'] . " " . $userDetails['last_name']; ?>
                            </p>
                            <p class="mb-0 smoltext"><?php echo $userDetails['role'] ?></p>
                            </div>
                        </li>
                        <hr class="mt-2">
                        <li><button class="dropdown-item" type="button">Report Issues</button></li>
                        <li><a href="<?php echo $currentPage?>/?logout=true" class="dropdown-item" type="button">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>