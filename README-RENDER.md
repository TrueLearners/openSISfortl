# openSIS on Render

This repository includes a Docker-based Apache/PHP deployment for Render.

## Important status

The image includes PHP 8.4.27, Apache, mysqli, common openSIS PHP extensions, and the AWS PHP SDK. The installer still creates the openSIS `Data.php` configuration file.

**Do not use this deployment for real student records yet.** Render's free filesystem is ephemeral. This blueprint does not provide a persistent disk, and B2 credentials alone do not redirect openSIS uploads to B2. The B2 SDK is included as a building block; upload/download integration and TiDB TLS support must be validated before production.

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

These variables are reserved for the B2 integration. They do not by themselves make uploads persistent yet.

## Before production

- Implement and test B2-backed file uploads, downloads, and deletes for every openSIS file area.
- Add TiDB TLS support to all installer and runtime mysqli connections.
- Set up automated database backups and test restoring them.
- Disable or remove the installer after setup.
- Use a paid always-on service and suitable persistent storage; test with fake records first.
