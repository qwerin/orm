START TRANSACTION;
INSERT INTO "photo_albums" ("title", "preview_id") VALUES ('album 1', NULL);
SELECT CURRVAL('photo_albums_id_seq');
INSERT INTO "photos" ("title", "album_id") VALUES ('photo 1', 1);
SELECT CURRVAL('photos_id_seq');
INSERT INTO "photos" ("title", "album_id") VALUES ('photo 2', 1);
SELECT CURRVAL('photos_id_seq');
INSERT INTO "photos" ("title", "album_id") VALUES ('photo 3', 1);
SELECT CURRVAL('photos_id_seq');
UPDATE "photo_albums" SET "preview_id" = 2 WHERE "id" = 1;
