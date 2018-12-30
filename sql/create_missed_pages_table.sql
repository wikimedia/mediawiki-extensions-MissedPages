-- Add missed_pages table
CREATE TABLE IF NOT EXISTS /*_*/missed_pages (
  `mp_id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `mp_datetime` DATETIME NOT NULL,
  `mp_page_title` VARBINARY(255) NOT NULL,
  `mp_ignore` BOOL NOT NULL DEFAULT FALSE
)/*$wgDBTableOptions*/;

CREATE INDEX /*i*/missed_pages_title ON /*_*/missed_pages (`mp_page_title`);
