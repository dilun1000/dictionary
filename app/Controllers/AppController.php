<?php

namespace App\Controllers;

use App\Core\Controller\Controller;
use App\Models\AppModel;
use PDO;
use PDOException;
use Exception;

class AppController extends Controller
{
    protected $pdo;
    /**
     * AppController constructor.
     * Initializes the database connection.
     */
    public function __construct()
    {
        $this->pdo = (new AppModel())->pdo;
    }
    public function search(string $lang, string $query)
    {
        header('Content-Type: application/json');
        // Validate the column name to avoid SQL injection
        $allowedLangs = ['eng', 'rus'];
        if (!in_array($lang, $allowedLangs)) {
            throw new \InvalidArgumentException("Invalid language column");
        }

        if (strlen($query) < 1) {
            return json_encode([]);
        }

        $stmt = $this->pdo->prepare("SELECT `$lang` FROM dictionary WHERE `$lang` LIKE :query LIMIT 5");
        $stmt->execute(['query' => $query . '%']);
        return json_encode($stmt->fetchAll());
    }
    public function home()
    {

        include __DIR__ . '/../Views/home.php'; // Load the homepage view reliably

    }

    public function initial(bool $learntOnly = false)
    {


        $learntOnly = ($_GET['learntOnly'] ?? '0') == '1';

        if ($learntOnly) {
            $stmt = $this->pdo->query("SELECT * FROM dictionary WHERE learnt = 1 ORDER BY eng ASC LIMIT 15");

            $stmt->execute();

        } else {
            $stmt = $this->pdo->query("SELECT * FROM dictionary ORDER BY eng ASC LIMIT 15");

            $stmt->execute();

        }
        //тут была ошибка


        header('Content-Type: application/json');

        echo json_encode(['data' => $stmt->fetchAll()]);
    }



    public function markAsLearnt(int $id)
    {
        // Parse PATCH input - assuming JSON body
        $data = json_decode(file_get_contents('php://input'), true);
        $learnt = $data['learnt'] ?? null;

        if (!is_int($learnt) && !ctype_digit($learnt)) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing or invalid learnt parameter']);
            return;
        }

        $stmt = $this->pdo->prepare("UPDATE dictionary SET learnt = :learnt WHERE id = :id");
        $stmt->execute(['learnt' => (int)$learnt, 'id' => $id]);

        echo json_encode(['status' => 'ok']);
    }


    public function destroy(int $id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM dictionary WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return json_encode(['status' => 'deleted']);
    }

    /* public function filter(?string $query = null, bool $learntOnly = false)
    {
        $sql = "SELECT * FROM dictionary WHERE 1";
        $params = [];

        if ($query) {
            $sql .= " AND (eng LIKE :q OR rus LIKE :q)";
            $params['q'] = "$query%";
        }

        if ($learntOnly) {
            $sql .= " AND learnt = 1";
        }

        $sql .= " ORDER BY eng ASC LIMIT 20";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['data' => $stmt->fetchAll()]);
    } */
public function filter()
{
    $query = $_GET['query'] ?? null;
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;

    $sql = "SELECT * FROM dictionary WHERE 1";
    $params = [];

    if ($query) {
        $sql .= " AND (eng LIKE :q OR rus LIKE :q)";
        $params['q'] = "$query%";
    }

    if ($learntOnly) {
        $sql .= " AND learnt = 1";
    }

    $sql .= " ORDER BY eng ASC LIMIT 20";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode(['data' => $stmt->fetchAll()]);
}


    public function update($id)
    {
        // Read raw JSON body
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data || !isset($data['eng'], $data['rus'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid input data']);
            return;
        }

        $eng = trim($data['eng']);
        $rus = trim($data['rus']);

        if ($eng === '' || $rus === '') {
            http_response_code(422);
            echo json_encode(['error' => 'Both fields are required']);
            return;
        }

        // Update query
        $stmt = $this->pdo->prepare(
            "UPDATE dictionary SET eng = :eng, rus = :rus, updated_at = NOW() WHERE id = :id"
        );

        if ($stmt->execute(['eng' => $eng, 'rus' => $rus, 'id' => $id])) {
            http_response_code(200);
            echo json_encode(['data' => ['id' => $id, 'eng' => $eng, 'rus' => $rus]]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update word']);
        }
    }

    public function store()
    {
        header('Content-Type: application/json; charset=utf-8');

        $input = json_decode(file_get_contents('php://input'), true);

        $eng = trim($input['eng'] ?? '');
        $rus = trim($input['rus'] ?? '');

        if (!$eng || !$rus) {
            http_response_code(400);
            echo json_encode(['error' => 'Both English and Russian words are required']);
            return;
        }

        try {
            // Вставляем новое слово (если есть уникальный индекс – оно само отловит дубликат)
            $stmt = $this->pdo->prepare("
            INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
            VALUES (:eng, :rus, 0, NOW(), NOW())
        ");
            $stmt->execute(['eng' => $eng, 'rus' => $rus]);

            $id = $this->pdo->lastInsertId();

            // Получаем новое слово первым + ещё 14 следующих по алфавиту
            $stmt = $this->pdo->prepare("
            SELECT *
            FROM dictionary
            WHERE eng >= (SELECT eng FROM dictionary WHERE id = :id)
            ORDER BY eng ASC
            LIMIT 15
        ");
            $stmt->execute(['id' => $id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            http_response_code(201);
            echo json_encode(['success' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            // Отдельная обработка ошибки дубликата
            if ($e->getCode() == 23000) {
                http_response_code(409);
                echo json_encode(['error' => 'These words are already stored']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Database error']);
            }
        }
    }

    public function autocomplete()
    {

        // Simulate query parameters (?query=...&lang=...)
        $query = isset($_GET['query']) ? $_GET['query'] : '';
        $lang  = isset($_GET['lang']) ? $_GET['lang'] : 'eng';

        // Validate $lang to prevent SQL injection
        if (!in_array($lang, ['eng', 'rus'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid language']);
            exit;
        }

        // Prepare SQL query
        $sql = "SELECT eng, rus FROM dictionary WHERE $lang LIKE :query";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['query' => $query . '%']);
        $results = $stmt->fetchAll(\PDO::FETCH_OBJ);

        // Group translations by word
        $grouped = [];
        foreach ($results as $word) {
            $key = $word->$lang;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'eng' => $word->eng,
                    'rus' => [],
                ];
            }
            $grouped[$key]['rus'][] = $word->rus;
        }

        // Send JSON response
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array_values($grouped), JSON_UNESCAPED_UNICODE);

    }

    

        public function chunk()
{
    $query = $_GET['query'] ?? '';
    $lang  = $_GET['lang'] ?? 'eng';
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;

    if (!in_array($lang, ['eng', 'rus'], true)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Invalid language parameter'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $perPage = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT * FROM dictionary WHERE 1";
    $params = [];

    if ($query !== '') {
        $sql .= " AND $lang LIKE :query";
        $params['query'] = $query . '%';
    }

    if ($learntOnly) {
        $sql .= " AND learnt = 1";
    }

    $sql .= " ORDER BY id LIMIT :limit OFFSET :offset";

    $stmt = $this->pdo->prepare($sql);

    foreach ($params as $key => $val) {
        $stmt->bindValue(':' . $key, $val, \PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

    $stmt->execute();
    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Count for pagination
    $countSql = "SELECT COUNT(*) FROM dictionary WHERE 1";
    if ($query !== '') {
        $countSql .= " AND $lang LIKE :query";
    }
    if ($learntOnly) {
        $countSql .= " AND learnt = 1";
    }
    $countStmt = $this->pdo->prepare($countSql);
    if ($query !== '') {
        $countStmt->bindValue(':query', $query . '%', \PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $response = [
        'data' => $rows,
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'last_page' => ceil($total / $perPage),
    ];

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}


    public function scroll()
{
    $pivot = $_GET['pivot'] ?? '';
    $direction = $_GET['direction'] ?? 'down';
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;
    $chunkSize = 15;

    if (!$pivot) {
        echo json_encode(['data' => []]);
        return;
    }

    if ($direction === 'down') {
        $sql = "
            SELECT id, eng, rus , learnt
            FROM dictionary
            WHERE eng > :pivot " . ($learntOnly ? "AND (learnt = 1)" : "") . "
            ORDER BY eng ASC
            LIMIT $chunkSize
        ";
    } else {
        $sql = "
            SELECT id, eng, rus , learnt
            FROM (
                SELECT id, eng, rus
                FROM dictionary
                WHERE eng < :pivot " . ($learntOnly ? "AND (learnt = 1)" : "") . "
                ORDER BY eng DESC
                LIMIT $chunkSize
            ) AS sub
            ORDER BY eng ASC
        ";
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['pivot' => $pivot]);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}


}


/*
store for output new pair
public function store()
{
    $input = json_decode(file_get_contents('php://input'), true);

    $eng = trim($input['eng'] ?? '');
    $rus = trim($input['rus'] ?? '');

    error_log("STORE called: eng='$eng', rus='$rus'");

    if (!$eng || !$rus) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['error' => 'Both English and Russian words are required']);
        exit;
    }

    try {
        // Check if the pair already exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dictionary WHERE eng = :eng AND rus = :rus");
        $stmt->execute(['eng' => $eng, 'rus' => $rus]);

        if ($stmt->fetchColumn() > 0) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(409);
            echo json_encode(['error' => 'These words are already stored']);
            exit;
        }

        // Insert new word
        $stmt = $this->pdo->prepare("
            INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
            VALUES (:eng, :rus, 0, NOW(), NOW())
        ");
        $stmt->execute(['eng' => $eng, 'rus' => $rus]);

        $id = $this->pdo->lastInsertId();

        // Fetch the new word first, then 14 other rows
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM dictionary
            ORDER BY (id = :id) DESC, created_at ASC
            LIMIT 15
        ");
        $stmt->execute(['id' => $id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode(['data' => $rows]);
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        error_log("Database error: " . $e->getMessage());
        exit;
    }
} */

    
/* public function scroll()
    {
        $pivotId = $_GET['pivot'] ?? 0;
        $direction = $_GET['direction'] ?? 'down';
        $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;
        $chunkSize = 20;

        try {
            // 1️⃣ Build full list of IDs in alphabetical order
            $sqlIds = "SELECT id FROM dictionary";
            if ($learntOnly) {
                $sqlIds .= " WHERE learnt = 1";
            }
            $sqlIds .= " ORDER BY eng ASC, id ASC";

            $stmt = $this->pdo->query($sqlIds);
            $allIds = $stmt->fetchAll(PDO::FETCH_COLUMN); // temporary array of all IDs in ABC order

            // 2️⃣ Find starting index depending on scroll direction
            $startIndex = 0;
            if ($pivotId) {
                $index = array_search($pivotId, $allIds);
                if ($index !== false) {
                    if ($direction === 'down') {
                        $startIndex = $index + 1;
                    } else { // scrolling up
                        $startIndex = max($index - $chunkSize, 0);
                    }
                }
            }

            // 3️⃣ Slice the chunk of IDs for this request
            $chunkIds = array_slice($allIds, $startIndex, $chunkSize);

            // 4️⃣ Retrieve words for these IDs
            $words = [];
            if (count($chunkIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
                $stmt = $this->pdo->prepare(
                    "SELECT * FROM dictionary WHERE id IN ($placeholders) ORDER BY FIELD(id," . implode(',', $chunkIds) . ")"
                );
                $stmt->execute($chunkIds);
                $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // 5️⃣ For scrolling up, reverse so frontend always receives ascending order
            if ($direction === 'up') {
                $words = array_reverse($words);
            }

            // 6️⃣ Return JSON
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['data' => $words]);
            exit;

        } catch (PDOException $e) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            exit;
        }
    } */


/* public function scroll()
{
    $pivotId = $_GET['pivot'] ?? 0;
    $direction = $_GET['direction'] ?? 'down';
    $idsParam = $_GET['ids'] ?? null;
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;
    $chunkSize = 20;

    try {
        if ($direction === 'up' && $idsParam) {
            // ✅ Scroll up: fetch words by IDs in the order provided
            $ids = explode(',', $idsParam);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));

            $stmt = $this->pdo->prepare(
                "SELECT * FROM dictionary WHERE id IN ($placeholders) ORDER BY FIELD(id," . implode(',', $ids) . ")"
            );
            $stmt->execute($ids);
            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['data' => $words, 'ids' => $ids]);
            exit;
        }

        // ✅ Scroll down: generate next chunk in alphabetical order
        $sqlIds = "SELECT id FROM dictionary";
        if ($learntOnly) {
            $sqlIds .= " WHERE learnt = 1";
        }
        $sqlIds .= " ORDER BY eng ASC, id ASC";

        $stmt = $this->pdo->query($sqlIds);
        $allIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $startIndex = 0;
        if ($pivotId) {
            $index = array_search($pivotId, $allIds);
            if ($index !== false) {
                $startIndex = $index + 1;
            }
        }

        $chunkIds = array_slice($allIds, $startIndex, $chunkSize);

        $words = [];
        if (count($chunkIds) > 0) {
            $placeholders = implode(',', array_fill(0, count($chunkIds), '?'));
            $stmt = $this->pdo->prepare(
                "SELECT * FROM dictionary WHERE id IN ($placeholders) ORDER BY FIELD(id," . implode(',', $chunkIds) . ")"
            );
            $stmt->execute($chunkIds);
            $words = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $words, 'ids' => $chunkIds]);
        exit;

    } catch (PDOException $e) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
} */

    
// public function store()
// {
//     $input = json_decode(file_get_contents('php://input'), true);

//     $eng = trim($input['eng'] ?? '');
//     $rus = trim($input['rus'] ?? '');

//     error_log("STORE called: eng='$eng', rus='$rus'");

//     if (!$eng || !$rus) {
//         header('Content-Type: application/json; charset=utf-8');   // 👈 add here
//         http_response_code(400);
//         echo json_encode(['error' => 'Both English and Russian words are required']);
//         exit;
//     }

//     try {
//         $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dictionary WHERE eng = :eng AND rus = :rus");
//         $stmt->execute(['eng' => $eng, 'rus' => $rus]);

//         if ($stmt->fetchColumn() > 0) {
//             header('Content-Type: application/json; charset=utf-8');   // 👈 add here
//             http_response_code(409);
//             echo json_encode(['error' => 'These words are already stored']);
//             exit;
//         }

//         $stmt = $this->pdo->prepare("
//         INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
//         VALUES (:eng, :rus, 0, NOW(), NOW())
//     ");
//         $stmt->execute(['eng' => $eng, 'rus' => $rus]);

//         $id = $this->pdo->lastInsertId();

//         header('Content-Type: application/json; charset=utf-8');   // 👈 add here
//         http_response_code(200);
//         echo json_encode(['data' => ['id' => $id, 'eng' => $eng, 'rus' => $rus]]);
//         exit;

/* $stmt = $this->pdo->prepare("
INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
VALUES (:eng, :rus, 0, NOW(), NOW())
");
$stmt->execute(['eng' => $eng, 'rus' => $rus]);

$id = $this->pdo->lastInsertId();

// 🔹 Fetch the new word first, then 14 other rows
$stmt = $this->pdo->prepare("
SELECT *
FROM dictionary
ORDER BY (id = :id) DESC, created_at ASC
LIMIT 15
");
$stmt->execute(['id' => $id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['data' => $rows]);
exit; */

//     } catch (PDOException $e) {
//         header('Content-Type: application/json; charset=utf-8');   // 👈 add here
//         http_response_code(500);
//         echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
//         error_log("Database error: " . $e->getMessage());
//         exit;
//     }
// }


/* public function scroll()
{
    $pivot = $_GET['pivot'] ?? 0;
    $direction = $_GET['direction'] ?? 'down';
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;

    $sql = "SELECT * FROM dictionary WHERE 1=1";

    // Pivot filter
    if ($direction === 'down') {
        $sql .= " AND eng > :pivot";
        $order = "ASC";
    } else {
        $sql .= " AND eng < :pivot";
        $order = "DESC";
    }

    // Learnt filter
    if ($learntOnly) {
        $sql .= " AND learnt = 1";
    }

    $sql .= " ORDER BY eng $order LIMIT 20";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['pivot' => $pivot]);

    $words = $stmt->fetchAll();

    // Reverse if scrolling up so JS can prepend
    if ($direction === 'up') {
        $words = array_reverse($words);
    }

    echo json_encode(['data' => $words]);
} */
/* public function scroll()
{
    $pivotId = $_GET['pivot'] ?? 0;
    $direction = $_GET['direction'] ?? 'down';
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;

    // Fetch pivot word's 'eng' value if pivotId is set
    $pivotEng = '';
    if ($pivotId) {
        $stmt = $this->pdo->prepare("SELECT eng FROM dictionary WHERE id = :id");
        $stmt->execute(['id' => $pivotId]);
        $pivotEng = $stmt->fetchColumn() ?: '';
    }

    $sql = "SELECT * FROM dictionary WHERE 1=1";

    // Pivot filter based on alphabetical order
    if ($pivotEng) {
        if ($direction === 'down') {
            $sql .= " AND (eng > :pivotEng OR (eng = :pivotEng AND id > :pivotId))";
        } else {
            $sql .= " AND (eng < :pivotEng OR (eng = :pivotEng AND id < :pivotId))";
        }
    }

    // Learnt filter
    if ($learntOnly) {
        $sql .= " AND learnt = 1";
    }

    // Always order alphabetically, id as tiebreaker
    $order = ($direction === 'down') ? 'ASC' : 'DESC';
    $sql .= " ORDER BY eng $order, id $order LIMIT 20";

    $stmt = $this->pdo->prepare($sql);
    $params = [];
    if ($pivotEng) {
        $params = ['pivotEng' => $pivotEng, 'pivotId' => $pivotId];
    }
    $stmt->execute($params);

    $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Reverse for scrolling up so JS can prepend
    if ($direction === 'up') {
        $words = array_reverse($words);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => $words]);
} */

    
//last working option
/* public function scroll()
{
    $pivotEng = $_GET['pivotEng'] ?? null;
    $direction = $_GET['direction'] ?? 'down';
    $is_learnt = $_GET['learntOnly'] ?? false;
    $chunkSize = 15; // or 20

    try {
        if (!$pivotEng) {
            throw new Exception("Missing pivotEng");
        }

        if ($direction === 'up') {
            // Take words before pivot (topmost word)

            $stmt = $this->pdo->prepare("
SELECT id, eng, rus FROM (
    SELECT id, eng, rus
    FROM dictionary
    WHERE eng < :pivot "
                    . ($is_learnt ? "AND learnt = 1 " : "") . "
    ORDER BY eng DESC
    LIMIT $chunkSize
) AS sub
ORDER BY eng ASC
");

            $stmt->execute([':pivot' => $pivotEng]);

        } else { // down
            // Take words after pivot (bottommost word)

$stmt = $this->pdo->prepare("
SELECT id, eng, rus
FROM dictionary
WHERE eng > :pivot " . ($is_learnt ? "AND learnt = 1 " : "") . "
ORDER BY eng ASC
LIMIT $chunkSize
");

            $stmt->execute([':pivot' => $pivotEng]);
        }

        $words = $stmt->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['data' => $words]);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} */

/* public function scroll()
{
    $pivot = $_GET['pivot'] ?? '';
    $direction = $_GET['direction'] ?? 'down';
    $learntOnly = isset($_GET['learntOnly']) && $_GET['learntOnly'] == 1;

    $chunkSize = 15;

    if ($direction === 'down') {
        $sql = "
            SELECT id, eng, rus
            FROM dictionary
            WHERE eng > :pivot " . ($learntOnly ? "AND learnt = 1 " : "") . "
            ORDER BY eng ASC
            LIMIT $chunkSize
        ";
    } else {
        $sql = "
            SELECT id, eng, rus
            FROM (
                SELECT id, eng, rus
                FROM dictionary
                WHERE eng < :pivot " . ($learntOnly ? "AND learnt = 1 " : "") . "
                ORDER BY eng DESC
                LIMIT $chunkSize
            ) AS sub
            ORDER BY eng ASC
        ";
    }

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute(['pivot' => $pivot]);

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} */




/* public function scroller()
{
    $pivot = $_GET['pivot'] ?? '';
    $direction = $_GET['direction'] ?? 'down';

    if ($direction === 'down') {
        $stmt = $this->pdo->prepare("SELECT * FROM dictionary WHERE eng < :pivot ORDER BY eng DESC LIMIT 5");
    } else {
        $stmt = $this->pdo->prepare("SELECT * FROM dictionary WHERE eng > :pivot ORDER BY eng ASC LIMIT 5");
    }

    $stmt->execute(['pivot' => $pivot]);

    header('Content-Type: application/json');
    echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} */

    
/* public function chunk()
    {
        $query = $_GET['query'] ?? '';
        $lang  = $_GET['lang'] ?? 'eng';

        if (!in_array($lang, ['eng', 'rus'], true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['error' => 'Invalid language parameter'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $perPage = 10;
        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
        if ($page < 1) {
            $page = 1;
        }
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM dictionary";
        $params = [];

        if ($query !== '') {
            $sql .= " WHERE $lang LIKE :query";
            $params['query'] = $query . '%';
        }

        $sql .= " ORDER BY id LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $val) {
            $stmt->bindValue(':' . $key, $val, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $countSql = "SELECT COUNT(*) FROM dictionary";
        if ($query !== '') {
            $countSql .= " WHERE $lang LIKE :query";
        }
        $countStmt = $this->pdo->prepare($countSql);
        if ($query !== '') {
            $countStmt->bindValue(':query', $query . '%', \PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) $countStmt->fetchColumn();

        $response = [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage),
        ];

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } */
