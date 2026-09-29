<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/include/bootstrap.php';

function fee_tenant_context(array $user): array
{
    if ((int)($user['role_type'] ?? 0) === 2) {
        json_error(
            'Course Fee Structure is available only for tenant users.',
            403
        );
    }

    $branchId = (int)($user['branch_id'] ?? 0);

    if ($branchId < 1) {
        json_error(
            'No active branch is assigned to your account.',
            403
        );
    }

    $stmt = db()->prepare(
        'SELECT
            b.id AS branch_id,
            b.company_id,
            b.branch_name,
            c.company_name
         FROM branches b
         INNER JOIN companies c
                 ON c.id=b.company_id
         WHERE b.id=:branch_id
           AND b.status=1
           AND c.status=1
         LIMIT 1'
    );

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Your assigned tenant branch is invalid or inactive.',
            403
        );
    }

    return [
        'branch_id' => (int)$row['branch_id'],
        'company_id' => (int)$row['company_id'],
        'branch_name' => (string)$row['branch_name'],
        'company_name' => (string)$row['company_name'],
    ];
}

function fee_require_schema(): void
{
    static $checked = false;

    if ($checked) return;

    $tables = [
        'college_courses',
        'college_fee_structures',
        'college_fee_structure_items',
    ];

    foreach ($tables as $tableName) {
        $stmt = db()->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=:table_name'
        );

        $stmt->execute([
            ':table_name' => $tableName
        ]);

        if ((int)$stmt->fetchColumn() !== 1) {
            json_error(
                'Course Fee Structure database setup is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing table: ' . $tableName
                ]
            );
        }
    }

    $structureColumns = [
        'id',
        'branch_id',
        'course_id',
        'structure_code',
        'structure_name',
        'effective_from',
        'total_amount',
        'default_installment_count',
        'notes',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    $itemColumns = [
        'id',
        'fee_structure_id',
        'branch_id',
        'fee_head',
        'amount',
        'mandatory',
        'sort_order',
        'status',
        'created_by',
        'created_at',
        'updated_at',
    ];

    foreach ([
        'college_fee_structures' => $structureColumns,
        'college_fee_structure_items' => $itemColumns,
    ] as $table => $columns) {
        $placeholders =
            implode(',', array_fill(0,count($columns),'?'));

        $stmt = db()->prepare(
            'SELECT COLUMN_NAME
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME=?
               AND COLUMN_NAME IN (' . $placeholders . ')'
        );

        $stmt->execute(
            array_merge([$table],$columns)
        );

        $found =
            array_map(
                'strtolower',
                array_column(
                    $stmt->fetchAll(PDO::FETCH_ASSOC),
                    'COLUMN_NAME'
                )
            );

        $missing =
            array_values(
                array_diff($columns,$found)
            );

        if ($missing !== []) {
            json_error(
                'Course Fee Structure database setup is incomplete.',
                500,
                [
                    'schema' =>
                        'Missing ' .
                        $table .
                        ' columns: ' .
                        implode(', ',$missing)
                ]
            );
        }
    }

    $checked = true;
}

function fee_ref_to_id($value): int
{
    if (!is_string($value) ||
        trim($value) === '') {
        json_error(
            'Fee Structure reference is required.',
            422,
            [
                'ref' =>
                    'Fee Structure reference is required.'
            ]
        );
    }

    try {
        return decryptReference(
            trim($value),
            'college_fee_structure'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Fee Structure reference.',
            422,
            [
                'ref' =>
                    'Invalid Fee Structure reference.'
            ]
        );
    }

    return 0;
}

function fee_item_ref_to_id(
    $value,
    string $field
): int {
    if (!is_string($value) ||
        trim($value) === '') {
        json_error(
            'Fee Component reference is required.',
            422,
            [
                $field =>
                    'Fee Component reference is required.'
            ]
        );
    }

    try {
        return decryptReference(
            trim($value),
            'college_fee_structure_item'
        );
    } catch (Throwable $e) {
        json_error(
            'Invalid Fee Component reference.',
            422,
            [
                $field =>
                    'Invalid Fee Component reference.'
            ]
        );
    }

    return 0;
}

function fee_nullable_text(
    $value,
    int $maxLength
): ?string {
    $value =
        trim((string)$value);

    return $value === ''
        ? null
        : mb_substr(
            $value,
            0,
            $maxLength
        );
}

function fee_positive_int(
    $value,
    string $field,
    string $label
): int {
    $text =
        trim((string)$value);

    if (!preg_match(
        '/^[1-9][0-9]*$/',
        $text
    )) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                $field =>
                    $label .
                    ' must be a whole number greater than zero.'
            ]
        );
    }

    return (int)$text;
}

function fee_nonnegative_int_value(
    $value
) {
    if ($value === '' ||
        $value === null) {
        return 0;
    }

    $text =
        trim((string)$value);

    return preg_match(
        '/^[0-9]+$/',
        $text
    )
        ? (int)$text
        : false;
}

function fee_amount_value(
    $value
) {
    if ($value === '' ||
        $value === null ||
        !is_numeric($value)) {
        return false;
    }

    $amount =
        round((float)$value,2);

    return $amount > 0
        ? $amount
        : false;
}

function fee_generate_code(
    int $branchId
): string {
    $stmt = db()->prepare(
        "SELECT structure_code
         FROM college_fee_structures
         WHERE branch_id=:branch_id
           AND structure_code REGEXP '^FEE[0-9]+$'
         ORDER BY
            CAST(
                SUBSTRING(structure_code,4)
                AS UNSIGNED
            ) DESC
         LIMIT 1"
    );

    $stmt->execute([
        ':branch_id' => $branchId
    ]);

    $last =
        (string)($stmt->fetchColumn() ?: '');

    $next = 1;

    if ($last !== '' &&
        preg_match(
            '/^FEE([0-9]+)$/i',
            $last,
            $m
        )) {
        $next =
            ((int)$m[1]) + 1;
    }

    return
        'FEE' .
        str_pad(
            (string)$next,
            4,
            '0',
            STR_PAD_LEFT
        );
}

function fee_course_options(
    int $branchId,
    ?int $includeId = null,
    bool $activeOnly = true
): array {
    $sql =
        'SELECT
            id,
            course_code,
            course_name,
            status
         FROM college_courses
         WHERE branch_id=:branch_id';

    $params = [
        ':branch_id' => $branchId
    ];

    if ($activeOnly) {
        if ($includeId &&
            $includeId > 0) {
            $sql .=
                ' AND (
                    status=1
                    OR id=:include_id
                  )';

            $params[':include_id'] =
                $includeId;
        } else {
            $sql .=
                ' AND status=1';
        }
    }

    $sql .=
        ' ORDER BY
            course_name,
            course_code';

    $stmt =
        db()->prepare($sql);

    $stmt->execute($params);

    $rows = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $rows[] = [
            'id' => (int)$row['id'],
            'course_code' =>
                (string)$row['course_code'],
            'course_name' =>
                (string)$row['course_name'],
            'status' =>
                (int)$row['status'],
        ];
    }

    return $rows;
}

function fee_course_record(
    int $branchId,
    int $courseId
): array {
    $stmt = db()->prepare(
        'SELECT
            id,
            course_code,
            course_name,
            status
         FROM college_courses
         WHERE id=:id
           AND branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $courseId,
        ':branch_id' => $branchId,
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'course_id' =>
                    'Selected Course is not available in this branch.'
            ]
        );
    }

    return [
        'id' => (int)$row['id'],
        'course_code' =>
            (string)$row['course_code'],
        'course_name' =>
            (string)$row['course_name'],
        'status' =>
            (int)$row['status'],
    ];
}

function fee_validate_structure(
    array $data,
    int $branchId,
    bool $isUpdate,
    ?array $old
): array {
    $courseId =
        fee_nonnegative_int_value(
            $data['course_id'] ?? ''
        );

    if ($courseId === false ||
        $courseId < 1) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'course_id' =>
                    'Select a Course.'
            ]
        );
    }

    $course =
        fee_course_record(
            $branchId,
            (int)$courseId
        );

    if ((int)$course['status'] !== 1) {
        $oldCourseId =
            $old
                ? (int)($old['course_id'] ?? 0)
                : 0;

        if (!$isUpdate ||
            $oldCourseId !== (int)$courseId) {
            json_error(
                'Course Fee Structure validation failed.',
                422,
                [
                    'course_id' =>
                        'Select an active Course.'
                ]
            );
        }
    }

    $structureName =
        trim(
            (string)(
                $data['structure_name'] ?? ''
            )
        );

    if ($structureName === '') {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'structure_name' =>
                    'Structure Name is required.'
            ]
        );
    }

    if (mb_strlen($structureName) > 150) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'structure_name' =>
                    'Structure Name cannot exceed 150 characters.'
            ]
        );
    }

    $effectiveFrom =
        trim(
            (string)(
                $data['effective_from'] ?? ''
            )
        );

    $date =
        DateTime::createFromFormat(
            'Y-m-d',
            $effectiveFrom
        );

    $dateErrors =
        DateTime::getLastErrors();

    if (
        !$date ||
        ($dateErrors !== false &&
         (
             ($dateErrors['warning_count'] ?? 0) > 0 ||
             ($dateErrors['error_count'] ?? 0) > 0
         )) ||
        $date->format('Y-m-d') !== $effectiveFrom
    ) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'effective_from' =>
                    'Enter a valid Effective From date.'
            ]
        );
    }

    $defaultInstallmentCount =
        fee_positive_int(
            $data['default_installment_count'] ?? '',
            'default_installment_count',
            'Default Installment Count'
        );

    $status =
        (int)($data['status'] ?? 1);

    if (!in_array(
        $status,
        [0,1],
        true
    )) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'status' =>
                    'Select a valid Status.'
            ]
        );
    }

    return [
        'course_id' =>
            (int)$courseId,
        'structure_name' =>
            $structureName,
        'effective_from' =>
            $effectiveFrom,
        'default_installment_count' =>
            $defaultInstallmentCount,
        'notes' =>
            fee_nullable_text(
                $data['notes'] ?? null,
                255
            ),
        'status' =>
            $status,
    ];
}

function fee_validate_items(
    array $items
): array {
    if ($items === []) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            [
                'items' =>
                    'Add at least one Fee Component.'
            ]
        );
    }

    $errors = [];
    $cleanRows = [];
    $seenHeads = [];
    $activeCount = 0;

    foreach (
        array_values($items)
        as $index => $row
    ) {
        $prefix =
            'items[' . $index . ']';

        if (!is_array($row)) {
            $errors[
                $prefix . '[fee_head]'
            ] =
                'Invalid Fee Component row.';

            continue;
        }

        $feeHead =
            trim(
                (string)(
                    $row['fee_head'] ?? ''
                )
            );

        if ($feeHead === '') {
            $errors[
                $prefix . '[fee_head]'
            ] =
                'Fee Head is required.';
        } elseif (
            mb_strlen($feeHead) > 120
        ) {
            $errors[
                $prefix . '[fee_head]'
            ] =
                'Fee Head cannot exceed 120 characters.';
        }

        $headKey =
            mb_strtolower($feeHead);

        if ($headKey !== '') {
            if (isset(
                $seenHeads[$headKey]
            )) {
                $errors[
                    $prefix . '[fee_head]'
                ] =
                    'This Fee Head is already entered above.';
            } else {
                $seenHeads[$headKey] =
                    $index;
            }
        }

        $amount =
            fee_amount_value(
                $row['amount'] ?? ''
            );

        if ($amount === false) {
            $errors[
                $prefix . '[amount]'
            ] =
                'Amount must be greater than zero.';
        }

        $mandatory =
            (int)($row['mandatory'] ?? 1);

        if (!in_array(
            $mandatory,
            [0,1],
            true
        )) {
            $errors[
                $prefix . '[mandatory]'
            ] =
                'Select Yes or No for Mandatory.';
        }

        $sortOrder =
            fee_nonnegative_int_value(
                $row['sort_order'] ?? 0
            );

        if ($sortOrder === false) {
            $errors[
                $prefix . '[sort_order]'
            ] =
                'Sort Order must be a whole number and cannot be negative.';
        }

        $status =
            (int)($row['status'] ?? 1);

        if (!in_array(
            $status,
            [0,1],
            true
        )) {
            $errors[
                $prefix . '[status]'
            ] =
                'Select a valid Status.';
        }

        if ($status === 1) {
            $activeCount++;
        }

        $cleanRows[$index] = [
            'ref' =>
                trim(
                    (string)(
                        $row['ref'] ?? ''
                    )
                ),
            'fee_head' =>
                $feeHead,
            'amount' =>
                $amount === false
                    ? 0.0
                    : (float)$amount,
            'mandatory' =>
                $mandatory,
            'sort_order' =>
                $sortOrder === false
                    ? 0
                    : (int)$sortOrder,
            'status' =>
                $status,
        ];
    }

    if ($activeCount < 1) {
        $errors['items'] =
            'At least one Fee Component must be Active.';
    }

    if ($errors !== []) {
        json_error(
            'Course Fee Structure validation failed.',
            422,
            $errors
        );
    }

    return $cleanRows;
}

function fee_total_from_items(
    array $items
): float {
    $total = 0.0;

    foreach ($items as $item) {
        if ((int)$item['status'] !== 1) {
            continue;
        }

        $total +=
            (float)$item['amount'];
    }

    return round($total,2);
}

function fee_assert_code_unique(
    int $branchId,
    string $structureCode,
    int $excludeId = 0
): void {
    $sql =
        'SELECT id
         FROM college_fee_structures
         WHERE branch_id=:branch_id
           AND structure_code=:structure_code';

    $params = [
        ':branch_id' =>
            $branchId,
        ':structure_code' =>
            $structureCode,
    ];

    if ($excludeId > 0) {
        $sql .=
            ' AND id<>:exclude_id';

        $params[':exclude_id'] =
            $excludeId;
    }

    $sql .=
        ' LIMIT 1';

    $stmt =
        db()->prepare($sql);

    $stmt->execute($params);

    if ($stmt->fetchColumn()) {
        json_error(
            'Course Fee Structure validation failed.',
            409,
            [
                'structure_code' =>
                    'Structure Code already exists.'
            ]
        );
    }
}

function fee_structure_record(
    array $ctx,
    int $id
): array {
    $stmt = db()->prepare(
        'SELECT
            fs.id,
            fs.branch_id,
            fs.course_id,
            fs.structure_code,
            fs.structure_name,
            fs.effective_from,
            fs.total_amount,
            fs.default_installment_count,
            fs.notes,
            fs.status,
            fs.created_by,
            fs.created_at,
            fs.updated_at,
            c.course_code,
            c.course_name,
            u.name AS created_by_name
         FROM college_fee_structures fs
         INNER JOIN college_courses c
                 ON c.id=fs.course_id
                AND c.branch_id=fs.branch_id
         LEFT JOIN users u
                ON u.id=fs.created_by
         WHERE fs.id=:id
           AND fs.branch_id=:branch_id
         LIMIT 1'
    );

    $stmt->execute([
        ':id' =>
            $id,
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $row =
        $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        json_error(
            'Course Fee Structure was not found.',
            404
        );
    }

    $row['id'] =
        (int)$row['id'];
    $row['branch_id'] =
        (int)$row['branch_id'];
    $row['course_id'] =
        (int)$row['course_id'];
    $row['total_amount'] =
        (float)$row['total_amount'];
    $row['default_installment_count'] =
        (int)$row['default_installment_count'];
    $row['status'] =
        (int)$row['status'];

    $row['ref'] =
        encryptReference(
            'college_fee_structure',
            (int)$row['id']
        );

    return $row;
}

function fee_item_rows(
    array $ctx,
    int $structureId
): array {
    $stmt = db()->prepare(
        'SELECT
            id,
            fee_head,
            amount,
            mandatory,
            sort_order,
            status,
            created_at,
            updated_at
         FROM college_fee_structure_items
         WHERE fee_structure_id=:fee_structure_id
           AND branch_id=:branch_id
         ORDER BY
            sort_order,
            fee_head,
            id'
    );

    $stmt->execute([
        ':fee_structure_id' =>
            $structureId,
        ':branch_id' =>
            (int)$ctx['branch_id'],
    ]);

    $rows = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $rows[] = [
            'ref' =>
                encryptReference(
                    'college_fee_structure_item',
                    (int)$row['id']
                ),
            'fee_head' =>
                (string)$row['fee_head'],
            'amount' =>
                (float)$row['amount'],
            'mandatory' =>
                (int)$row['mandatory'],
            'sort_order' =>
                (int)$row['sort_order'],
            'status' =>
                (int)$row['status'],
            'created_at' =>
                $row['created_at'],
            'updated_at' =>
                $row['updated_at'],
        ];
    }

    return $rows;
}

function fee_item_plan_dependency_count(
    PDO $pdo,
    int $itemId
): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM college_student_fee_plan_items
         WHERE source_fee_structure_item_id=:item_id'
    );

    $stmt->execute([
        ':item_id' => $itemId
    ]);

    return
        (int)$stmt->fetchColumn();
}

function fee_structure_plan_dependency_count(
    PDO $pdo,
    int $structureId
): int {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM college_student_fee_plans
         WHERE fee_structure_id=:structure_id'
    );

    $stmt->execute([
        ':structure_id' =>
            $structureId
    ]);

    return
        (int)$stmt->fetchColumn();
}

$method =
    request_method();

fee_require_schema();

/* -------------------------------------------------------------------------
 * FORM OPTIONS
 * ---------------------------------------------------------------------- */
if (
    $method === 'GET' &&
    isset($_GET['options'])
) {
    $access =
        require_permission(
            'course-fee-form.php',
            ACTION_VIEW
        );

    $ctx =
        fee_tenant_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    json_success(
        'Course Fee Structure form options loaded.',
        [
            'allowed_actions' =>
                $access['actions'],
            'next_structure_code' =>
                fee_generate_code($branchId),
            'courses' =>
                fee_course_options(
                    $branchId,
                    null,
                    true
                ),
            'branch' =>
                $ctx,
        ]
    );
}

/* -------------------------------------------------------------------------
 * SINGLE STRUCTURE
 * ---------------------------------------------------------------------- */
if (
    $method === 'GET' &&
    isset($_GET['ref'])
) {
    $access =
        require_permission(
            'course-fee-form.php',
            ACTION_VIEW
        );

    $ctx =
        fee_tenant_context(
            $access['user']
        );

    $id =
        fee_ref_to_id(
            $_GET['ref'] ?? ''
        );

    $structure =
        fee_structure_record(
            $ctx,
            $id
        );

    json_success(
        'Course Fee Structure loaded.',
        [
            'structure' =>
                $structure,
            'items' =>
                fee_item_rows(
                    $ctx,
                    $id
                ),
            'allowed_actions' =>
                $access['actions'],
            'courses' =>
                fee_course_options(
                    (int)$ctx['branch_id'],
                    (int)$structure['course_id'],
                    true
                ),
            'branch' =>
                $ctx,
        ]
    );
}

/* -------------------------------------------------------------------------
 * LIST / DATATABLE
 * ---------------------------------------------------------------------- */
if (
    $method === 'GET' &&
    isset($_GET['datatable'])
) {
    $access =
        require_permission(
            'course-fee-list.php',
            ACTION_VIEW
        );

    $ctx =
        fee_tenant_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $formMenu =
        menu_by_path(
            'course-fee-form.php'
        );

    $formActions =
        $formMenu
            ? effective_actions_for_menu(
                $access['user'],
                $formMenu
            )
            : [];

    $draw =
        max(
            0,
            (int)($_GET['draw'] ?? 0)
        );

    $start =
        max(
            0,
            (int)($_GET['start'] ?? 0)
        );

    $lengthRaw =
        (int)($_GET['length'] ?? 10);

    $length =
        $lengthRaw < 0
            ? 100000
            : max(
                1,
                min(
                    100000,
                    $lengthRaw
                )
            );

    $search =
        trim(
            (string)(
                $_GET['search']['value'] ?? ''
            )
        );

    $courseId =
        isset($_GET['course_id']) &&
        $_GET['course_id'] !== ''
            ? (int)$_GET['course_id']
            : 0;

    $status =
        isset($_GET['status']) &&
        $_GET['status'] !== ''
            ? (int)$_GET['status']
            : -1;

    $where = [
        'fs.branch_id=:branch_id'
    ];

    $params = [
        ':branch_id' =>
            $branchId
    ];

    if ($search !== '') {
        $like =
            '%' . $search . '%';

        $where[] =
            '(fs.structure_code LIKE :s_code
              OR fs.structure_name LIKE :s_name
              OR fs.notes LIKE :s_notes
              OR c.course_code LIKE :s_course_code
              OR c.course_name LIKE :s_course_name)';

        $params += [
            ':s_code' => $like,
            ':s_name' => $like,
            ':s_notes' => $like,
            ':s_course_code' => $like,
            ':s_course_name' => $like,
        ];
    }

    if ($courseId > 0) {
        $where[] =
            'fs.course_id=:course_id';

        $params[':course_id'] =
            $courseId;
    }

    if (in_array(
        $status,
        [0,1],
        true
    )) {
        $where[] =
            'fs.status=:status';

        $params[':status'] =
            $status;
    }

    $totalStmt =
        db()->prepare(
            'SELECT COUNT(*)
             FROM college_fee_structures
             WHERE branch_id=:branch_id'
        );

    $totalStmt->execute([
        ':branch_id' =>
            $branchId
    ]);

    $recordsTotal =
        (int)$totalStmt->fetchColumn();

    $countStmt =
        db()->prepare(
            'SELECT COUNT(*)
             FROM college_fee_structures fs
             INNER JOIN college_courses c
                     ON c.id=fs.course_id
                    AND c.branch_id=fs.branch_id
             WHERE ' .
             implode(
                 ' AND ',
                 $where
             )
        );

    $countStmt->execute($params);

    $recordsFiltered =
        (int)$countStmt->fetchColumn();

    $summaryStmt =
        db()->prepare(
            'SELECT
                COUNT(*) AS total_count,
                COALESCE(SUM(CASE WHEN fs.status=1 THEN 1 ELSE 0 END),0) AS active_count,
                COALESCE(SUM(CASE WHEN fs.status=0 THEN 1 ELSE 0 END),0) AS inactive_count,
                COALESCE(SUM(fs.total_amount),0) AS total_amount
             FROM college_fee_structures fs
             INNER JOIN college_courses c
                     ON c.id=fs.course_id
                    AND c.branch_id=fs.branch_id
             WHERE ' .
             implode(
                 ' AND ',
                 $where
             )
        );

    foreach (
        $params
        as $key => $value
    ) {
        $isInt =
            in_array(
                $key,
                [
                    ':branch_id',
                    ':course_id',
                    ':status',
                ],
                true
            );

        $summaryStmt->bindValue(
            $key,
            $value,
            $isInt
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $summaryStmt->execute();

    $summary =
        $summaryStmt->fetch(
            PDO::FETCH_ASSOC
        ) ?: [];

    $orderColumns = [
        'fs.structure_code',
        'c.course_name',
        'fs.structure_name',
        'fs.effective_from',
        'fs.total_amount',
        'fs.default_installment_count',
        'fs.status',
        'fs.created_at',
    ];

    $orderIndex =
        (int)(
            $_GET['order'][0]['column']
            ?? 3
        );

    $orderDir =
        strtolower(
            (string)(
                $_GET['order'][0]['dir']
                ?? 'desc'
            )
        ) === 'desc'
            ? 'DESC'
            : 'ASC';

    $orderBy =
        $orderColumns[$orderIndex]
        ?? 'fs.effective_from';

    $sql =
        'SELECT
            fs.id,
            fs.course_id,
            fs.structure_code,
            fs.structure_name,
            fs.effective_from,
            fs.total_amount,
            fs.default_installment_count,
            fs.notes,
            fs.status,
            fs.created_at,
            c.course_code,
            c.course_name
         FROM college_fee_structures fs
         INNER JOIN college_courses c
                 ON c.id=fs.course_id
                AND c.branch_id=fs.branch_id
         WHERE ' .
         implode(
             ' AND ',
             $where
         ) .
        ' ORDER BY ' .
            $orderBy .
            ' ' .
            $orderDir .
            ',
            fs.id DESC
          LIMIT :start,:length';

    $stmt =
        db()->prepare($sql);

    foreach (
        $params
        as $key => $value
    ) {
        $isInt =
            in_array(
                $key,
                [
                    ':branch_id',
                    ':course_id',
                    ':status',
                ],
                true
            );

        $stmt->bindValue(
            $key,
            $value,
            $isInt
                ? PDO::PARAM_INT
                : PDO::PARAM_STR
        );
    }

    $stmt->bindValue(
        ':start',
        $start,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':length',
        $length,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $rows = [];

    foreach (
        $stmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $row['course_id'] =
            (int)$row['course_id'];

        $row['total_amount'] =
            (float)$row['total_amount'];

        $row['default_installment_count'] =
            (int)$row['default_installment_count'];

        $row['status'] =
            (int)$row['status'];

        $row['course_label'] =
            $row['course_code'] .
            ' - ' .
            $row['course_name'];

        $row['status_label'] =
            $row['status'] === 1
                ? 'Active'
                : 'Inactive';

        $row['ref'] =
            encryptReference(
                'college_fee_structure',
                (int)$row['id']
            );

        $row['view_url'] =
            'course-fee-form.php?ref=' .
            rawurlencode(
                $row['ref']
            ) .
            '&view=1';

        $row['edit_url'] =
            'course-fee-form.php?ref=' .
            rawurlencode(
                $row['ref']
            );

        unset($row['id']);

        $rows[] =
            $row;
    }

    json_success(
        'Course Fee Structures loaded.',
        [
            'datatable' => [
                'draw' =>
                    $draw,
                'recordsTotal' =>
                    $recordsTotal,
                'recordsFiltered' =>
                    $recordsFiltered,
                'data' =>
                    $rows,
            ],
            'summary' => [
                'total_count' =>
                    (int)(
                        $summary['total_count'] ??
                        0
                    ),

                'active_count' =>
                    (int)(
                        $summary['active_count'] ??
                        0
                    ),

                'inactive_count' =>
                    (int)(
                        $summary['inactive_count'] ??
                        0
                    ),

                'total_amount' =>
                    (float)(
                        $summary['total_amount'] ??
                        0
                    ),
            ],
            'list_actions' =>
                $access['actions'],
            'form_actions' =>
                $formActions,
            'courses' =>
                fee_course_options(
                    $branchId,
                    null,
                    false
                ),
        ]
    );
}

/* -------------------------------------------------------------------------
 * SAVE / DELETE
 * ---------------------------------------------------------------------- */
if ($method === 'POST') {
    $data =
        request_data();

    $action =
        strtolower(
            trim(
                (string)(
                    $data['action'] ?? 'save'
                )
            )
        );

    if ($action === 'delete') {
        $access =
            require_permission(
                'course-fee-form.php',
                4
            );

        $ctx =
            fee_tenant_context(
                $access['user']
            );

        $branchId =
            (int)$ctx['branch_id'];

        $userId =
            (int)$access['user']['id'];

        $id =
            fee_ref_to_id(
                $data['ref'] ?? ''
            );

        $pdo =
            db();

        $planCount =
            fee_structure_plan_dependency_count(
                $pdo,
                $id
            );

        if ($planCount > 0) {
            json_error(
                'This Fee Structure is already used by ' .
                $planCount .
                ' Student Fee Plan' .
                ($planCount === 1 ? '' : 's') .
                '. Make it Inactive instead of deleting it.',
                409
            );
        }

        $old =
            fee_structure_record(
                $ctx,
                $id
            );

        $itemStmt =
            $pdo->prepare(
                'SELECT id
                 FROM college_fee_structure_items
                 WHERE fee_structure_id=:structure_id
                   AND branch_id=:branch_id'
            );

        $itemStmt->execute([
            ':structure_id' =>
                $id,
            ':branch_id' =>
                $branchId,
        ]);

        foreach (
            $itemStmt->fetchAll(PDO::FETCH_COLUMN)
            as $itemId
        ) {
            if (
                fee_item_plan_dependency_count(
                    $pdo,
                    (int)$itemId
                ) > 0
            ) {
                json_error(
                    'A Fee Component from this Structure is already used by a Student Fee Plan. Make the Structure Inactive instead of deleting it.',
                    409
                );
            }
        }

        $pdo->beginTransaction();

        try {
            $deleteItems =
                $pdo->prepare(
                    'DELETE FROM college_fee_structure_items
                     WHERE fee_structure_id=:structure_id
                       AND branch_id=:branch_id'
                );

            $deleteItems->execute([
                ':structure_id' =>
                    $id,
                ':branch_id' =>
                    $branchId,
            ]);

            $deleteStructure =
                $pdo->prepare(
                    'DELETE FROM college_fee_structures
                     WHERE id=:id
                       AND branch_id=:branch_id'
                );

            $deleteStructure->execute([
                ':id' =>
                    $id,
                ':branch_id' =>
                    $branchId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }

        audit_log(
            $userId,
            4,
            [
                'company_id' =>
                    (int)$ctx['company_id'],
                'branch_id' =>
                    $branchId,
                'menu_id' =>
                    (int)$access['menu']['id'],
                'record_id' =>
                    $id,
                'old_data' =>
                    $old,
            ]
        );

        json_success(
            'Course Fee Structure deleted successfully.'
        );
    }

    if ($action !== 'save') {
        json_error(
            'Unsupported Course Fee Structure action.',
            404
        );
    }

    $isUpdate =
        isset($data['ref']) &&
        is_string($data['ref']) &&
        trim($data['ref']) !== '';

    $access =
        require_permission(
            'course-fee-form.php',
            $isUpdate
                ? ACTION_UPDATE
                : ACTION_CREATE
        );

    $ctx =
        fee_tenant_context(
            $access['user']
        );

    $branchId =
        (int)$ctx['branch_id'];

    $userId =
        (int)$access['user']['id'];

    $pdo =
        db();

    $itemsRaw =
        isset($data['items']) &&
        is_array($data['items'])
            ? $data['items']
            : [];

    $removedRefs =
        isset($data['removed_items']) &&
        is_array($data['removed_items'])
            ? array_values(
                array_filter(
                    array_map(
                        static fn($value): string =>
                            trim((string)$value),
                        $data['removed_items']
                    ),
                    static fn(string $value): bool =>
                        $value !== ''
                )
            )
            : [];

    if ($removedRefs !== []) {
        require_permission(
            'course-fee-form.php',
            4
        );
    }

    $cleanItems =
        fee_validate_items(
            $itemsRaw
        );

    $oldStructure =
        null;

    $structureId =
        0;

    if ($isUpdate) {
        $structureId =
            fee_ref_to_id(
                $data['ref']
            );

        $oldStructure =
            fee_structure_record(
                $ctx,
                $structureId
            );

        $data['structure_code'] =
            (string)$oldStructure['structure_code'];
    }

    $cleanStructure =
        fee_validate_structure(
            $data,
            $branchId,
            $isUpdate,
            $oldStructure
        );

    if (!$isUpdate) {
        $structureCode =
            fee_generate_code(
                $branchId
            );
    } else {
        $structureCode =
            (string)$oldStructure['structure_code'];
    }

    fee_assert_code_unique(
        $branchId,
        $structureCode,
        $structureId
    );

    $totalAmount =
        fee_total_from_items(
            $cleanItems
        );

    $pdo->beginTransaction();

    try {
        if (!$isUpdate) {
            $stmt =
                $pdo->prepare(
                    'INSERT INTO college_fee_structures
                     (
                        branch_id,
                        course_id,
                        structure_code,
                        structure_name,
                        effective_from,
                        total_amount,
                        default_installment_count,
                        notes,
                        status,
                        created_by,
                        created_at,
                        updated_at
                     )
                     VALUES
                     (
                        :branch_id,
                        :course_id,
                        :structure_code,
                        :structure_name,
                        :effective_from,
                        :total_amount,
                        :default_installment_count,
                        :notes,
                        :status,
                        :created_by,
                        NOW(),
                        NOW()
                     )'
                );

            $stmt->execute([
                ':branch_id' =>
                    $branchId,
                ':course_id' =>
                    $cleanStructure['course_id'],
                ':structure_code' =>
                    $structureCode,
                ':structure_name' =>
                    $cleanStructure['structure_name'],
                ':effective_from' =>
                    $cleanStructure['effective_from'],
                ':total_amount' =>
                    $totalAmount,
                ':default_installment_count' =>
                    $cleanStructure['default_installment_count'],
                ':notes' =>
                    $cleanStructure['notes'],
                ':status' =>
                    $cleanStructure['status'],
                ':created_by' =>
                    $userId,
            ]);

            $structureId =
                (int)$pdo->lastInsertId();
        } else {
            $stmt =
                $pdo->prepare(
                    'UPDATE college_fee_structures
                     SET
                        course_id=:course_id,
                        structure_name=:structure_name,
                        effective_from=:effective_from,
                        total_amount=:total_amount,
                        default_installment_count=:default_installment_count,
                        notes=:notes,
                        status=:status,
                        updated_at=NOW()
                     WHERE id=:id
                       AND branch_id=:branch_id'
                );

            $stmt->execute([
                ':course_id' =>
                    $cleanStructure['course_id'],
                ':structure_name' =>
                    $cleanStructure['structure_name'],
                ':effective_from' =>
                    $cleanStructure['effective_from'],
                ':total_amount' =>
                    $totalAmount,
                ':default_installment_count' =>
                    $cleanStructure['default_installment_count'],
                ':notes' =>
                    $cleanStructure['notes'],
                ':status' =>
                    $cleanStructure['status'],
                ':id' =>
                    $structureId,
                ':branch_id' =>
                    $branchId,
            ]);
        }

        /*
         * Resolve removed existing Fee Components.
         */
        $removeIds = [];

        foreach (
            $removedRefs
            as $removeIndex => $removeRef
        ) {
            $itemId =
                fee_item_ref_to_id(
                    $removeRef,
                    'removed_items[' .
                    $removeIndex .
                    ']'
                );

            $stmt =
                $pdo->prepare(
                    'SELECT *
                     FROM college_fee_structure_items
                     WHERE id=:id
                       AND fee_structure_id=:structure_id
                       AND branch_id=:branch_id
                     LIMIT 1
                     FOR UPDATE'
                );

            $stmt->execute([
                ':id' =>
                    $itemId,
                ':structure_id' =>
                    $structureId,
                ':branch_id' =>
                    $branchId,
            ]);

            $oldItem =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$oldItem) {
                json_error(
                    'Removed Fee Component does not belong to this Fee Structure.',
                    422
                );
            }

            if (
                fee_item_plan_dependency_count(
                    $pdo,
                    $itemId
                ) > 0
            ) {
                json_error(
                    'Fee Component "' .
                    (string)$oldItem['fee_head'] .
                    '" is already used by a Student Fee Plan. Make it Inactive instead of removing it.',
                    409
                );
            }

            $removeIds[] =
                $itemId;
        }

        /*
         * Resolve current rows.
         */
        $resolved = [];
        $errors = [];

        foreach (
            $cleanItems
            as $index => $item
        ) {
            $itemId = 0;
            $oldItem = null;

            if ($item['ref'] !== '') {
                try {
                    $itemId =
                        decryptReference(
                            $item['ref'],
                            'college_fee_structure_item'
                        );
                } catch (Throwable $e) {
                    $errors[
                        'items[' .
                        $index .
                        '][fee_head]'
                    ] =
                        'Invalid Fee Component reference.';

                    continue;
                }

                $stmt =
                    $pdo->prepare(
                        'SELECT *
                         FROM college_fee_structure_items
                         WHERE id=:id
                           AND fee_structure_id=:structure_id
                           AND branch_id=:branch_id
                         LIMIT 1
                         FOR UPDATE'
                    );

                $stmt->execute([
                    ':id' =>
                        $itemId,
                    ':structure_id' =>
                        $structureId,
                    ':branch_id' =>
                        $branchId,
                ]);

                $oldItem =
                    $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$oldItem) {
                    $errors[
                        'items[' .
                        $index .
                        '][fee_head]'
                    ] =
                        'Fee Component was not found in this Structure.';

                    continue;
                }
            }

            $resolved[$index] = [
                'id' =>
                    $itemId,
                'old' =>
                    $oldItem,
                'data' =>
                    $item,
            ];
        }

        if ($errors !== []) {
            json_error(
                'Course Fee Structure validation failed.',
                422,
                $errors
            );
        }

        /*
         * Delete explicitly removed components.
         */
        $deletedItems = [];

        foreach (
            $removeIds
            as $removeId
        ) {
            $oldStmt =
                $pdo->prepare(
                    'SELECT *
                     FROM college_fee_structure_items
                     WHERE id=:id
                       AND fee_structure_id=:structure_id
                       AND branch_id=:branch_id
                     LIMIT 1'
                );

            $oldStmt->execute([
                ':id' =>
                    $removeId,
                ':structure_id' =>
                    $structureId,
                ':branch_id' =>
                    $branchId,
            ]);

            $oldItem =
                $oldStmt->fetch(PDO::FETCH_ASSOC);

            $deleteStmt =
                $pdo->prepare(
                    'DELETE FROM college_fee_structure_items
                     WHERE id=:id
                       AND fee_structure_id=:structure_id
                       AND branch_id=:branch_id'
                );

            $deleteStmt->execute([
                ':id' =>
                    $removeId,
                ':structure_id' =>
                    $structureId,
                ':branch_id' =>
                    $branchId,
            ]);

            if ($oldItem) {
                $deletedItems[] = [
                    'id' =>
                        $removeId,
                    'old' =>
                        $oldItem,
                ];
            }
        }

        $createdItems = [];
        $updatedItems = [];

        foreach (
            $resolved
            as $resolvedRow
        ) {
            $itemId =
                (int)$resolvedRow['id'];

            $item =
                $resolvedRow['data'];

            if ($itemId > 0) {
                if (in_array(
                    $itemId,
                    $removeIds,
                    true
                )) {
                    continue;
                }

                $update =
                    $pdo->prepare(
                        'UPDATE college_fee_structure_items
                         SET
                            fee_head=:fee_head,
                            amount=:amount,
                            mandatory=:mandatory,
                            sort_order=:sort_order,
                            status=:status,
                            updated_at=NOW()
                         WHERE id=:id
                           AND fee_structure_id=:structure_id
                           AND branch_id=:branch_id'
                    );

                $update->execute([
                    ':fee_head' =>
                        $item['fee_head'],
                    ':amount' =>
                        $item['amount'],
                    ':mandatory' =>
                        $item['mandatory'],
                    ':sort_order' =>
                        $item['sort_order'],
                    ':status' =>
                        $item['status'],
                    ':id' =>
                        $itemId,
                    ':structure_id' =>
                        $structureId,
                    ':branch_id' =>
                        $branchId,
                ]);

                $updatedItems[] = [
                    'id' =>
                        $itemId,
                    'old' =>
                        $resolvedRow['old'],
                    'new' =>
                        $item,
                ];
            } else {
                $insert =
                    $pdo->prepare(
                        'INSERT INTO college_fee_structure_items
                         (
                            fee_structure_id,
                            branch_id,
                            fee_head,
                            amount,
                            mandatory,
                            sort_order,
                            status,
                            created_by,
                            created_at,
                            updated_at
                         )
                         VALUES
                         (
                            :fee_structure_id,
                            :branch_id,
                            :fee_head,
                            :amount,
                            :mandatory,
                            :sort_order,
                            :status,
                            :created_by,
                            NOW(),
                            NOW()
                         )'
                    );

                $insert->execute([
                    ':fee_structure_id' =>
                        $structureId,
                    ':branch_id' =>
                        $branchId,
                    ':fee_head' =>
                        $item['fee_head'],
                    ':amount' =>
                        $item['amount'],
                    ':mandatory' =>
                        $item['mandatory'],
                    ':sort_order' =>
                        $item['sort_order'],
                    ':status' =>
                        $item['status'],
                    ':created_by' =>
                        $userId,
                ]);

                $createdItems[] = [
                    'id' =>
                        (int)$pdo->lastInsertId(),
                    'new' =>
                        $item,
                ];
            }
        }

        /*
         * Recalculate after all item changes using DB values.
         */
        $sumStmt =
            $pdo->prepare(
                'SELECT
                    COALESCE(
                        SUM(amount),
                        0
                    )
                 FROM college_fee_structure_items
                 WHERE fee_structure_id=:structure_id
                   AND branch_id=:branch_id
                   AND status=1'
            );

        $sumStmt->execute([
            ':structure_id' =>
                $structureId,
            ':branch_id' =>
                $branchId,
        ]);

        $databaseTotal =
            round(
                (float)$sumStmt->fetchColumn(),
                2
            );

        $totalUpdate =
            $pdo->prepare(
                'UPDATE college_fee_structures
                 SET total_amount=:total_amount,
                     updated_at=NOW()
                 WHERE id=:id
                   AND branch_id=:branch_id'
            );

        $totalUpdate->execute([
            ':total_amount' =>
                $databaseTotal,
            ':id' =>
                $structureId,
            ':branch_id' =>
                $branchId,
        ]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if (
            $e instanceof PDOException &&
            $e->getCode() === '23000'
        ) {
            json_error(
                'Course Fee Structure could not be saved because a duplicate or linked value already exists.',
                409
            );
        }

        throw $e;
    }

    $actionId =
        $isUpdate
            ? ACTION_UPDATE
            : ACTION_CREATE;

    audit_log(
        $userId,
        $actionId,
        [
            'company_id' =>
                (int)$ctx['company_id'],
            'branch_id' =>
                $branchId,
            'menu_id' =>
                (int)$access['menu']['id'],
            'record_id' =>
                $structureId,
            'old_data' =>
                $oldStructure,
            'new_data' =>
                $cleanStructure +
                [
                    'structure_code' =>
                        $structureCode,
                    'total_amount' =>
                        $databaseTotal,
                ],
        ]
    );

    foreach (
        $createdItems
        as $created
    ) {
        audit_log(
            $userId,
            ACTION_CREATE,
            [
                'company_id' =>
                    (int)$ctx['company_id'],
                'branch_id' =>
                    $branchId,
                'menu_id' =>
                    (int)$access['menu']['id'],
                'record_id' =>
                    (int)$created['id'],
                'new_data' =>
                    $created['new'] +
                    [
                        'fee_structure_id' =>
                            $structureId,
                    ],
            ]
        );
    }

    foreach (
        $updatedItems
        as $updated
    ) {
        audit_log(
            $userId,
            ACTION_UPDATE,
            [
                'company_id' =>
                    (int)$ctx['company_id'],
                'branch_id' =>
                    $branchId,
                'menu_id' =>
                    (int)$access['menu']['id'],
                'record_id' =>
                    (int)$updated['id'],
                'old_data' =>
                    $updated['old'],
                'new_data' =>
                    $updated['new'],
            ]
        );
    }

    foreach (
        $deletedItems
        as $deleted
    ) {
        audit_log(
            $userId,
            4,
            [
                'company_id' =>
                    (int)$ctx['company_id'],
                'branch_id' =>
                    $branchId,
                'menu_id' =>
                    (int)$access['menu']['id'],
                'record_id' =>
                    (int)$deleted['id'],
                'old_data' =>
                    $deleted['old'],
            ]
        );
    }

    json_success(
        $isUpdate
            ? 'Course Fee Structure updated successfully.'
            : 'Course Fee Structure created successfully.',
        [
            'structure' =>
                fee_structure_record(
                    $ctx,
                    $structureId
                ),
            'items' =>
                fee_item_rows(
                    $ctx,
                    $structureId
                ),
        ],
        $isUpdate
            ? 200
            : 201
    );
}

json_error(
    'Unsupported request.',
    404
);
