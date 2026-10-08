<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A small, explicit router. Routes are matched in registration order; the
 * public catch-all is registered last.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, names:list<string>, handler:callable|array}> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void
    {
        $this->add(['GET', 'HEAD'], $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): void
    {
        $this->add(['POST'], $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): void
    {
        $this->add(['PUT', 'PATCH'], $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): void
    {
        $this->add(['DELETE'], $pattern, $handler);
    }

    /** @param list<string> $methods */
    public function add(array $methods, string $pattern, callable|array $handler): void
    {
        $names = [];
        $regex = preg_replace_callback('#\{(\w+)(\*)?\}#', static function (array $m) use (&$names): string {
            $names[] = $m[1];
            return isset($m[2]) && $m[2] === '*' ? '(.*)' : '([^/]+)';
        }, $pattern);
        foreach ($methods as $method) {
            $this->routes[] = [
                'method' => $method,
                'regex' => '#^' . $regex . '$#u',
                'names' => $names,
                'handler' => $handler,
            ];
        }
    }

    public function dispatch(Request $request): Response
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed = true;
                continue;
            }
            $params = [];
            foreach ($route['names'] as $i => $name) {
                $params[$name] = $m[$i + 1] ?? '';
            }
            $request->params = $params;
            return $this->call($route['handler'], $request);
        }
        throw $allowed ? new HttpException(405, 'Method Not Allowed') : HttpException::notFound();
    }

    private function call(callable|array $handler, Request $request): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $controller = new $handler[0]($request);
            $result = $controller->{$handler[1]}(...array_values($request->params));
        } else {
            $result = $handler($request, ...array_values($request->params));
        }
        if ($result instanceof Response) {
            return $result;
        }
        return Response::html((string) $result);
    }
}
