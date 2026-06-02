<?php
require __DIR__ . '/middleware.php';
require_login();

$userId = user_id();
$page_title = 'Manage Account';

$stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) {
    http_response_code(404);
    exit('User not found.');
}

$messages = [];
$errors = [];

function load_user(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT id, email, display_name, avatar_path, timezone, password_hash FROM users WHERE id = ? LIMIT 1');
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

                    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
                    $check->execute([$email, $userId]);
                    if ($check->fetch()) {
                        $errors[] = 'That email address is already in use.';
                    }
                }

                if (!$errors) {
                    $pdo->prepare('UPDATE users SET display_name = ?, email = ?, timezone = ? WHERE id = ?')
                        ->execute([$displayName !== '' ? $displayName : null, $email, $timezone, $userId]);
                    $user = load_user($pdo, $userId);
                    set_user_session($user);
                    if ($emailChanged) {
                        refresh_session_security();
                    }
                    $messages[] = 'Profile updated successfully.';
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
                        $dir = __DIR__ . '/uploads/avatars';
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
                    header('Location: /auth/login.php?deleted=1');
                    exit;
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
include __DIR__ . '/header.php';
?>
<h1 class="mb-4">Manage Account</h1>

<?php foreach ($messages as $message): ?>
    <div class="alert alert-success"><?= h($message) ?></div>
<?php endforeach; ?>
<?php foreach ($errors as $error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
<?php endforeach; ?>

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

<?php include __DIR__ . '/footer.php'; ?>
