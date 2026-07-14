-- Doctor Login Setup SQL
-- Supports dedicated doctor login with hash passwords.

ALTER TABLE Doctor
ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL;

