<?php
// footer.php
?>
<footer class="app-footer border-top mt-5 py-5">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div class="col-md-4 d-flex align-items-center">
                <span class="text-body-secondary">
                    &copy; <?= date('Y') ?> James June Media
                </span>
            </div>

            <div class="col-md-4 d-flex justify-content-center">
                <a href="<?= h(route_url('dashboard')) ?>">
                    <img src="/assets/img/hourwise-logo.jpg" alt="HourWise Logo" class="me-2" height="40">
                </a>
            </div>

            <ul class="nav col-md-4 justify-content-end">
                <li class="nav-item">
                    <a href="<?= h(route_url('dashboard')) ?>"
                        class="nav-link px-2 text-body-secondary"
                        <?= route_is('dashboard') ? 'aria-current="page"' : '' ?>>
                        Dashboard
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= h(route_url('track')) ?>"
                        class="nav-link px-2 text-body-secondary"
                        <?= route_is('track') ? 'aria-current="page"' : '' ?>>
                        Track
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= h(route_url('entries')) ?>"
                        class="nav-link px-2 text-body-secondary"
                        <?= route_is(['entries', 'entry_edit']) ? 'aria-current="page"' : '' ?>>
                        Entries
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= h(route_url('clients')) ?>"
                        class="nav-link px-2 text-body-secondary"
                        <?= route_is(['clients', 'client_edit']) ? 'aria-current="page"' : '' ?>>
                        Clients
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= h(route_url('projects')) ?>"
                        class="nav-link px-2 text-body-secondary"
                        <?= route_is(['projects', 'project_edit']) ? 'aria-current="page"' : '' ?>>
                        Projects
                    </a>
                </li>

                <li class="nav-item">
                    <a href="<?= h(route_url('account')) ?>"
                        class="nav-link px-2 text-body-secondary <?= route_is('account') ? 'fw-semibold text-body' : '' ?>"
                        <?= route_is('account') ? 'aria-current="page"' : '' ?>>
                        Account
                    </a>
                </li>
            </ul>
        </div>
    </div>
</footer>

<script src="/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="/assets/index.js"></script>
<?php require __DIR__ . '/document_end.php'; ?>
