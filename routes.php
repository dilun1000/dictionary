<?php


class Router {
    private $routes = [];

    public function add(string $method, string $path, $handler) {
        $this->routes[] = compact('method', 'path', 'handler');
    }

    public function dispatch(string $method, string $uri) {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('#\{[\w]+\}#', '([^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // first match is full string, remove it

                $handler = $route['handler'];
                if (is_callable($handler)) {
                    return call_user_func_array($handler, $matches);
                }
                if (is_array($handler) && count($handler) === 2) {
                

                    [$class, $method] = $handler;
                   
                    if (class_exists($class)) {
                        $controller = new $class();
                        if (method_exists($controller, $method)) {
                            return call_user_func_array([$controller, $method], $matches);
                        }
                    }
                }
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }
}



$router = new Router();

//Как пример

$router->add('GET', '/', [App\Controllers\AppController::class, 'home']);
$router->add('PATCH', '/dictionary/{id}/learn', [App\Controllers\AppController::class, 'markAsLearnt']);
$router->add('GET','/dictionary/scroll', [App\Controllers\AppController::class, 'scroll']);
$router->add('GET','/dictionary/initial', [App\Controllers\AppController::class, 'initial']);


$router->add('GET','/dictionary/chunk', [App\Controllers\AppController::class, 'chunk']);


$router->add('GET','/dictionary/filter', [App\Controllers\AppController::class, 'filter']);


$router->add('DELETE','/dictionary/{id}', [App\Controllers\AppController::class, 'destroy']);


$router->add('POST','/dictionary', [App\Controllers\AppController::class, 'store']);
$router->add('PATCH','/dictionary/{id}', [App\Controllers\AppController::class, 'update']);


$router->add('GET','/dictionary/autocomplete', [App\Controllers\AppController::class, 'autocomplete']);

return $router;

// https://chatgpt.com/share/68a5b6f1-71f0-8010-848a-b923cb3b26ea
// https://chatgpt.com/c/68a7fbe0-1138-8324-a89e-65b7db365f31

/* 
new one
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
        // 🔹 Check if pair already exists
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM dictionary WHERE eng = :eng AND rus = :rus");
        $stmt->execute(['eng' => $eng, 'rus' => $rus]);

        if ($stmt->fetchColumn() > 0) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(409);
            echo json_encode(['error' => 'These words are already stored']);
            exit;
        }

        // 🔹 Insert new pair
        $stmt = $this->pdo->prepare("
            INSERT INTO dictionary (eng, rus, learnt, created_at, updated_at)
            VALUES (:eng, :rus, 0, NOW(), NOW())
        ");
        $stmt->execute(['eng' => $eng, 'rus' => $rus]);

        $id = $this->pdo->lastInsertId();

        // 🔹 Fetch the new row first, then the following 14 rows in dictionary order
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM dictionary
            WHERE eng > :eng
               OR (eng = :eng AND rus >= :rus)
            ORDER BY eng ASC, rus ASC
            LIMIT 15
        ");
        $stmt->execute(['eng' => $eng, 'rus' => $rus]);
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