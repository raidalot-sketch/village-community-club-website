<?php
/**
 * Secure file upload handling for images, PDFs and (optionally) small videos.
 *
 * Never trusts the original filename or the browser-supplied MIME type.
 * Verifies the real file content type with finfo, generates a random safe
 * filename, and stores files in a category subfolder under /uploads.
 */

const UPLOAD_IMAGE_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];

const UPLOAD_PDF_TYPES = [
    'application/pdf' => 'pdf',
];

const UPLOAD_VIDEO_TYPES = [
    'video/mp4' => 'mp4',
    'video/webm' => 'webm',
];

/**
 * Handles one uploaded file (from $_FILES['field']).
 *
 * @param string $fieldName   Key in $_FILES
 * @param string $category    Subfolder under /uploads, e.g. 'news', 'events', 'gallery'
 * @param string $kind        'image' | 'pdf' | 'video'
 * @return array{ok:bool, path?:string, error?:string, width?:int, height?:int}
 */
function handle_upload(string $fieldName, string $category, string $kind = 'image'): array
{
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file was selected.'];
    }

    $file = $_FILES[$fieldName];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'The file is larger than this server allows.',
            UPLOAD_ERR_FORM_SIZE => 'The file is larger than allowed.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'Server upload error: no temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Server upload error: could not write file.',
        ];
        return ['ok' => false, 'error' => $errors[$file['error']] ?? 'Upload failed. Please try again.'];
    }

    $maxSize = match ($kind) {
        'pdf' => defined('MAX_UPLOAD_PDF_BYTES') ? MAX_UPLOAD_PDF_BYTES : 15 * 1024 * 1024,
        'video' => defined('MAX_UPLOAD_VIDEO_BYTES') ? MAX_UPLOAD_VIDEO_BYTES : 30 * 1024 * 1024,
        default => defined('MAX_UPLOAD_IMAGE_BYTES') ? MAX_UPLOAD_IMAGE_BYTES : 5 * 1024 * 1024,
    };
    if ($file['size'] > $maxSize) {
        return ['ok' => false, 'error' => 'That file is too large. Maximum size is ' . round($maxSize / 1024 / 1024, 1) . 'MB.'];
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'Upload failed a security check. Please try again.'];
    }

    $allowedTypes = match ($kind) {
        'pdf' => UPLOAD_PDF_TYPES,
        'video' => UPLOAD_VIDEO_TYPES,
        default => UPLOAD_IMAGE_TYPES,
    };

    // Verify the REAL content type by inspecting the file's bytes, not the
    // browser-supplied Content-Type header and not the file extension.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realMime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!isset($allowedTypes[$realMime])) {
        return ['ok' => false, 'error' => 'That file type is not allowed. Allowed types: ' . implode(', ', array_values($allowedTypes))];
    }
    $extension = $allowedTypes[$realMime];

    // Extra guard: for images, confirm getimagesize() also agrees it's a real image.
    if ($kind === 'image') {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false) {
            return ['ok' => false, 'error' => 'The file does not appear to be a valid image.'];
        }
    }

    $category = preg_replace('/[^a-z0-9\-]/', '', strtolower($category));
    $destDir = APP_ROOT . '/uploads/' . $category;
    if (!is_dir($destDir)) {
        mkdir($destDir, 0750, true);
        upload_dir_protect($destDir);
    }

    $safeName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destPath = $destDir . '/' . $safeName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['ok' => false, 'error' => 'Could not save the uploaded file. Please check folder permissions.'];
    }
    @chmod($destPath, 0640);

    $result = ['ok' => true, 'path' => $category . '/' . $safeName];

    if ($kind === 'image') {
        [$width, $height] = getimagesize($destPath);
        $result['width'] = $width;
        $result['height'] = $height;
        $thumbPath = create_thumbnail($destPath, $destDir . '/thumb_' . $safeName, $realMime);
        if ($thumbPath) {
            $result['thumbnail'] = $category . '/thumb_' . $safeName;
        }
        optimise_image($destPath, $realMime);
    }

    return $result;
}

/** Creates a max-400px-wide thumbnail alongside the original. Requires GD. */
function create_thumbnail(string $sourcePath, string $destPath, string $mime, int $maxWidth = 400): ?string
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $image = load_gd_image($sourcePath, $mime);
    if (!$image) {
        return null;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    $ratio = $maxWidth / $width;
    if ($ratio >= 1) {
        $newWidth = $width;
        $newHeight = $height;
    } else {
        $newWidth = $maxWidth;
        $newHeight = (int) round($height * $ratio);
    }
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    save_gd_image($thumb, $destPath, $mime);
    imagedestroy($image);
    imagedestroy($thumb);
    return $destPath;
}

/** Downscales very large images in place to keep page weight reasonable. */
function optimise_image(string $path, string $mime, int $maxDimension = 1920): void
{
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    $image = load_gd_image($path, $mime);
    if (!$image) {
        return;
    }
    $width = imagesx($image);
    $height = imagesy($image);
    if (max($width, $height) <= $maxDimension) {
        imagedestroy($image);
        return;
    }
    $ratio = $maxDimension / max($width, $height);
    $newWidth = (int) round($width * $ratio);
    $newHeight = (int) round($height * $ratio);
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($resized, false);
    imagesavealpha($resized, true);
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    save_gd_image($resized, $path, $mime);
    imagedestroy($image);
    imagedestroy($resized);
}

function load_gd_image(string $path, string $mime)
{
    return match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($path),
        'image/png' => @imagecreatefrompng($path),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function save_gd_image($image, string $path, string $mime): void
{
    match ($mime) {
        'image/jpeg' => imagejpeg($image, $path, 85),
        'image/png' => imagepng($image, $path, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($image, $path, 85) : imagejpeg($image, $path, 85),
        default => null,
    };
}

/** Writes a .htaccess into an uploads subfolder to prevent script execution. */
function upload_dir_protect(string $dir): void
{
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "php_flag engine off\nAddHandler cgi-script .php .php3 .php4 .php5 .php7 .phtml .pl .py .cgi .asp .aspx .sh .exe\nOptions -ExecCGI -Indexes\n");
    }
}

/** Safely deletes an upload (and its thumbnail, if any) from disk. */
function delete_upload(?string $relativePath): void
{
    if (empty($relativePath)) {
        return;
    }
    // Guard against directory traversal — only allow simple category/filename paths.
    if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
        return;
    }
    $full = APP_ROOT . '/uploads/' . $relativePath;
    if (is_file($full) && str_starts_with(realpath($full) ?: '', realpath(APP_ROOT . '/uploads') ?: '')) {
        @unlink($full);
    }
    $thumb = dirname($full) . '/thumb_' . basename($full);
    if (is_file($thumb)) {
        @unlink($thumb);
    }
}
