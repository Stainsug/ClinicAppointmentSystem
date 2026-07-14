-- Admin module bootstrap SQL

CREATE TABLE IF NOT EXISTS Admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin password: admin123
INSERT INTO Admin (username, password)
SELECT 'admin', 'admin123'
WHERE NOT EXISTS (
    SELECT 1 FROM Admin WHERE username = 'admin'
);
