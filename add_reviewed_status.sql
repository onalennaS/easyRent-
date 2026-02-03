-- Add 'reviewed' status to rental_applications table
ALTER TABLE rental_applications
MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'withdrawn', 'reviewed')
NOT NULL DEFAULT 'pending';

-- Optional: Update any existing records if needed
-- This is just in case there are any records that might need updating
-- UPDATE rental_applications SET status = 'pending' WHERE status NOT IN ('pending', 'approved', 'rejected', 'withdrawn', 'reviewed');
