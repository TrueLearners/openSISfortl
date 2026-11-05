<?php
/**
 * Remote file storage for openSIS on ephemeral hosts such as Render.
 *
 * When B2 is configured, files are stored in Backblaze B2 through its
 * S3-compatible API. When B2 is not configured, legacy local storage remains
 * available for non-ephemeral deployments.
 */
if (!function_exists('opensis_b2_client')) {
    function opensis_b2_configured()
    {
        return getenv('B2_BUCKET') && getenv('B2_KEY_ID') &&
            getenv('B2_APPLICATION_KEY') && getenv('B2_REGION');
    }

    function opensis_b2_client()
    {
        static $client = null;
        if ($client !== null) {
            return $client;
        }
        if (!opensis_b2_configured()) {
            throw new RuntimeException('Backblaze B2 is not configured. Set B2_BUCKET, B2_KEY_ID, B2_APPLICATION_KEY, and B2_ENDPOINT or B2_REGION.');
        }

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            throw new RuntimeException('AWS SDK for PHP is missing. Rebuild the application image.');
        }
        require_once $autoload;

        $region = getenv('B2_REGION') ?: '';
        $endpoint = getenv('B2_ENDPOINT') ?: ('https://s3.' . $region . '.backblazeb2.com');
        if (strpos($endpoint, 'https://') !== 0) {
            throw new RuntimeException('B2_ENDPOINT must use HTTPS.');
        }

        $client = new Aws\S3\S3Client(array(
            'version' => 'latest',
            'region' => $region ?: 'us-east-1',
            'endpoint' => rtrim($endpoint, '/'),
            'credentials' => array(
                'key' => getenv('B2_KEY_ID'),
                'secret' => getenv('B2_APPLICATION_KEY'),
            ),
            'signature_version' => 'v4',
            'use_path_style_endpoint' => true,
        ));
        return $client;
    }

    function opensis_b2_key($category, $filename)
    {
        $category = preg_replace('/[^a-z0-9_-]/i', '', (string) $category);
        $filename = basename(str_replace(chr(0), '', (string) $filename));
        return 'opensis/' . $category . '/' . ltrim($filename, '/');
    }

    function opensis_file_store_uploaded($tmpPath, $category, $filename, $contentType = 'application/octet-stream')
    {
        if (!is_uploaded_file($tmpPath) && !is_file($tmpPath)) {
            throw new RuntimeException('Uploaded file is not available.');
        }
        if (!opensis_b2_configured()) {
            $directory = dirname(__DIR__) . '/assets/' . $category;
            if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException('Unable to create local upload directory.');
            }
            $destination = $directory . '/' . basename($filename);
            if (!move_uploaded_file($tmpPath, $destination) && !copy($tmpPath, $destination)) {
                throw new RuntimeException('Unable to save uploaded file.');
            }
            return 'IN_DIR';
        }

        $key = opensis_b2_key($category, $filename);
        opensis_b2_client()->putObject(array(
            'Bucket' => getenv('B2_BUCKET'),
            'Key' => $key,
            'SourceFile' => $tmpPath,
            'ContentType' => $contentType ?: 'application/octet-stream',
        ));
        return 'B2:' . $key;
    }

    function opensis_file_get_contents($storedValue, $category, $filename)
    {
        if (strpos((string) $storedValue, 'B2:') === 0) {
            $key = substr($storedValue, 3);
            $result = opensis_b2_client()->getObject(array(
                'Bucket' => getenv('B2_BUCKET'),
                'Key' => $key,
            ));
            return (string) $result['Body'];
        }

        $path = dirname(__DIR__) . '/assets/' . $category . '/' . basename($filename);
        if (is_file($path)) {
            return file_get_contents($path);
        }
        return false;
    }

    function opensis_file_delete($storedValue, $category, $filename)
    {
        if (strpos((string) $storedValue, 'B2:') === 0) {
            opensis_b2_client()->deleteObject(array(
                'Bucket' => getenv('B2_BUCKET'),
                'Key' => substr($storedValue, 3),
            ));
            return true;
        }
        $path = dirname(__DIR__) . '/assets/' . $category . '/' . basename($filename);
        return !is_file($path) || unlink($path);
    }
}
