CREATE TABLE audit (
    id INT AUTO_INCREMENT NOT NULL,
    audit_key INT DEFAULT NULL,
    action VARCHAR(255) NOT NULL,
    timestamp DATETIME NOT NULL,
    user_id INT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    details TEXT DEFAULT NULL,
    PRIMARY KEY (id)
);