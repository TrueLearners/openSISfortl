# openSIS on Render

This repository includes a Docker-based Apache/PHP deployment for Render.

## Important status

The image includes PHP 8.4.27, Apache, mysqli, common openSIS PHP extensions, and the AWS PHP SDK. The installer still creates the openSIS `Data.php` configuration file.

**Do not use this deployment for real student records yet.** Render's free filesystem is ephemeral. The main student/staff attachment upload screens now use the B2 adapter when B2 is configured, and `DownloadWindow.php` reads those B2 objects. Staff/student photos and school logos are stored in TiDB as database BLOBs, not in B2. The legacy student file screen (`modules/students/includes/Files.inc.php`) still uses local disk and is not covered. The integration still needs a successful live B2 test and full application testing before production.

## Render

1. Create a new Web Service from this repository.
2. Select Docker as the runtime.
3. Use the included `render.yaml` settings or set the health check path to `/healthz.php`.
4. Deploy and open the service URL to run the openSIS installer.

## TiDB Cloud

Use the TiDB cluster's MySQL-compatible endpoint, database, username, and password. TiDB Cloud normally requires TLS. Use the TLS-enabled connection option in the TiDB connection instructions and verify the installer connection before creating the database. Do not expose credentials in logs or commit them.

## Backblaze B2

Create a private bucket and an application key scoped to that bucket. Set the Render environment variables:

- `B2_REGION` (for example, `us-west-002`)
- `B2_BUCKET`
- `B2_KEY_ID`
- `B2_APPLICATION_KEY`
- `B2_ENDPOINT` (for example, `https://s3.us-west-002.backblazeb2.com`)

These variables activate the S3-compatible B2 adapter for the main student and staff attachment screens. The adapter stores objects under `opensis/studentfiles/` and `opensis/stafffiles/`, writes the object key marker into `user_file_upload.CONTENT`, and retrieves/deletes the object through the same adapter. `B2_REGION` is required for AWS Signature V4; `B2_ENDPOINT` should match that region. Keep the bucket private and use an application key limited to the bucket. If B2 is not configured, the adapter falls back to local disk, which is not persistent on Render Free.

## Before production

- Test B2 upload, download, and delete end-to-end with fake files; confirm the object appears in the private bucket and survives a Render restart.
- Migrate or disable the legacy student file screen before relying on B2 for all attachments.
- Verify TiDB TLS and database operations through the full installer and runtime.
- Set up automated database backups and test restoring them.
- Disable or remove the installer after setup.
- Use a paid always-on service and suitable persistent storage; test with fake records first.
