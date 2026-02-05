-- Add AM/PM schedule columns to sections table
-- Default values set to standard school hours
ALTER TABLE sections
ADD COLUMN IF NOT EXISTS am_start_time TIME DEFAULT '07:30:00',
    ADD COLUMN IF NOT EXISTS am_late_threshold TIME DEFAULT '08:00:00',
    ADD COLUMN IF NOT EXISTS am_end_time TIME DEFAULT '12:00:00',
    ADD COLUMN IF NOT EXISTS pm_start_time TIME DEFAULT '13:00:00',
    ADD COLUMN IF NOT EXISTS pm_late_threshold TIME DEFAULT '13:30:00',
    ADD COLUMN IF NOT EXISTS pm_end_time TIME DEFAULT '17:00:00';