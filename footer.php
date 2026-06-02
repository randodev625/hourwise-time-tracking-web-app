<?php
// footer.php
?>
<footer class="app-footer border-top mt-5 py-5">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">

            <!-- Left: Copyright -->
            <div class="col-md-4 d-flex align-items-center">
                <span class="text-body-secondary">
                    &copy; <?= date('Y') ?> James June Media
                </span>
            </div>

            <!-- Center: Brand -->
            <div class="col-md-4 d-flex justify-content-center">
                <a href="/dashboard.php">
                    <img src="/assets/img/hourwise-logo.jpg" alt="HourWise Logo" class="me-2" height="40">
                </a>
            </div>

            <!-- Right: Navigation -->
            <ul class="nav col-md-4 justify-content-end">

                <li class="nav-item">
                    <a href="/dashboard.php"
                        class="nav-link px-2 text-body-secondary"
                        <?= $current_page === 'dashboard.php' ? 'aria-current="page"' : '' ?>>
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/track.php"
                        class="nav-link px-2 text-body-secondary"
                        <?= $current_page === 'track.php' ? 'aria-current="page"' : '' ?>>
                        Track
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/entries.php"
                        class="nav-link px-2 text-body-secondary"
                        <?= in_array($current_page, ['entries.php', 'entry_edit.php'], true) ? 'aria-current="page"' : '' ?>>
                        Entries
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/clients.php"
                        class="nav-link px-2 text-body-secondary"
                        <?= in_array($current_page, ['clients.php', 'client_edit.php'], true) ? 'aria-current="page"' : '' ?>>
                        Clients
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/projects.php"
                        class="nav-link px-2 text-body-secondary"
                        <?= in_array($current_page, ['projects.php', 'project_edit.php'], true) ? 'aria-current="page"' : '' ?>>
                        Projects
                    </a>
                </li>

                <li class="nav-item">
                    <a href="/account.php"
                        class="nav-link px-2 text-body-secondary <?= $current_page === 'account.php' ? 'fw-semibold text-body' : '' ?>"
                        <?= $current_page === 'account.php' ? 'aria-current="page"' : '' ?>>
                        Account
                    </a>
                </li>

            </ul>
        </div>
    </div>
</footer>

<script src="/assets/index.js"></script>
</body>

</html>