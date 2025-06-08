
    <nav class="navbar p-3 mx-3 mt-2 rounded-2 fixed-top">
        <div class="container-fluid shadows">

            <a href="#Home" class="navbar-brand">
                <div class="d-flex align-items-center">
                    <img src="../../../src/img/PLSP.png" alt="PLSP Enrollment System" width="40">
                    <div class="ms-2">
                        <p class="title">PLSP</p>
                        <h6 class="mb-0">MyUniPortal</h6>
                    </div>
                </div>
            </a>
        </div>
    </nav>
    <!-- Center Content: Logo + 404 (Literally center of screen) -->
    <div class="container-fluid flex-grow-1 d-flex align-items-center justify-content-center">
        <div class="text-center mt-5 pt-5">
            <div class="d-flex align-items-center justify-content-center">
                <div>
                    <div class="d-flex justify-content-center align-items-center">
                        <img src="https://raw.githubusercontent.com/PLSPGameEnt/PLSPGE-WEB/refs/heads/main/Sources/Images/404.svg" alt="404 Error" style="max-width: 600px; height: auto;">
                    </div>
                    <p class="text-dark opacity-75 mt-3">
                        <strong>Oops! This page seems to have skipped class! 📚</strong><br>
                        <small>The page you're looking for might have been moved, deleted, or is currently enrolled in a different semester.<br> Don't worry though - there are plenty of other pages ready to help you succeed!</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navigation Section (Bottom) -->
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center">
                    <!-- Primary Action -->
                    <div class="mb-4">
                        <a href="javascript:history.back()" class="btn btn-success btn-lg px-4 rounded rounded-pill">
                            <i class="bi bi-arrow-left me-2"></i>Go Back
                        </a>
                    </div>
                    
                    <!-- Quick Access Navigation -->
                    <div class="row g-3 justify-content-center">
                        <div class="col-md-3 col-6">
                            <a href="../student/dashboard.php" class="text-decoration-none text-dark d-block p-3 border border-secondary rounded-0" style="transition: all 0.3s;">
                                <i class="bi bi-person-badge fs-3 d-block mb-2 opacity-75"></i>
                                <span class="small">Student Portal</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="../admin/dashboard.php" class="text-decoration-none text-dark d-block p-3 border border-secondary rounded-0" style="transition: all 0.3s;">
                                <i class="bi bi-gear fs-3 d-block mb-2 opacity-75"></i>
                                <span class="small">Admin Panel</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="https://plsp.edu.ph/" class="text-decoration-none text-dark d-block p-3 border border-secondary rounded-0" style="transition: all 0.3s;">
                                <i class="bi bi-globe fs-3 d-block mb-2 opacity-75"></i>
                                <span class="small">PLSP Main Website</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer (Stays at bottom) -->
    <div class="container-fluid py-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <div class="d-flex align-items-center">
                    <span class="text-dark fs-5 me-3 bg-primary rounded rounded-3 py-2 pe-4 ps-3 text-white">ヽ( •ω• )ﾉ</span>                    <small class="text-dark opacity-75">
                        <strong>Campus Tip:</strong> <span id="campusTip">Loading tip...</span>
                    </small>
                </div>
            </div>
            <div class="col-md-4 text-end">
                <small class="text-dark opacity-75">
                    Need help? <a href="mailto:plsp.official@plsp.edu.ph" class="text-dark">plsp.official@plsp.edu.ph</a>
                </small>
            </div>
        </div>
    </div>
    <script src="../../../src/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Randomized Campus Tips
            const campusTips = [
                "Don't forget to bookmark your favorite pages to avoid getting lost in the digital hallways!",
                "Use Ctrl+F to quickly find what you're looking for on any page - it's like a campus map for your browser!",
                "Check your notification bell regularly - important updates from your professors might be waiting!",
                "Save your work frequently when filling out forms - technology can be as unpredictable as surprise quizzes!",
                "Keep your login credentials secure - treat them like your dorm room key!",
                "Use the search feature to quickly locate courses, professors, or classmates across the portal!",
                "Take advantage of the dark mode option if you're studying late - your eyes will thank you!",
                "Remember to log out when using public computers - protecting your academic privacy is important!",
                "Explore all the dashboard widgets - you might discover features that make your student life easier!",
                "Set up email notifications for grades and announcements - stay ahead of your academic game!",
                "Use keyboard shortcuts like 'B' to go back - navigate like a pro student!",
                "Check the system status page if something seems off - sometimes the server needs a coffee break too!",
                "Organize your bookmarks into folders by semester or subject - future you will appreciate the organization!",
                "Take screenshots of important information - sometimes having a backup copy saves the day!",
                "Use multiple tabs wisely - but don't overload your browser like you overload your course schedule!"
            ];
            
            // Display random campus tip
            const tipElement = document.getElementById('campusTip');
            if (tipElement) {
                const randomTip = campusTips[Math.floor(Math.random() * campusTips.length)];
                tipElement.textContent = randomTip;
            }
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Press 'B' to go back
            if (e.key.toLowerCase() === 'b' && !e.ctrlKey && !e.altKey) {
                const activeElement = document.activeElement;
                if (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA') {
                    history.back();
                }
            }
        });
    </script>
    </script>

