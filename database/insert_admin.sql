-- Insert Admin User
-- Username: admin
-- Password: admin123
-- Email: admin@etranzact.com

INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `changed_password`) 
VALUES (
    'admin', 
    'admin@etranzact.com', 
    '$2y$12$Uve0lGdSjaf5aEfpMf3gu.DPbqrLc.zTK5bY98cjMWozW44wuOOXC', 
    'Admin User', 
    'admin', 
    1, 
    0
);

-- Alternative: Create a custom admin user
-- Uncomment and modify the values below if you want different credentials
-- Note: You'll need to generate a new password hash using PHP password_hash() function

/*
INSERT INTO `users` (`username`, `email`, `password_hash`, `full_name`, `role`, `is_active`, `changed_password`) 
VALUES (
    'yourusername',           -- Change this
    'your@email.com',         -- Change this
    'HASH_HERE',              -- Generate using: password_hash('yourpassword', PASSWORD_BCRYPT, ['cost' => 12])
    'Your Full Name',         -- Change this
    'admin', 
    1, 
    0
);
*/
