# Simple Shop (PHP + MySQL + S3)

## Requirements
- PHP 8.2+ with pdo_mysql, mbstring, openssl, curl, and Composer
- MySQL 8+ and Apache with DocumentRoot pointing to **public/**
- AWS S3 bucket with private objects and an IAM role or AWS SDK credentials

## Install
1. Run `composer install --no-dev --optimize-autoloader` in the project root.
2. Copy `.env.example` to `.env`, then configure MySQL and S3. Never expose `.env` through Apache.
3. Create the MySQL database and import `sql/schema.sql`, e.g. `mysql -u root -p simple_shop < sql/schema.sql`.
4. Create an admin password hash using `php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT), PHP_EOL;' 'your-long-password'`. Insert the resulting hash with `INSERT INTO admins(username,password_hash) VALUES ('admin','PASTE_HASH_HERE');`.
5. Set Apache `DocumentRoot /path/to/simple-shop/public` and enable HTTPS. Ensure `AllowOverride All` if using `.htaccess`.
6. Open `/admin/login.php` to manage products.

## AWS
Grant the application identity least-privilege `s3:GetObject`, `s3:PutObject` for `arn:aws:s3:::YOUR_BUCKET/products/*`. Prefer an EC2 instance role; don't place long-lived AWS keys in the web root. The SDK standard credential provider chain is used.

Configure bucket CORS to allow browser PUT from your exact storefront origin:
```json
[{"AllowedHeaders":["Content-Type"],"AllowedMethods":["PUT"],"AllowedOrigins":["https://yourdomain.example"],"ExposeHeaders":["ETag"],"MaxAgeSeconds":300}]
```
Keep S3 Block Public Access enabled. Photos are read using presigned GET URLs (15-minute lifetime) and uploaded using presigned PUT URLs (5-minute lifetime).

## Notes / production hardening
- This is a starter catalog/CMS, **not** a full checkout system.
- Upload size/type are checked by the browser and presign endpoint, but direct-to-S3 presigned PUT does not enforce an actual byte limit or inspect file contents. For production, use a presigned POST policy with a `content-length-range` condition, or quarantine uploads and validate actual file signatures before publication. The save endpoint currently checks object existence only.
- S3 photo objects are not deleted when products are removed or photos replaced, to avoid accidental data loss; add cleanup/lifecycle rules if desired.
- For production, add login rate limiting, image scanning, pagination, logging, backup and admin auditing. Consider S3 lifecycle rules for orphaned uploads.
- Restrict allowed `photo_key` values to server-issued uploads (e.g. track upload intents) before exposing this to untrusted admin accounts.

## Upgrade an existing installation to multiple photos
1. **Back up your database and application files.** Keep your existing `.env`, `vendor/`, and `admins` records. Do not overwrite `.env` or import `schema.sql` again.
2. From the extracted release, run `mysql -u YOUR_DB_USER -p YOUR_DB_NAME < sql/migrate_multi_photos.sql` (or run that SQL as an administrator). The migration creates `product_photos` and copies any existing `products.photo_key` values into it. The old column stays in place and is kept synchronized to the first photo.
3. Deploy the updated `app/bootstrap.php`, `public/index.php`, `public/products.php`, `public/admin/edit.php`, `public/admin/save.php`, and `public/assets/style.css`. Other files are unchanged. Preserve your `.env` and existing dependencies.
4. In the CMS, choose multiple files in the file picker. Existing photos can be removed or reordered with the arrow buttons. **Save product** commits changes after uploads finish. Removed photos remain in S3 (no S3 DeleteObject permission required).
5. Existing S3 CORS and IAM permissions for presigned PUT/GET remain applicable. `s3:HeadObject` uses GetObject permission. Each file is limited to 5 MB by the existing presign flow; there is no fixed total gallery size, although one save accepts up to 30 new images.

**Security note:** The starter's presigned PUT flow validates requested file metadata but does not verify the actual uploaded bytes or bind uploaded keys to a server-side upload-intent record. Harden this before allowing untrusted administrators to upload content. S3 presigned GET links expire after 15 minutes, so images on a page left open for a long time may need a refresh.

## Thumbnail upgrade (existing multi-photo installations)
1. Back up the database and project. Run `mysql -u YOUR_USER -p YOUR_DATABASE < sql/migrate_thumbnails.sql` **once**, after the multi-photo migration. Do not reimport schema.sql.
2. Deploy updated `app/`, `public/`, and `sql/` files; preserve your existing `.env` and `vendor/`.
3. For each new photo, the CMS generates a max-320px JPEG preview locally using the browser Canvas API and uploads it directly to S3 using a second presigned PUT URL. The original and thumbnail keys are stored separately. Browser image previews are shown immediately in the editor; saved thumbnails are shown in the CMS and on product listing pages. Clicking a gallery thumbnail loads the full-size original.
4. Existing photos remain usable with their original image as a fallback. Re-upload older photos if you want generated thumbnails for them.
5. Your S3 CORS policy must allow PUT from your site origin. IAM permissions must permit `s3:PutObject` and `s3:GetObject` for both `products/*` and `products/thumbs/*` (the latter is already covered by `products/*`).

Note: A failed upload may leave an unreferenced object in S3. Browser-side thumbnails are not an authoritative security validation of the original image; for untrusted administrators use server-side inspection or an S3 post-upload processing pipeline.

## Product descriptions

The `products.description` TEXT column is already part of the original database schema. The Add/Edit Product CMS form includes a required Description textarea, and both the home and Products pages render the saved description. This edition also displays descriptions in the admin product list. **No SQL migration is needed** if you installed the supplied schema. Verify with `SHOW COLUMNS FROM products LIKE 'description';`. If you created your own products table without this column, back up the database first and then run `ALTER TABLE products ADD COLUMN description TEXT NOT NULL;`.
