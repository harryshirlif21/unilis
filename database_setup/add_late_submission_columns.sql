-- Allow tracking and accepting late assignment submissions

ALTER TABLE `submissions`
  ADD COLUMN IF NOT EXISTS `is_late` TINYINT(1) NOT NULL DEFAULT 0 AFTER `submitted_at`;

ALTER TABLE `assignments`
  ADD COLUMN IF NOT EXISTS `allow_late_submission` TINYINT(1) NOT NULL DEFAULT 1 AFTER `deadline`;
