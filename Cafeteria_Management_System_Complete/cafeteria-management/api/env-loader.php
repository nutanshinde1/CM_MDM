<?php
declare(strict_types=1);

function load_env(string \): void {
    if (!is_file(\)) {
        return;
    }
    \ = file(\, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (\ === false) {
        return;
    }
    foreach (\ as \) {
        \ = trim(\);
        if (\ === '' || strpos(\, '#') === 0) {
            continue;
        }
        if (strpos(\, '=') === false) {
            continue;
        }
        list(\, \) = explode('=', \, 2);
        \ = trim(\);
        \ = trim(\);
        if ((strlen(\) >= 2) && ((\[0] === '"' && \[strlen(\)-1] === '"') || (\[0] === "'" && \[strlen(\)-1] === "'"))) {
            \ = substr(\, 1, -1);
        }
        if (getenv(\) === false) {
            putenv("\=\");
            \[\] = \;
        }
    }
}
