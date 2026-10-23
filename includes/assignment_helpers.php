<?php

function assignment_json_response(array $payload, int $status_code = 200): void
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function assignment_app_timezone(): DateTimeZone
{
    $timezone_name = getenv('SMARTLEARN_TIMEZONE');
    if ($timezone_name === false || trim($timezone_name) === '') {
        $timezone_name = date_default_timezone_get();
    }

    try {
        return new DateTimeZone($timezone_name);
    } catch (Throwable $exception) {
        throw new RuntimeException('SMARTLEARN_TIMEZONE is not a valid timezone.', 0, $exception);
    }
}

function assignment_parse_deadline(string $value): ?string
{
    if (trim($value) === '') {
        return null;
    }

    $deadline = DateTimeImmutable::createFromFormat(
        '!Y-m-d\TH:i',
        $value,
        assignment_app_timezone()
    );
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$deadline
        || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        || $deadline->format('Y-m-d\TH:i') !== $value
    ) {
        throw new InvalidArgumentException('Choose a valid assignment deadline.');
    }

    return $deadline->format('Y-m-d H:i:s');
}

function assignment_format_deadline(?string $value): ?DateTimeImmutable
{
    if ($value === null || $value === '') {
        return null;
    }

    $deadline = DateTimeImmutable::createFromFormat(
        '!Y-m-d H:i:s',
        $value,
        assignment_app_timezone()
    );
    if (!$deadline) {
        throw new RuntimeException('An assignment deadline could not be read.');
    }

    return $deadline;
}

function assignment_submission_time(array $submission): ?DateTimeImmutable
{
    if (isset($submission['submitted_at_epoch']) && $submission['submitted_at_epoch'] !== null) {
        return (new DateTimeImmutable('@' . (int) $submission['submitted_at_epoch']))
            ->setTimezone(assignment_app_timezone());
    }
    if (empty($submission['submitted_at'])) {
        return null;
    }

    return new DateTimeImmutable($submission['submitted_at'], assignment_app_timezone());
}

function assignment_parse_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return PHP_INT_MAX;
    }

    $unit = strtolower(substr($value, -1));
    $amount = (float) $value;
    if ($unit === 'g') {
        $amount *= 1024;
    }
    if ($unit === 'g' || $unit === 'm') {
        $amount *= 1024;
    }
    if ($unit === 'g' || $unit === 'm' || $unit === 'k') {
        $amount *= 1024;
    }

    return (int) min($amount, PHP_INT_MAX);
}

function assignment_max_file_bytes(): int
{
    $configured_limit = getenv('SMARTLEARN_ASSIGNMENT_MAX_FILE_BYTES');
    if ($configured_limit === false || $configured_limit === '') {
        $limit = 10 * 1024 * 1024;
    } elseif (!ctype_digit($configured_limit) || filter_var($configured_limit, FILTER_VALIDATE_INT) === false) {
        throw new RuntimeException('SMARTLEARN_ASSIGNMENT_MAX_FILE_BYTES must be a positive integer.');
    } else {
        $limit = (int) $configured_limit;
    }
    if ($limit < 1) {
        throw new RuntimeException('SMARTLEARN_ASSIGNMENT_MAX_FILE_BYTES must be a positive integer.');
    }

    return min(
        $limit,
        assignment_parse_ini_bytes((string) ini_get('upload_max_filesize')),
        assignment_parse_ini_bytes((string) ini_get('post_max_size'))
    );
}

function assignment_max_total_upload_bytes(): int
{
    $post_limit = assignment_parse_ini_bytes((string) ini_get('post_max_size'));
    $request_overhead = 256 * 1024;
    $usable_post_limit = $post_limit === PHP_INT_MAX
        ? PHP_INT_MAX
        : max(1, $post_limit - $request_overhead);

    return min(assignment_max_file_bytes() * 5, $usable_post_limit);
}

function assignment_storage_directory(): string
{
    $configured_path = getenv('SMARTLEARN_ASSIGNMENT_STORAGE_PATH');
    $directory = $configured_path !== false && trim($configured_path) !== ''
        ? trim($configured_path)
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'smartlearn-private'
            . DIRECTORY_SEPARATOR . 'assignment-files';

    if (!str_starts_with($directory, DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Assignment storage path must be absolute.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Private assignment storage could not be created.');
    }

    $resolved_directory = realpath($directory);
    if ($resolved_directory === false) {
        throw new RuntimeException('Private assignment storage could not be resolved.');
    }
    $document_root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (
        $document_root !== false
        && ($resolved_directory === $document_root
            || str_starts_with($resolved_directory, rtrim($document_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))
    ) {
        throw new RuntimeException('Assignment storage must be outside the public document root.');
    }

    if (!chmod($resolved_directory, 0700)) {
        throw new RuntimeException('Private assignment storage permissions could not be secured.');
    }

    return $resolved_directory;
}

function assignment_validate_uploads(array $files): array
{
    if (!isset($files['name']) || !is_array($files['name'])) {
        return [];
    }

    $uploads = [];
    $maximum_bytes = assignment_max_file_bytes();
    $maximum_count = 5;
    $file_count = count($files['name']);
    if ($file_count > $maximum_count) {
        throw new InvalidArgumentException('Attach no more than five files at a time.');
    }

    $allowed_mime_types = [
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'pdf' => ['application/pdf'],
        'html' => ['text/html', 'text/plain'],
        'htm' => ['text/html', 'text/plain'],
        'css' => ['text/css', 'text/plain'],
        'js' => ['application/javascript', 'text/javascript', 'application/x-javascript', 'text/plain'],
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $total_size = 0;
    for ($index = 0; $index < $file_count; $index++) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(
                $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
                    ? 'Each file must be no larger than ' . number_format($maximum_bytes / 1024 / 1024, 1) . ' MB.'
                    : 'A file could not be uploaded. Keep your response and try again.'
            );
        }

        $temporary_path = (string) ($files['tmp_name'][$index] ?? '');
        $size = (int) ($files['size'][$index] ?? 0);
        if ($size < 1 || $size > $maximum_bytes || !is_uploaded_file($temporary_path)) {
            throw new InvalidArgumentException(
                'Each file must be non-empty and no larger than '
                . number_format($maximum_bytes / 1024 / 1024, 1) . ' MB.'
            );
        }
        $total_size += $size;
        if ($total_size > assignment_max_total_upload_bytes()) {
            throw new InvalidArgumentException(
                'The selected files exceed the total upload limit of '
                . number_format(assignment_max_total_upload_bytes() / 1024 / 1024, 1) . ' MB.'
            );
        }

        $original_name = basename(str_replace('\\', '/', (string) ($files['name'][$index] ?? '')));
        $original_name = preg_replace('/[\x00-\x1F\x7F]/u', '', $original_name);
        if (!is_string($original_name) || $original_name === '' || strlen($original_name) > 255) {
            throw new InvalidArgumentException('A file name is invalid or too long.');
        }

        $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!isset($allowed_mime_types[$extension])) {
            throw new InvalidArgumentException('Use ZIP, PDF, HTML, CSS, JavaScript, PNG, or JPEG files only.');
        }

        $mime_type = $finfo->file($temporary_path);
        if (!in_array($mime_type, $allowed_mime_types[$extension], true)) {
            throw new InvalidArgumentException('A file’s content does not match its allowed file type.');
        }

        $content = file_get_contents($temporary_path);
        if ($content === false) {
            throw new InvalidArgumentException('A file could not be inspected. Keep your response and try again.');
        }
        if (in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            $image_info = @getimagesize($temporary_path);
            $expected_type = $extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
            if ($image_info === false || $image_info[2] !== $expected_type) {
                throw new InvalidArgumentException('An image file is not a valid PNG or JPEG image.');
            }
        } elseif ($extension === 'pdf' && !str_starts_with($content, '%PDF-')) {
            throw new InvalidArgumentException('A PDF file has invalid content.');
        } elseif ($extension === 'zip' && !preg_match('/^PK(?:\x03\x04|\x05\x06|\x07\x08)/', $content)) {
            throw new InvalidArgumentException('A ZIP file has invalid content.');
        } elseif (in_array($extension, ['html', 'htm', 'css', 'js'], true)) {
            if (str_contains($content, "\0") || !mb_check_encoding($content, 'UTF-8')) {
                throw new InvalidArgumentException('HTML, CSS, and JavaScript attachments must be valid text files.');
            }
        }

        $uploads[] = [
            'temporary_path' => $temporary_path,
            'original_name' => $original_name,
            'extension' => $extension,
            'mime_type' => $mime_type,
            'file_size' => $size,
        ];
    }

    return $uploads;
}

function assignment_move_upload(array $upload, string $storage_directory): array
{
    $stored_name = bin2hex(random_bytes(32));
    if (!move_uploaded_file($upload['temporary_path'], $storage_directory . DIRECTORY_SEPARATOR . $stored_name)) {
        throw new RuntimeException('An assignment attachment could not be stored.');
    }
    if (!chmod($storage_directory . DIRECTORY_SEPARATOR . $stored_name, 0600)) {
        unlink($storage_directory . DIRECTORY_SEPARATOR . $stored_name);
        throw new RuntimeException('An assignment attachment could not be secured.');
    }

    $upload['stored_name'] = $stored_name;
    return $upload;
}

function assignment_store_attachment_records(
    mysqli $mysqli,
    int $submission_id,
    array $uploads,
    string $storage_directory,
    array &$moved_paths
): void {
    $insert = $mysqli->prepare(
        'INSERT INTO learning_assignment_attachments
            (submission_id, original_name, stored_name, mime_type, file_size, status)
         VALUES (?, ?, ?, ?, ?, \'draft\')'
    );

    try {
        foreach ($uploads as $upload) {
            $stored_upload = assignment_move_upload($upload, $storage_directory);
            $stored_path = $storage_directory . DIRECTORY_SEPARATOR . $stored_upload['stored_name'];
            $moved_paths[] = $stored_path;
            $insert->bind_param(
                'isssi',
                $submission_id,
                $stored_upload['original_name'],
                $stored_upload['stored_name'],
                $stored_upload['mime_type'],
                $stored_upload['file_size']
            );
            $insert->execute();
        }
    } finally {
        $insert->close();
    }
}

function assignment_display_status(array $submission, ?DateTimeImmutable $deadline): string
{
    if (empty($submission['submitted_at'])) {
        return 'Draft';
    }

    if ($submission['grade'] !== null) {
        return trim((string) ($submission['instructor_feedback'] ?? '')) !== '' ? 'Returned' : 'Graded';
    }

    if ($deadline !== null) {
        $submitted_at = assignment_submission_time($submission);
        if ($submitted_at && $submitted_at > $deadline) {
            return 'Late';
        }
    }

    return 'Submitted';
}

function assignment_was_submitted_late(array $submission, ?DateTimeImmutable $deadline): bool
{
    if (empty($submission['submitted_at']) || $deadline === null) {
        return false;
    }

    $submitted_at = assignment_submission_time($submission);
    return $submitted_at !== null && $submitted_at > $deadline;
}
