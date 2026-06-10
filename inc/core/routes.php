<?php

$publicRoot = dirname(__DIR__, 2);
$appViewRoot = $publicRoot . '/inc/views/app';
$authViewRoot = $publicRoot . '/inc/views/auth';
$errorViewRoot = $publicRoot . '/inc/views/errors';
$setupViewRoot = $publicRoot . '/inc/views/setup';

return [
    '/dashboard' => [
        'name' => 'dashboard',
        'file' => $appViewRoot . '/dashboard.php',
        'title' => 'Dashboard',
        'layout' => 'app',
    ],
    '/dashboard.php' => [
        'name' => 'dashboard_legacy_php',
        'canonical' => 'dashboard',
        'file' => $appViewRoot . '/dashboard.php',
    ],
    '/track' => [
        'name' => 'track',
        'file' => $appViewRoot . '/track.php',
        'title' => 'Track Time',
        'layout' => 'app',
    ],
    '/track.php' => [
        'name' => 'track_legacy_php',
        'canonical' => 'track',
        'file' => $appViewRoot . '/track.php',
    ],
    '/entries' => [
        'name' => 'entries',
        'file' => $appViewRoot . '/entries.php',
        'title' => 'Entries',
        'layout' => 'app',
    ],
    '/entries.php' => [
        'name' => 'entries_legacy_php',
        'canonical' => 'entries',
        'file' => $appViewRoot . '/entries.php',
    ],
    '/entries/{id}/edit' => [
        'name' => 'entry_edit',
        'file' => $appViewRoot . '/entry_edit.php',
        'title' => 'Edit Entry',
        'layout' => 'app',
    ],
    '/entries/edit' => [
        'name' => 'entry_edit_legacy',
        'canonical' => 'entry_edit',
        'file' => $appViewRoot . '/entry_edit.php',
    ],
    '/entry_edit.php' => [
        'name' => 'entry_edit_legacy_php',
        'canonical' => 'entry_edit',
        'file' => $appViewRoot . '/entry_edit.php',
    ],
    '/clients' => [
        'name' => 'clients',
        'file' => $appViewRoot . '/clients.php',
        'title' => 'Clients',
        'layout' => 'app',
    ],
    '/clients.php' => [
        'name' => 'clients_legacy_php',
        'canonical' => 'clients',
        'file' => $appViewRoot . '/clients.php',
    ],
    '/clients/{id}/edit' => [
        'name' => 'client_edit',
        'file' => $appViewRoot . '/client_edit.php',
        'title' => 'Edit Client',
        'layout' => 'app',
    ],
    '/clients/edit' => [
        'name' => 'client_edit_legacy',
        'canonical' => 'client_edit',
        'file' => $appViewRoot . '/client_edit.php',
    ],
    '/client_edit.php' => [
        'name' => 'client_edit_legacy_php',
        'canonical' => 'client_edit',
        'file' => $appViewRoot . '/client_edit.php',
    ],
    '/projects' => [
        'name' => 'projects',
        'file' => $appViewRoot . '/projects.php',
        'title' => 'Projects',
        'layout' => 'app',
    ],
    '/projects.php' => [
        'name' => 'projects_legacy_php',
        'canonical' => 'projects',
        'file' => $appViewRoot . '/projects.php',
    ],
    '/projects/{id}/edit' => [
        'name' => 'project_edit',
        'file' => $appViewRoot . '/project_edit.php',
        'title' => 'Edit Project',
        'layout' => 'app',
    ],
    '/projects/edit' => [
        'name' => 'project_edit_legacy',
        'canonical' => 'project_edit',
        'file' => $appViewRoot . '/project_edit.php',
    ],
    '/project_edit.php' => [
        'name' => 'project_edit_legacy_php',
        'canonical' => 'project_edit',
        'file' => $appViewRoot . '/project_edit.php',
    ],
    '/categories' => [
        'name' => 'categories',
        'file' => $appViewRoot . '/categories.php',
        'title' => 'Project Categories',
        'layout' => 'app',
    ],
    '/categories.php' => [
        'name' => 'categories_legacy_php',
        'canonical' => 'categories',
        'file' => $appViewRoot . '/categories.php',
    ],
    '/categories/{id}/edit' => [
        'name' => 'category_edit',
        'file' => $appViewRoot . '/category_edit.php',
        'title' => 'Edit Project Category',
        'layout' => 'app',
    ],
    '/categories/edit' => [
        'name' => 'category_edit_legacy',
        'canonical' => 'category_edit',
        'file' => $appViewRoot . '/category_edit.php',
    ],
    '/category_edit.php' => [
        'name' => 'category_edit_legacy_php',
        'canonical' => 'category_edit',
        'file' => $appViewRoot . '/category_edit.php',
    ],
    '/account' => [
        'name' => 'account',
        'file' => $appViewRoot . '/account.php',
        'title' => 'Manage Account',
        'layout' => 'app',
    ],
    '/account.php' => [
        'name' => 'account_legacy_php',
        'canonical' => 'account',
        'file' => $appViewRoot . '/account.php',
    ],
    '/account/delete' => [
        'name' => 'account_delete',
        'file' => $appViewRoot . '/account_delete.php',
        'title' => 'Delete Account',
        'layout' => 'app',
    ],
    '/account_delete.php' => [
        'name' => 'account_delete_legacy_php',
        'canonical' => 'account_delete',
        'file' => $appViewRoot . '/account_delete.php',
    ],
    '/admin/settings' => [
        'name' => 'admin_settings',
        'file' => $appViewRoot . '/admin_settings.php',
        'title' => 'Admin Settings',
        'layout' => 'app',
    ],
    '/admin_settings.php' => [
        'name' => 'admin_settings_legacy_php',
        'canonical' => 'admin_settings',
        'file' => $appViewRoot . '/admin_settings.php',
    ],
    '/setup' => [
        'name' => 'setup',
        'file' => $setupViewRoot . '/setup.php',
        'title' => 'HourWise Setup',
        'heading' => 'HourWise Setup',
        'layout' => 'setup',
    ],
    '/setup.php' => [
        'name' => 'setup_legacy_php',
        'canonical' => 'setup',
        'file' => $setupViewRoot . '/setup.php',
    ],
    '/setup/complete' => [
        'name' => 'setup_complete',
        'file' => $setupViewRoot . '/setup_complete.php',
        'title' => 'Setup Complete',
        'layout' => 'auth',
    ],
    '/setup_complete.php' => [
        'name' => 'setup_complete_legacy_php',
        'canonical' => 'setup_complete',
        'file' => $setupViewRoot . '/setup_complete.php',
    ],
    '/login' => [
        'name' => 'login',
        'file' => $authViewRoot . '/login.php',
        'title' => 'Login',
        'subtitle' => 'Simple time tracking for freelancers and small teams.',
        'layout' => 'auth',
    ],
    '/auth/login.php' => [
        'name' => 'login_legacy_php',
        'canonical' => 'login',
        'file' => $authViewRoot . '/login.php',
    ],
    '/logout' => [
        'name' => 'logout',
        'file' => $authViewRoot . '/logout.php',
        'title' => 'Logout',
        'layout' => 'auth',
    ],
    '/auth/logout.php' => [
        'name' => 'logout_legacy_php',
        'canonical' => 'logout',
        'file' => $authViewRoot . '/logout.php',
    ],
    '/register' => [
        'name' => 'register',
        'file' => $authViewRoot . '/register.php',
        'title' => 'Create Account',
        'subtitle' => 'Simple time tracking for freelancers and small teams.',
        'layout' => 'auth',
    ],
    '/auth/register.php' => [
        'name' => 'register_legacy_php',
        'canonical' => 'register',
        'file' => $authViewRoot . '/register.php',
    ],
    '/password/forgot' => [
        'name' => 'forgot_password',
        'file' => $authViewRoot . '/forgot_password.php',
        'title' => 'Forgot Password',
        'subtitle' => 'Enter your email and we will send you a password reset link.',
        'layout' => 'auth',
    ],
    '/auth/forgot_password.php' => [
        'name' => 'forgot_password_legacy_php',
        'canonical' => 'forgot_password',
        'file' => $authViewRoot . '/forgot_password.php',
    ],
    '/password/reset' => [
        'name' => 'reset_password',
        'file' => $authViewRoot . '/reset_password.php',
        'title' => 'Reset Password',
        'subtitle' => 'Choose a new password for your account.',
        'layout' => 'auth',
    ],
    '/auth/reset_password.php' => [
        'name' => 'reset_password_legacy_php',
        'canonical' => 'reset_password',
        'file' => $authViewRoot . '/reset_password.php',
    ],
    '/verify-email' => [
        'name' => 'verify_email',
        'file' => $authViewRoot . '/verify_email.php',
        'title' => 'Verify Email',
        'layout' => 'auth',
    ],
    '/auth/verify_email.php' => [
        'name' => 'verify_email_legacy_php',
        'canonical' => 'verify_email',
        'file' => $authViewRoot . '/verify_email.php',
    ],
    '/verify-email/resend' => [
        'name' => 'resend_verification',
        'file' => $authViewRoot . '/resend_verification.php',
        'title' => 'Resend Verification',
        'subtitle' => 'Request a new verification link for your account.',
        'layout' => 'auth',
    ],
    '/auth/resend_verification.php' => [
        'name' => 'resend_verification_legacy_php',
        'canonical' => 'resend_verification',
        'file' => $authViewRoot . '/resend_verification.php',
    ],
    '/two-factor' => [
        'name' => 'two_factor',
        'file' => $authViewRoot . '/two_factor.php',
        'title' => 'Two-Factor Authentication',
        'subtitle' => 'Enter your authenticator code to finish signing in.',
        'layout' => 'auth',
    ],
    '/auth/two_factor.php' => [
        'name' => 'two_factor_legacy_php',
        'canonical' => 'two_factor',
        'file' => $authViewRoot . '/two_factor.php',
    ],
    '/api/entries' => [
        'name' => 'entries_ajax',
        'file' => $publicRoot . '/inc/api/entries_ajax.php',
    ],
    '/entries_ajax.php' => [
        'name' => 'entries_ajax_legacy_php',
        'canonical' => 'entries_ajax',
        'file' => $publicRoot . '/inc/api/entries_ajax.php',
    ],
    '/api/filter-lookup' => [
        'name' => 'filter_lookup',
        'file' => $publicRoot . '/inc/api/filter_lookup.php',
    ],
    '/filter_lookup.php' => [
        'name' => 'filter_lookup_legacy_php',
        'canonical' => 'filter_lookup',
        'file' => $publicRoot . '/inc/api/filter_lookup.php',
    ],
    '/jobs' => [
        'name' => 'jobs',
        'file' => $appViewRoot . '/jobs.php',
        'title' => 'Jobs',
        'layout' => 'app',
    ],
    '/jobs.php' => [
        'name' => 'jobs_legacy_php',
        'canonical' => 'jobs',
        'file' => $appViewRoot . '/jobs.php',
    ],
    '/jobs/search' => [
        'name' => 'jobs_search',
        'file' => $publicRoot . '/inc/api/jobs_search.php',
    ],
    '/jobs_search.php' => [
        'name' => 'jobs_search_legacy_php',
        'canonical' => 'jobs_search',
        'file' => $publicRoot . '/inc/api/jobs_search.php',
    ],
    '/403' => [
        'name' => 'error_403',
        'file' => $errorViewRoot . '/status.php',
        'status_code' => 403,
        'title' => 'Access Denied',
        'message' => 'You do not have permission to access this page.',
        'layout' => 'error',
    ],
    '/404' => [
        'name' => 'error_404',
        'file' => $errorViewRoot . '/status.php',
        'status_code' => 404,
        'title' => 'Page Not Found',
        'message' => 'The page you requested does not exist.',
        'layout' => 'error',
    ],
    '/500' => [
        'name' => 'error_500',
        'file' => $errorViewRoot . '/status.php',
        'status_code' => 500,
        'title' => 'Server Error',
        'message' => 'Something went wrong, but no account details or server information were exposed.',
        'layout' => 'error',
    ],
];
