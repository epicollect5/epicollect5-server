-- Read-only export: every non-empty question regex across all projects with a structure.
-- No INTO OUTFILE (avoids ESCAPED BY backslash doubling).
-- Scope: all projects with a non-null project_extra ("all with structure").
-- Run with: mysql --default-character-set=utf8mb4 --batch --raw --skip-column-names --quick < tools/export-question-regex.sql
-- Output is JSONL: one JSON object per line, no header. Parse each line with json.loads()
-- and write the RFC 4180 CSV with your own Python csv.writer (QUOTE_MINIMAL, doublequote=True);
-- no trim(), no delimiter stripping, no HTML decoding.
-- Each row is JSON-encoded so literal tabs/newlines/backslashes inside question and regex
-- values cannot be mistaken for TSV column or row separators. --raw is required so the JSON
-- backslash escapes are written verbatim instead of being doubled by batch-mode escaping.
SELECT
  JSON_OBJECT(
    'project_ref', t.project_ref,
    'project_slug', t.project_slug,
    'input_ref', t.input_ref,
    'input_type', t.input_type,
    'input_name', t.input_name,
    'regex', t.regex,
    'structure_last_updated', t.structure_last_updated
  ) AS row_json
FROM (
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
) AS t
ORDER BY t.project_ref, t.input_ref;
