<?php
require __DIR__ . '/../../core/middleware.php';
require_login();

$userId = user_id();

$stmt = $pdo->prepare('SELECT id, email, pending_email, display_name, avatar_path, timezone FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$messages = [];
$errors = [];
$newRecoveryCodes = [];

function load_user(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT id, email, pending_email, display_name, avatar_path, timezone, password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('User not found.');
    }
    return $row;
}

function delete_avatar_file(?string $avatarPath): void {
    $full = avatar_file_path($avatarPath);
    if ($full === null) return;
    if (is_file($full)) {
        @unlink($full);
    }
}

function avatar_upload_limits(): array {
    return [
        'max_bytes' => 5 * 1024 * 1024,
        'max_width' => 4096,
        'max_height' => 4096,
        'allowed_mimes' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
    ];
}

function account_two_factor_challenge_valid(PDO $pdo, int $userId, ?array $settings, string $code): bool {
    if (!$settings) {
        return false;
    }

    if (two_factor_verify_code((string)$settings['secret'], $code)) {
        return true;
    }

    return two_factor_use_recovery_code($pdo, $userId, $code);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        try {
            $userWithPassword = load_user($pdo, $userId);

            if ($action === 'update_profile') {
                $displayName = trim($_POST['display_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $timezone = trim((string)($_POST['timezone'] ?? ''));
                $currentPassword = $_POST['current_password_for_profile'] ?? '';
                $emailChanged = $email !== $userWithPassword['email'];

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'Please enter a valid email address.';
                }

                if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                    $errors[] = 'Please select a valid timezone.';
                }

                if ($emailChanged) {
                    if ($currentPassword === '' || !password_verify($currentPassword, $userWithPassword['password_hash'])) {
                        $errors[] = 'Enter your current password to change your email address.';
                    }

                    $check = $pdo->prepare('SELECT id FROM users WHERE (email = ? OR pending_email = ?) AND id <> ? LIMIT 1');
                    $check->execute([$email, $email, $userId]);
                    if ($check->fetch()) {
                        $errors[] = 'That email address is already in use.';
                    }
                }

                if (!$errors) {
                    if ($emailChanged) {
                        $pdo->prepare('UPDATE users SET display_name = ?, timezone = ?, pending_email = ? WHERE id = ?')
                            ->execute([$displayName !== '' ? $displayName : null, $timezone, $email, $userId]);
                    } else {
                        $pdo->prepare('UPDATE users SET display_name = ?, timezone = ? WHERE id = ?')
                            ->execute([$displayName !== '' ? $displayName : null, $timezone, $userId]);
                    }
                    $user = load_user($pdo, $userId);
                    set_user_session($user);
                    if ($emailChanged) {
                        refresh_session_security();
                        try {
                            send_account_verification_for_user($pdo, (int)$userId);
                            audit_log('email_verification_requested', ['user_id' => (int)$userId, 'reason' => 'email_change']);
                            $messages[] = 'Profile updated successfully. A verification link has been sent to your new email address. Your current login email stays active until you verify the change.';
                        } catch (Throwable $e) {
                            log_exception($e, 'Email change verification email failed.', ['user_id' => (int)$userId]);
                            $messages[] = 'Profile updated successfully. Your current login email stays active until you verify the change, but the verification email could not be sent right now. Use the resend verification link soon.';
                        }
                    } else {
                        $messages[] = 'Profile updated successfully.';
                    }
                }
            }

            if ($action === 'change_password') {
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';

                if (!password_verify($currentPassword, $userWithPassword['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                }
                $passwordError = validate_password_strength($newPassword);
                if ($passwordError !== null) {
                    $errors[] = $passwordError;
                }
                if ($newPassword !== $confirmPassword) {
                    $errors[] = 'New password and confirmation do not match.';
                }

                if (!$errors) {
                    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                    $pdo->beginTransaction();
                    try {
                        $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP() WHERE id = ?')
                            ->execute([$hash, $userId]);
                        invalidate_password_reset_tokens($pdo, (int)$userId);
                        $pdo->commit();
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) {
                            $pdo->rollBack();
                        }
                        throw $e;
                    }
                    refresh_session_security();
                    $messages[] = 'Password updated successfully.';
                }
            }

            if ($action === 'start_two_factor_setup') {
                if (!password_verify((string)($_POST['current_password_2fa'] ?? ''), $userWithPassword['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                }

                if (!$errors) {
                    $_SESSION['pending_2fa_secret'] = two_factor_generate_secret();
                    $messages[] = 'Two-factor setup started. Add the setup key to your authenticator app, then enter the code it generates.';
                }
            }

            if ($action === 'confirm_two_factor_setup') {
                $pendingSecret = (string)($_SESSION['pending_2fa_secret'] ?? '');
                $code = (string)($_POST['two_factor_code'] ?? '');

                if ($pendingSecret === '') {
                    $errors[] = 'Two-factor setup has expired. Start setup again.';
                } elseif (!two_factor_verify_code($pendingSecret, $code)) {
                    $errors[] = 'Invalid two-factor code.';
                }

                if (!$errors) {
                    $newRecoveryCodes = two_factor_generate_recovery_codes();
                    $recoveryJson = two_factor_hash_recovery_codes($newRecoveryCodes);
                    $stmt = $pdo->prepare(
                        'INSERT INTO user_two_factor (user_id, secret, recovery_codes_json, enabled_at)
                         VALUES (?, ?, ?, CURRENT_TIMESTAMP())
                         ON DUPLICATE KEY UPDATE
                            secret = VALUES(secret),
                            recovery_codes_json = VALUES(recovery_codes_json),
                            enabled_at = CURRENT_TIMESTAMP()'
                    );
                    $stmt->execute([(int)$userId, $pendingSecret, $recoveryJson]);
                    unset($_SESSION['pending_2fa_secret']);
                    refresh_session_security();
                    audit_log('two_factor_enabled', ['user_id' => (int)$userId]);
                    $messages[] = 'Two-factor authentication enabled. Save your recovery codes now.';
                }
            }

            if ($action === 'regenerate_two_factor_recovery_codes') {
                $settings = two_factor_settings($pdo, (int)$userId);
                $code = (string)($_POST['two_factor_code_manage'] ?? '');

                if (!password_verify((string)($_POST['current_password_2fa_manage'] ?? ''), $userWithPassword['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                } elseif (!account_two_factor_challenge_valid($pdo, (int)$userId, $settings, $code)) {
                    $errors[] = 'Invalid two-factor code.';
                }

                if (!$errors) {
                    $newRecoveryCodes = two_factor_generate_recovery_codes();
                    $stmt = $pdo->prepare('UPDATE user_two_factor SET recovery_codes_json = ? WHERE user_id = ?');
                    $stmt->execute([two_factor_hash_recovery_codes($newRecoveryCodes), (int)$userId]);
                    audit_log('two_factor_recovery_codes_regenerated', ['user_id' => (int)$userId]);
                    $messages[] = 'New recovery codes generated. Save them now; previous recovery codes no longer work.';
                }
            }

            if ($action === 'disable_two_factor') {
                $settings = two_factor_settings($pdo, (int)$userId);
                $code = (string)($_POST['two_factor_code_manage'] ?? '');

                if (!password_verify((string)($_POST['current_password_2fa_manage'] ?? ''), $userWithPassword['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                } elseif (!account_two_factor_challenge_valid($pdo, (int)$userId, $settings, $code)) {
                    $errors[] = 'Invalid two-factor code.';
                }

                if (!$errors) {
                    $pdo->prepare('DELETE FROM user_two_factor WHERE user_id = ?')->execute([(int)$userId]);
                    unset($_SESSION['pending_2fa_secret']);
                    refresh_session_security();
                    audit_log('two_factor_disabled', ['user_id' => (int)$userId]);
                    $messages[] = 'Two-factor authentication disabled.';
                }
            }

            if ($action === 'remove_avatar') {
                delete_avatar_file($userWithPassword['avatar_path'] ?? '');
                $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?')->execute([$userId]);
                $user = load_user($pdo, $userId);
                set_user_session($user);
                $messages[] = 'Profile photo removed.';
            }

            if ($action === 'upload_avatar') {
                if (!isset($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
                    $errors[] = 'Please choose an image to upload.';
                } elseif (($_FILES['avatar']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    $errors[] = 'Upload failed. Please try another image.';
                } else {
                    $tmp = $_FILES['avatar']['tmp_name'];
                    $size = (int)($_FILES['avatar']['size'] ?? 0);
                    $limits = avatar_upload_limits();
                    if (!is_uploaded_file($tmp)) {
                        $errors[] = 'Upload failed. Please try another image.';
                    }
                    if ($size <= 0 || $size > $limits['max_bytes']) {
                        $errors[] = 'Profile photo must be 5 MB or smaller.';
                    }

                    $allowed = $limits['allowed_mimes'];
                    $mime = '';
                    if (!$errors) {
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime = (string)$finfo->file($tmp);
                    }
                    if (!$errors && !isset($allowed[$mime])) {
                        $errors[] = 'Only JPG, PNG, and WEBP images are allowed.';
                    }

                    $imageInfo = $errors ? false : @getimagesize($tmp);
                    if (!$errors && !is_array($imageInfo)) {
                        $errors[] = 'Uploaded file must be a valid image.';
                    }
                    if (!$errors && ($imageInfo['mime'] ?? '') !== $mime) {
                        $errors[] = 'Uploaded image type does not match its content.';
                    }
                    if (!$errors && ((int)$imageInfo[0] <= 0 || (int)$imageInfo[1] <= 0)) {
                        $errors[] = 'Uploaded image has invalid dimensions.';
                    }
                    if (!$errors && ((int)$imageInfo[0] > $limits['max_width'] || (int)$imageInfo[1] > $limits['max_height'])) {
                        $errors[] = 'Profile photo dimensions must be 4096 by 4096 pixels or smaller.';
                    }

                    if (!$errors) {
                        $dir = public_root_path('uploads/avatars');
                        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
                            $errors[] = 'Could not create avatar upload directory.';
                        } else {
                            $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
                            $destination = $dir . '/' . $filename;
                            if (!move_uploaded_file($tmp, $destination)) {
                                $errors[] = 'Unable to save uploaded image.';
                            } else {
                                @chmod($destination, 0644);
                                delete_avatar_file($userWithPassword['avatar_path'] ?? '');
                                $avatarPath = '/uploads/avatars/' . $filename;
                                $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?')->execute([$avatarPath, $userId]);
                                $user = load_user($pdo, $userId);
                                set_user_session($user);
                                $messages[] = 'Profile photo updated successfully.';
                            }
                        }
                    }
                }
            }

            if ($action === 'delete_account') {
                $deletePassword = (string)($_POST['current_password_delete'] ?? '');
                $deleteConfirmText = strtoupper(trim((string)($_POST['delete_confirm_text'] ?? '')));
                $deleteConfirmed = isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === '1';

                if (!$deleteConfirmed) {
                    $errors[] = 'Please confirm that you understand account deletion is permanent.';
                }
                if ($deleteConfirmText !== 'DELETE') {
                    $errors[] = 'Type DELETE exactly to confirm account deletion.';
                }
                if (!password_verify($deletePassword, $userWithPassword['password_hash'])) {
                    $errors[] = 'Your current password is incorrect.';
                }

                if (!$errors) {
                    delete_user_account($pdo, (int)$userId);
                    audit_log('account_deleted', ['deleted_user_id' => (int)$userId]);
                    clear_auth_session();
                    redirect_to_route('login', ['deleted' => '1']);
                }
            }

            $stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (Throwable $e) {
            log_exception($e, 'Account update failed.', ['action' => $action ?? 'unknown', 'user_id' => (int)$userId]);
            $errors[] = 'Something went wrong while updating your account.';
        }
    }
}

$accountAvatarUrl = avatar_url($user['avatar_path'] ?? '');
$twoFactorSettings = two_factor_settings($pdo, (int)$userId);
$twoFactorEnabled = $twoFactorSettings !== null;
$pendingTwoFactorSecret = (string)($_SESSION['pending_2fa_secret'] ?? '');
$twoFactorSetupUri = $pendingTwoFactorSecret !== ''
    ? two_factor_otpauth_uri($pendingTwoFactorSecret, (string)($user['email'] ?? ''))
    : '';
render_layout_header();
?>
<h1 class="mb-4">Manage Account</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>
<?php if (!empty($user['pending_email'] ?? '')): ?>
    <div class="alert alert-info">
        Your login email is still <strong><?= h((string)$user['email']) ?></strong>.
        A change to <strong><?= h((string)$user['pending_email']) ?></strong> is waiting for verification.
        Check that inbox and click the verification link to complete the change.
    </div>
<?php endif; ?>
<?php if (!empty($newRecoveryCodes)): ?>
    <div class="alert alert-warning">
        <strong>Save these recovery codes now.</strong>
        <p class="mb-2">Each code can be used once if you lose access to your authenticator app.</p>
        <div class="row g-2">
            <?php foreach ($newRecoveryCodes as $code): ?>
                <div class="col-sm-6"><code><?= h($code) ?></code></div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card p-4 mb-4">
            <h2 class="h5 mb-3">Profile</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="mb-3">
                    <label for="display_name" class="form-label">Display Name</label>
                    <input type="text" id="display_name" name="display_name" class="form-control" maxlength="150"
                        value="<?= h((string)($user['display_name'] ?? '')) ?>">
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" required
                        value="<?= h((string)($user['email'] ?? '')) ?>">
                    <div class="form-text">Enter your current password below only if you are changing your email address.</div>
                </div>

                <div class="mb-3">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select" required>
                        <?php foreach (DateTimeZone::listIdentifiers() as $tzOption): ?>
                            <option value="<?= h($tzOption) ?>" <?= (($user['timezone'] ?? app_default_timezone()) === $tzOption) ? 'selected' : '' ?>>
                                <?= h($tzOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="current_password_for_profile" class="form-label">Current Password</label>
                    <input type="password" id="current_password_for_profile" name="current_password_for_profile" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Save Profile</button>
            </form>
        </div>

        <div class="card p-4">
            <h2 class="h5 mb-3">Change Password</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" minlength="12" required>
                    <div class="form-text">Use at least 12 characters with uppercase, lowercase, a number, and a symbol.</div>
                </div>

                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="12" required>
                </div>

                <button type="submit" class="btn btn-primary">Update Password</button>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card p-4">
            <h2 class="h5 mb-3">Profile Photo</h2>
            <div class="text-center mb-3">
                <?php if ($accountAvatarUrl !== null): ?>
                    <img src="<?= h($accountAvatarUrl) ?>" alt="<?= h(user_display_name($user)) ?>" class="account-avatar-large">
                <?php else: ?>
                    <div class="account-avatar-large-placeholder"><?= h(user_initials($user)) ?></div>
                <?php endif; ?>
            </div>

            <form method="post" enctype="multipart/form-data" class="mb-3">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_avatar">
                <div class="mb-3">
                    <label for="avatar" class="form-label">Upload New Photo</label>
                    <input type="file" id="avatar" name="avatar" class="form-control" accept="image/jpeg,image/png,image/webp" required>
                    <div class="form-text">JPG, PNG, or WEBP. Max 5 MB and 4096 by 4096 pixels.</div>
                </div>
                <button type="submit" class="btn btn-outline-primary">Upload Photo</button>
            </form>

            <?php if ($accountAvatarUrl !== null): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove_avatar">
                    <button type="submit" class="btn btn-outline-danger">Remove Photo</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card p-4 mt-4">
            <h2 class="h5 mb-2">Two-Factor Authentication</h2>
            <p class="text-muted mb-3">
                <?= $twoFactorEnabled ? 'Two-factor authentication is enabled for your account.' : 'Add an authenticator code requirement to your login.' ?>
            </p>

            <?php if ($twoFactorEnabled): ?>
                <form method="post" class="mb-3">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="regenerate_two_factor_recovery_codes">

                    <div class="mb-3">
                        <label for="current_password_2fa_recovery" class="form-label">Current Password</label>
                        <input type="password" id="current_password_2fa_recovery" name="current_password_2fa_manage" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="two_factor_code_recovery" class="form-label">Authenticator or Recovery Code</label>
                        <input type="text" id="two_factor_code_recovery" name="two_factor_code_manage" class="form-control" required autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn btn-outline-primary">Regenerate Recovery Codes</button>
                </form>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="disable_two_factor">

                    <div class="mb-3">
                        <label for="current_password_2fa_disable" class="form-label">Current Password</label>
                        <input type="password" id="current_password_2fa_disable" name="current_password_2fa_manage" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label for="two_factor_code_disable" class="form-label">Authenticator or Recovery Code</label>
                        <input type="text" id="two_factor_code_disable" name="two_factor_code_manage" class="form-control" required autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn btn-outline-danger">Disable Two-Factor</button>
                </form>
            <?php elseif ($pendingTwoFactorSecret !== ''): ?>
                <div class="mb-3">
                    <label class="form-label">Scan QR Code</label>
                    <div id="two_factor_qrcode" class="two-factor-qr" aria-label="Two-factor setup QR code"></div>
                    <div class="form-text">Scan this code with your authenticator app.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="two_factor_setup_key">Setup Key</label>
                    <input id="two_factor_setup_key" class="form-control font-monospace" value="<?= h($pendingTwoFactorSecret) ?>" readonly>
                    <div class="form-text">Use this key if your authenticator app cannot scan the QR code.</div>
                </div>
                <details class="mb-3">
                    <summary class="small text-muted">Show authenticator URI</summary>
                    <textarea id="two_factor_setup_uri" class="form-control font-monospace mt-2" rows="3" readonly><?= h($twoFactorSetupUri) ?></textarea>
                </details>

                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="confirm_two_factor_setup">

                    <div class="mb-3">
                        <label for="two_factor_code_confirm" class="form-label">Authenticator Code</label>
                        <input type="text" id="two_factor_code_confirm" name="two_factor_code" class="form-control" required inputmode="numeric" autocomplete="one-time-code">
                    </div>

                    <button type="submit" class="btn btn-primary">Enable Two-Factor</button>
                </form>
            <?php else: ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="start_two_factor_setup">

                    <div class="mb-3">
                        <label for="current_password_2fa" class="form-label">Current Password</label>
                        <input type="password" id="current_password_2fa" name="current_password_2fa" class="form-control" required autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn btn-outline-primary">Start Two-Factor Setup</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card border-danger p-4 mt-4">
            <h2 class="h5 mb-2">Danger Zone</h2>
            <p class="text-muted mb-3">Deleting your account permanently removes your profile and time-tracking data.</p>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                Delete My Account
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title fs-5 text-danger" id="deleteAccountModalLabel">Delete Account</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete_account">

                    <p class="mb-3">This action cannot be undone. All account data, projects, categories, and entries will be permanently deleted.</p>

                    <div class="mb-3">
                        <label for="current_password_delete" class="form-label">Current Password</label>
                        <input type="password" id="current_password_delete" name="current_password_delete" class="form-control" required autocomplete="current-password">
                    </div>

                    <div class="mb-3">
                        <label for="delete_confirm_text" class="form-label">Type <strong>DELETE</strong> to confirm</label>
                        <input type="text" id="delete_confirm_text" name="delete_confirm_text" class="form-control" required>
                    </div>

                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="confirm_delete" name="confirm_delete">
                        <label class="form-check-label" for="confirm_delete">
                            I understand this action is permanent.
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger" id="delete_account_submit" disabled>Delete Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($pendingTwoFactorSecret !== '' && $twoFactorSetupUri !== ''): ?>
    <script src="/assets/vendor/qrcodejs/qrcode.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var target = document.getElementById('two_factor_qrcode');
            if (!target || typeof QRCode === 'undefined') {
                return;
            }

            new QRCode(target, {
                text: <?= json_encode($twoFactorSetupUri, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>,
                width: 180,
                height: 180,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
<?php endif; ?>

<?php render_layout_footer(); ?>
