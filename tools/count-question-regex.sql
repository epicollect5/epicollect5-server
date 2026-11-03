-- Read-only count: must equal (export CSV lines - 1 header).
-- Same FROM/WHERE as tools/export-question-regex.sql; run in the same window as the export.
-- Run with: mysql --default-character-set=utf8mb4 --batch --raw --quick < tools/count-question-regex.sql
SELECT COUNT(*) AS regex_input_count
FROM projects AS p
JOIN project_structures AS ps ON ps.project_id = p.id
CROSS JOIN JSON_TABLE(
  JSON_KEYS(ps.project_extra, '$.inputs'),
  '$[*]' COLUMNS (input_key VARCHAR(255) PATH '$')
) AS jt
WHERE ps.project_extra IS NOT NULL
  AND JSON_TYPE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.regex'))) = 'STRING'
  AND JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.regex'))) <> '';
