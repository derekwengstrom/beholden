-- Run once after migrate_multi_photos.sql; existing images continue to work.
ALTER TABLE product_photos ADD COLUMN thumbnail_key VARCHAR(512) NULL AFTER photo_key;
CREATE INDEX idx_thumbnail_key ON product_photos (thumbnail_key);
-- Existing photos have no generated thumbnails; re-upload to generate them.
