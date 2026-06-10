<?php

return [
    '/dashboard' => [
        'name' => 'dashboard',
        'file' => __DIR__ . '/dashboard.php',
    ],
    '/track' => [
        'name' => 'track',
        'file' => __DIR__ . '/track.php',
    ],
    '/entries' => [
        'name' => 'entries',
        'file' => __DIR__ . '/entries.php',
    ],
    '/entries/{id}/edit' => [
        'name' => 'entry_edit',
        'file' => __DIR__ . '/entry_edit.php',
    ],
    '/entries/edit' => [
        'name' => 'entry_edit_legacy',
        'canonical' => 'entry_edit',
        'file' => __DIR__ . '/entry_edit.php',
    ],
    '/clients' => [
        'name' => 'clients',
        'file' => __DIR__ . '/clients.php',
    ],
    '/clients/{id}/edit' => [
        'name' => 'client_edit',
        'file' => __DIR__ . '/client_edit.php',
    ],
    '/clients/edit' => [
        'name' => 'client_edit_legacy',
        'canonical' => 'client_edit',
        'file' => __DIR__ . '/client_edit.php',
    ],
    '/projects' => [
        'name' => 'projects',
        'file' => __DIR__ . '/projects.php',
    ],
    '/projects/{id}/edit' => [
        'name' => 'project_edit',
        'file' => __DIR__ . '/project_edit.php',
    ],
    '/projects/edit' => [
        'name' => 'project_edit_legacy',
        'canonical' => 'project_edit',
        'file' => __DIR__ . '/project_edit.php',
    ],
    '/categories' => [
        'name' => 'categories',
        'file' => __DIR__ . '/categories.php',
    ],
    '/categories/{id}/edit' => [
        'name' => 'category_edit',
        'file' => __DIR__ . '/category_edit.php',
    ],
    '/categories/edit' => [
        'name' => 'category_edit_legacy',
        'canonical' => 'category_edit',
        'file' => __DIR__ . '/category_edit.php',
    ],
    '/account' => [
        'name' => 'account',
        'file' => __DIR__ . '/account.php',
    ],
    '/account/delete' => [
        'name' => 'account_delete',
        'file' => __DIR__ . '/account_delete.php',
    ],
    '/admin/settings' => [
        'name' => 'admin_settings',
        'file' => __DIR__ . '/admin_settings.php',
    ],
    '/setup' => [
        'name' => 'setup',
        'file' => __DIR__ . '/setup.php',
    ],
    '/setup/complete' => [
        'name' => 'setup_complete',
        'file' => __DIR__ . '/setup_complete.php',
    ],
    '/login' => [
        'name' => 'login',
        'file' => __DIR__ . '/auth/login.php',
    ],
    '/logout' => [
        'name' => 'logout',
        'file' => __DIR__ . '/auth/logout.php',
    ],
    '/register' => [
        'name' => 'register',
        'file' => __DIR__ . '/auth/register.php',
    ],
    '/password/forgot' => [
        'name' => 'forgot_password',
        'file' => __DIR__ . '/auth/forgot_password.php',
    ],
    '/password/reset' => [
        'name' => 'reset_password',
        'file' => __DIR__ . '/auth/reset_password.php',
    ],
    '/verify-email' => [
        'name' => 'verify_email',
        'file' => __DIR__ . '/auth/verify_email.php',
    ],
    '/verify-email/resend' => [
        'name' => 'resend_verification',
        'file' => __DIR__ . '/auth/resend_verification.php',
    ],
    '/two-factor' => [
        'name' => 'two_factor',
        'file' => __DIR__ . '/auth/two_factor.php',
    ],
    '/api/entries' => [
        'name' => 'entries_ajax',
        'file' => __DIR__ . '/entries_ajax.php',
    ],
    '/api/filter-lookup' => [
        'name' => 'filter_lookup',
        'file' => __DIR__ . '/filter_lookup.php',
    ],
    '/jobs' => [
        'name' => 'jobs',
        'file' => __DIR__ . '/jobs.php',
    ],
    '/jobs/search' => [
        'name' => 'jobs_search',
        'file' => __DIR__ . '/jobs_search.php',
    ],
    '/403' => [
        'name' => 'error_403',
        'file' => __DIR__ . '/403.php',
    ],
    '/404' => [
        'name' => 'error_404',
        'file' => __DIR__ . '/404.php',
    ],
    '/500' => [
        'name' => 'error_500',
        'file' => __DIR__ . '/500.php',
    ],
];
