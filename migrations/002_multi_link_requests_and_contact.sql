-- MirrorHub schema upgrade
-- 002: Multi-link mirror suggestions + coordination contact info

ALTER TABLE requests
    MODIFY COLUMN url VARCHAR(1000) NOT NULL,
    ADD COLUMN email VARCHAR(190) DEFAULT NULL,
    ADD COLUMN phone VARCHAR(30) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS request_links (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    url VARCHAR(1000) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_request_links_request (request_id, sort_order),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO request_links (request_id, url, sort_order)
SELECT r.id, r.url, 0
FROM requests r
WHERE r.url IS NOT NULL AND r.url <> ''
  AND NOT EXISTS (
      SELECT 1 FROM request_links rl WHERE rl.request_id = r.id
  );
