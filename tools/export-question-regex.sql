-- Read-only export: every non-empty question regex across all projects with a structure.
-- No INTO OUTFILE (avoids ESCAPED BY backslash doubling).
-- Scope: all projects with a non-null project_extra ("all with structure").
-- Run with: mysql --default-character-set=utf8mb4 --batch --raw --quick < tools/export-question-regex.sql
-- Pipe the TSV output to your own Python csv.writer (QUOTE_MINIMAL, doublequote=True)
-- to produce the RFC 4180 CSV; no trim(), no delimiter stripping, no HTML decoding.
SELECT
  p.ref AS project_ref,
  p.slug AS project_slug,
  COALESCE(
    NULLIF(
      JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.ref'))),
      ''
    ),
    jt.input_key
  ) AS input_ref,
  JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.type'))) AS input_type,
  JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.question'))) AS input_name,
  JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.regex'))) AS regex,
  DATE_FORMAT(ps.updated_at, '%Y-%m-%d %H:%i:%s') AS structure_last_updated
FROM projects AS p
JOIN project_structures AS ps ON ps.project_id = p.id
CROSS JOIN JSON_TABLE(
  JSON_KEYS(ps.project_extra, '$.inputs'),
  '$[*]' COLUMNS (input_key VARCHAR(255) PATH '$')
) AS jt
WHERE ps.project_extra IS NOT NULL
  AND JSON_TYPE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.regex'))) = 'STRING'
  AND JSON_UNQUOTE(JSON_EXTRACT(ps.project_extra, CONCAT('$.inputs."', jt.input_key, '".data.regex'))) <> ''
ORDER BY project_ref, input_ref;
