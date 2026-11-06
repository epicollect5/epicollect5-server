<?php

// Unique, non-empty malformed question regexes extracted from tools/audit.csv, an export
// across the existing projects. They were saved before there was a validation gate, so
// the upload validation path must reject them with ec5_392 now that one exists.
// Regenerate from tools/export-question-regex.sql output when the data is refreshed.

return [
    '(10,10}$',
    '****-***',
    '*.*',
    '+()',
    '.^{10,10}$',
    '[a-z0-9!#$%&\'*+/=?^_`{|}~-]+(?:\\.[a-z0-9!#$%&\'*+',
    '[below 18] + [between18- 60] + [above 60]',
    '^(0[1-9]|[12][0-9]|3[01])\\/(0[1-9]|1[0-2])\\/(19|20',
    '^(3[0-6][0]|3[0-5][0-9]|[12]?[0-9]{1,2})$ (Only al',
    '^.{1,00}$',
    '^.{1,10)$',
    '^.{1,1000000}$',
    '^.{1,3)$',
    '^.{1,9000000000000000000000000000000000000000000}$',
    '^.{1,9000000000000000000000000000000000000}$',
    '^.{10,00}$',
    '^.{2,20)$',
    '^[0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$',
    '^[10-10+$',
    '^[16-121]+$',
    '^[18-60]+$',
    '^[18-65]+$',
    '^[25-110]+$',
    '^[9-7]+$',
    '^[A-Z]*$,^{1,4}$',
    '^[\\w-\\.]+@([\\w-]+\\.)+[\\w-]{2,4}$',
    '^[a-zA-ZO-9._%+]+@[a-zA-ZO-9.-]+\\.[a-zA-Z]{2,}$',
    '^\\C\\M\\d\\d\\d\\d$',
    '^{1,2}$',
    '^{10}',
    '^{8,8}$',
    '^{8,9}$',
    '{10,10}',
    '{10,10}$',
    '{10,11}',
    '{13}$',
    '{2,20}$',
    '⁽1,6)$',
];
