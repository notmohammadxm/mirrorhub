-- MirrorHub schema upgrade
-- 001: Multi-protocol mirrors + hierarchical suggestion categories

CREATE TABLE IF NOT EXISTS mirror_protocols (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    mirror_id INT NOT NULL,
    protocol VARCHAR(32) NOT NULL,
    custom_label VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    UNIQUE KEY uq_mirror_protocol (mirror_id, protocol, custom_label),
    INDEX idx_mirror_protocol (mirror_id, sort_order),
    FOREIGN KEY (mirror_id) REFERENCES mirrors(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS request_protocols (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    protocol VARCHAR(32) NOT NULL,
    custom_label VARCHAR(100) DEFAULT NULL,
    sort_order INT DEFAULT 0,
    UNIQUE KEY uq_request_protocol (request_id, protocol, custom_label),
    INDEX idx_request_protocol (request_id, sort_order),
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE requests ADD COLUMN parent_category_id INT DEFAULT NULL;
ALTER TABLE requests ADD COLUMN category_id INT DEFAULT NULL;

ALTER TABLE requests
    ADD INDEX idx_requests_parent_category (parent_category_id),
    ADD INDEX idx_requests_category (category_id);

ALTER TABLE requests
    ADD CONSTRAINT fk_requests_parent_category
    FOREIGN KEY (parent_category_id) REFERENCES categories(id) ON DELETE SET NULL;

ALTER TABLE requests
    ADD CONSTRAINT fk_requests_category
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL;

INSERT IGNORE INTO mirror_protocols (mirror_id, protocol, custom_label, sort_order)
SELECT id,
       CASE WHEN protocol = 'other' THEN 'custom' ELSE protocol END,
       CASE WHEN protocol = 'other' THEN 'سایر' ELSE NULL END,
       0
FROM mirrors;

INSERT IGNORE INTO request_protocols (request_id, protocol, custom_label, sort_order)
SELECT id,
       CASE WHEN protocol = 'other' THEN 'custom' ELSE protocol END,
       CASE WHEN protocol = 'other' THEN 'سایر' ELSE NULL END,
       0
FROM requests
WHERE protocol IS NOT NULL;

UPDATE requests r
LEFT JOIN categories c ON c.name = r.category_name
SET r.category_id = COALESCE(r.category_id, c.id),
    r.parent_category_id = COALESCE(r.parent_category_id, c.parent_id)
WHERE r.category_name IS NOT NULL AND r.category_name <> '';
