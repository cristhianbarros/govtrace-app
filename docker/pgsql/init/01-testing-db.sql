-- Isolated database for the test suite (phpunit.xml -> DB_DATABASE=testing).
-- NOTE: Postgres only runs init/ scripts when the data directory is EMPTY.
-- If the volume already exists this file is silently ignored.
CREATE DATABASE testing;
