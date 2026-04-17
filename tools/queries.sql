-- projects with at least one entry
SELECT ps.project_definition
FROM project_structures ps
         JOIN entries e ON e.project_id = ps.project_id
GROUP BY ps.project_id;

-- skip projects with empty forms (first form hack)
SELECT project_definition, project_mapping
FROM project_structures
WHERE JSON_LENGTH(project_definition, '$.project.forms[0].inputs') > 0;

-- select project titles, descriptions and the form question text for each public project
SELECT p.name                                                                       AS project_title,
       p.description                                                                AS project_description,
       p.small_description                                                          AS project_small_description,

       -- Form 1
       JSON_UNQUOTE(JSON_EXTRACT(ps.project_definition, '$.project.forms[0].name')) AS form_1_name,
       (SELECT GROUP_CONCAT(question SEPARATOR ', ')
        FROM JSON_TABLE(ps.project_definition, '$.project.forms[0].inputs[*]'
                        COLUMNS (question VARCHAR(255) PATH '$.question')) AS f1)   AS form_1_questions,

       -- Form 2
       JSON_UNQUOTE(JSON_EXTRACT(ps.project_definition, '$.project.forms[1].name')) AS form_2_name,
       (SELECT GROUP_CONCAT(question SEPARATOR ', ')
        FROM JSON_TABLE(ps.project_definition, '$.project.forms[1].inputs[*]'
                        COLUMNS (question VARCHAR(255) PATH '$.question')) AS f2)   AS form_2_questions,

       -- Form 3
       JSON_UNQUOTE(JSON_EXTRACT(ps.project_definition, '$.project.forms[2].name')) AS form_3_name,
       (SELECT GROUP_CONCAT(question SEPARATOR ', ')
        FROM JSON_TABLE(ps.project_definition, '$.project.forms[2].inputs[*]'
                        COLUMNS (question VARCHAR(255) PATH '$.question')) AS f3)   AS form_3_questions,

       -- Form 4
       JSON_UNQUOTE(JSON_EXTRACT(ps.project_definition, '$.project.forms[3].name')) AS form_4_name,
       (SELECT GROUP_CONCAT(question SEPARATOR ', ')
        FROM JSON_TABLE(ps.project_definition, '$.project.forms[3].inputs[*]'
                        COLUMNS (question VARCHAR(255) PATH '$.question')) AS f4)   AS form_4_questions,

       -- Form 5
       JSON_UNQUOTE(JSON_EXTRACT(ps.project_definition, '$.project.forms[4].name')) AS form_5_name,
       (SELECT GROUP_CONCAT(question SEPARATOR ', ')
        FROM JSON_TABLE(ps.project_definition, '$.project.forms[4].inputs[*]'
                        COLUMNS (question VARCHAR(255) PATH '$.question')) AS f5)   AS form_5_questions

FROM projects p
         JOIN
     project_structures ps ON p.id = ps.project_id
WHERE p.access = 'public'
  AND p.status = 'active';
