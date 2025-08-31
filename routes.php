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