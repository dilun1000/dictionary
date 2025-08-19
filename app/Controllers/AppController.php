<?php

namespace App\Controllers;

use App\Core\Controller\Controller;
use App\Models\AppModel;
use PDO;
use PDOException;

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

    

    public function scroll()
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
    }


    public function scroller()
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

    public function filter(?string $query = null, bool $learntOnly = false)
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
        $input = json_decode(file_get_contents('php://input'), true);

        $eng = trim($input['eng'] ?? '');
        $rus = trim($input['rus'] ?? '');

        error_log("STORE called: eng='$eng', rus='$rus'");

        if (!$eng || !$rus) {
            header('Content-Type: application/json; charset=utf-8');   // 👈 add here
            http_response_code(400);
            echo json_encode(['error' => 'Both English and Russian words are required']);
            exit;
        }

        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dictionary WHERE eng = :eng AND rus = :rus");
            $stmt->execute(['eng' => $eng, 'rus' => $rus]);

            if ($stmt->fetchColumn() > 0) {
                header('Content-Type: application/json; charset=utf-8');   // 👈 add here
                http_response_code(409);
                echo json_encode(['error' => 'These words are already stored']);
                exit;
            }

            $stmt = $this->pdo->prepare("
            INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
            VALUES (:eng, :rus, 0, NOW(), NOW())
        ");
            $stmt->execute(['eng' => $eng, 'rus' => $rus]);

            $id = $this->pdo->lastInsertId();

            header('Content-Type: application/json; charset=utf-8');   // 👈 add here
            http_response_code(200);
            echo json_encode(['data' => ['id' => $id, 'eng' => $eng, 'rus' => $rus]]);
            exit;
        } catch (PDOException $e) {
            header('Content-Type: application/json; charset=utf-8');   // 👈 add here
            http_response_code(500);
            echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
            error_log("Database error: " . $e->getMessage());
            exit;
        }
    }


    /* public function autocomplete()
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

    } */

    public function chunk()
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
    }

}
