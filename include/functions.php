<?php
declare(strict_types=1);

function json_response(array $data, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code($status);
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        http_response_code(500);
        $json = '{"success":false,"message":"Unable to encode API response."}';
    }
    echo $json;
    exit;
}

function app_setting(string $key, $default = null, $branchId = null)
{
    static $cache = [];
    $cacheKey = ($branchId === null ? 'platform' : (string) (int) $branchId) . ':' . $key;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        if ($branchId === null) {
            $stmt = db()->prepare(
                'SELECT setting_value FROM app_settings
                 WHERE setting_key = :setting_key AND branch_id IS NULL LIMIT 1'
            );
            $stmt->execute([':setting_key' => $key]);
        } else {
            $stmt = db()->prepare(
                'SELECT setting_value FROM app_settings
                 WHERE setting_key = :setting_key
                   AND (branch_id = :branch_id OR branch_id IS NULL)
                 ORDER BY CASE WHEN branch_id = :branch_order THEN 0 ELSE 1 END
                 LIMIT 1'
            );
            $stmt->execute([
                ':setting_key' => $key,
                ':branch_id' => (int) $branchId,
                ':branch_order' => (int) $branchId,
            ]);
        }
        $value = $stmt->fetchColumn();
        $cache[$cacheKey] = $value === false ? $default : $value;
    } catch (Throwable $exception) {
        $cache[$cacheKey] = $default;
    }
    return $cache[$cacheKey];
}

function save_app_setting(
    string $key,
    string $value,
    string $description,
    int $userId,
    $branchId = null
): void {
    $select = $branchId === null
        ? 'SELECT id FROM app_settings WHERE setting_key = :setting_key AND branch_id IS NULL LIMIT 1'
        : 'SELECT id FROM app_settings WHERE setting_key = :setting_key AND branch_id = :branch_id LIMIT 1';
    $stmt = db()->prepare($select);
    $params = [':setting_key' => $key];
    if ($branchId !== null) {
        $params[':branch_id'] = (int) $branchId;
    }
    $stmt->execute($params);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        $stmt = db()->prepare(
            'UPDATE app_settings SET setting_value = :setting_value,
             description = :description, updated_by = :updated_by, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':setting_value' => $value,
            ':description' => $description,
            ':updated_by' => $userId,
            ':id' => (int) $id,
        ]);
        return;
    }

    $stmt = db()->prepare(
        'INSERT INTO app_settings
         (branch_id, setting_key, setting_value, description, updated_by, created_at, updated_at)
         VALUES (:branch_id, :setting_key, :setting_value, :description, :updated_by, NOW(), NOW())'
    );
    $stmt->execute([
        ':branch_id' => $branchId === null ? null : (int) $branchId,
        ':setting_key' => $key,
        ':setting_value' => $value,
        ':description' => $description,
        ':updated_by' => $userId,
    ]);
}

function json_success(string $message, array $data = [], int $status = 200): void
{
    json_response(['success' => true, 'message' => $message, 'data' => $data], $status);
}

function json_error(string $message, int $status = 400, array $errors = []): void
{
    $response = ['success' => false, 'message' => $message];
    if ($errors !== []) {
        $response['errors'] = $errors;
    }
    json_response($response, $status);
}

function request_method(): string
{
    $method = strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
    if ($method === 'POST' && isset($_POST['_method'])) {
        $override = strtoupper((string) $_POST['_method']);
        if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
            return $override;
        }
    }
    return $method;
}

function request_data(): array
{
    static $data = null;
    if (is_array($data)) {
        return $data;
    }

    $contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw ?: '{}', true);
        if (!is_array($decoded)) {
            json_error('Invalid JSON request.', 422);
        }
        $data = $decoded;
    } else {
        $data = $_POST;
        if ($data === [] && in_array(request_method(), ['PUT', 'PATCH'], true)) {
            parse_str((string) file_get_contents('php://input'), $data);
        }
    }

    unset($data['_method']);
    return $data;
}

function require_fields(array $data, array $fields): void
{
    $errors = [];

    foreach ($fields as $key => $value) {

        $field = is_int($key)
            ? (string) $value
            : (string) $key;

        $message = is_int($key)
            ? 'This field is required.'
            : (string) $value;

        $missing = !array_key_exists($field, $data);

        if (!$missing) {
            $fieldValue = $data[$field];

            if ($fieldValue === null) {
                $missing = true;

            } elseif (is_string($fieldValue)) {
                $missing = trim($fieldValue) === '';

            } elseif (is_array($fieldValue)) {
                $missing = count($fieldValue) === 0;

            } elseif (is_object($fieldValue) || is_resource($fieldValue)) {
                $missing = true;

            } else {
                $missing = trim((string) $fieldValue) === '';
            }
        }

        if ($missing) {
            $errors[$field] = $message !== ''
                ? $message
                : 'This field is required.';
        }
    }

    if ($errors !== []) {
        json_error(
            'Please complete the required fields.',
            422,
            $errors
        );
    }
}   

function positive_id($value, string $field = 'id'): int
{
    $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) {
        json_error('Invalid ' . $field . '.', 422, [$field => 'A positive number is required.']);
    }
    return (int) $id;
}

function normalize_status($value): int
{
    return (int) $value === 1 ? 1 : 0;
}

/** Normalize formatted browser values before API validation and database storage. */
function normalize_input_value(string $rule, $value): string
{
    $value = (string) $value;
    if (in_array($rule, ['mobile', 'aadhaar', 'pincode'], true)) {
        return (string) preg_replace('/\D+/', '', $value);
    }
    if (in_array($rule, ['pan', 'gst'], true)) {
        return strtoupper(trim($value));
    }
    if ($rule === 'email') {
        return trim($value);
    }
    return $value;
}

/** Reusable API validation matching assets/js/validation.js. */
function valid_input_value(string $rule, $value, array $options = []): bool
{
    $value = normalize_input_value($rule, $value);
    if ($rule === 'email') {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    $patterns = [
        'mobile' => '/^[6-9][0-9]{9}$/',
        'pan' => '/^[A-Z]{5}[0-9]{4}[A-Z]$/',
        'aadhaar' => '/^[2-9][0-9]{11}$/',
        'gst' => '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/',
        'pincode' => '/^[1-9][0-9]{5}$/',
        'integer' => '/^-?[0-9]+$/',
        'password' => '/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}$/',
    ];

    if ($rule === 'decimal') {
        $places = isset($options['decimal_places']) ? (int) $options['decimal_places'] : 2;
        $places = max(0, min(8, $places));
        $pattern = $places === 0
            ? '/^-?[0-9]+$/'
            : '/^-?(?:[0-9]+(?:\.[0-9]{1,' . $places . '})?|\.[0-9]{1,' . $places . '})$/';
        return preg_match($pattern, $value) === 1;
    }

    return isset($patterns[$rule]) && preg_match($patterns[$rule], $value) === 1;
}

function require_strong_password($value, string $field = 'password'): void
{
    $password = (string) $value;
    if (strlen($password) < 8 ||
        !preg_match('/[A-Z]/', $password) ||
        !preg_match('/[a-z]/', $password) ||
        !preg_match('/[0-9]/', $password) ||
        !preg_match('/[^A-Za-z0-9]/', $password)) {
        json_error(
            'Password must contain uppercase, lowercase, number and special character.',
            422,
            [$field => 'Enter a strong password with at least 8 characters.']
        );
    }
}

/**
 * Convert stored CSV IDs (for example "1,2,3,7,20") or an API numeric array
 * into a unique sorted integer array. All permission checks use integers.
 */
function normalize_csv_ids($value): array
{
    $items = is_array($value) ? $value : explode(',', (string) $value);
    $ids = [];
    foreach ($items as $item) {
        if (is_bool($item)) {
            continue;
        }
        $text = trim((string) $item);
        if ($text === '' || !preg_match('/^\d+$/', $text)) {
            continue;
        }
        $id = (int) $text;
        if ($id > 0 && $id <= 65535) {
            $ids[$id] = $id;
        }
    }
    ksort($ids, SORT_NUMERIC);
    return array_values($ids);
}

/** Normalize IDs before saving them to a VARCHAR CSV column. */
function csv_ids($value): string
{
    return implode(',', normalize_csv_ids($value));
}

function request_ip(): string
{
    return substr(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '', 0, 45);
}

function audit_log(int $userId, int $actionId, array $options = []): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO audit_logs
             (company_id, branch_id, user_id, menu_id, action_id, record_id,
              old_data, new_data, ip_address, created_at)
             VALUES (:company_id, :branch_id, :user_id, :menu_id, :action_id,
                     :record_id, :old_data, :new_data, :ip_address, NOW())'
        );
        $stmt->execute([
            ':company_id' => isset($options['company_id']) ? $options['company_id'] : null,
            ':branch_id' => isset($options['branch_id']) ? $options['branch_id'] : null,
            ':user_id' => $userId,
            ':menu_id' => isset($options['menu_id']) ? $options['menu_id'] : null,
            ':action_id' => $actionId,
            ':record_id' => isset($options['record_id']) ? $options['record_id'] : null,
            ':old_data' => isset($options['old_data']) ? json_encode($options['old_data']) : null,
            ':new_data' => isset($options['new_data']) ? json_encode($options['new_data']) : null,
            ':ip_address' => request_ip(),
        ]);
    } catch (Throwable $exception) {
        error_log('Audit log failed: ' . $exception->getMessage());
    }
}

function normalize_uploaded_files(string $field): array
{
    if (!isset($_FILES[$field])) {
        return [];
    }

    $file = $_FILES[$field];
    if (!is_array($file['name'])) {
        return [$file];
    }

    $files = [];
    foreach ($file['name'] as $index => $name) {
        $files[] = [
            'name' => $name,
            'type' => $file['type'][$index],
            'tmp_name' => $file['tmp_name'][$index],
            'error' => $file['error'][$index],
            'size' => $file['size'][$index],
        ];
    }
    return $files;
}

/** Save image/video fields used by a normal form API. */
function save_form_uploads(string $field, string $folder, int $minFiles = 0, int $maxFiles = 10): array
{
    $files = array_values(array_filter(normalize_uploaded_files($field), function ($file) {
        return (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }));

    if (count($files) < $minFiles || count($files) > $maxFiles) {
        json_error('Invalid number of uploaded files.', 422);
    }
    if ($files === []) {
        return [];
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
    ];
    $imageMax = (int) env_value('UPLOAD_MAX_IMAGE_MB', 10) * 1024 * 1024;
    $videoMax = (int) env_value('UPLOAD_MAX_VIDEO_MB', 50) * 1024 * 1024;
    $basePath = trim((string) env_value('UPLOAD_PATH', ''));
    if ($basePath === '') {
        $basePath = APP_ROOT . '/uploads';
    }
    $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder);
    $destination = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($destination) && !mkdir($destination, 0755, true)) {
        throw new RuntimeException('Unable to create upload directory.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $saved = [];
    foreach ($files as $file) {
        if ((int) $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            json_error('One of the files could not be uploaded.', 422);
        }

        $mime = $finfo->file($file['tmp_name']);
        if (!isset($mimeMap[$mime])) {
            json_error('Only supported image and video files are allowed.', 422);
        }
        $maxBytes = strpos($mime, 'image/') === 0 ? $imageMax : $videoMax;
        if ((int) $file['size'] <= 0 || (int) $file['size'] > $maxBytes) {
            json_error('Uploaded file size is not allowed.', 422);
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $mimeMap[$mime];
        $target = $destination . DIRECTORY_SEPARATOR . $newName;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Unable to save uploaded file.');
        }

        $relative = 'uploads/' . $folder . '/' . $newName;
        $baseUrl = rtrim((string) env_value('UPLOAD_BASE_URL', ''), '/');
        $saved[] = [
            'name' => basename((string) $file['name']),
            'path' => $relative,
            'url' => $baseUrl !== '' ? $baseUrl . '/' . $folder . '/' . $newName : $relative,
            'mime' => $mime,
            'size' => (int) $file['size'],
        ];
    }

    return $saved;
}
