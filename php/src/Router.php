<?php
/**
 * Router - Roteador de API simples baseado em regex.
 * Converte padrões como /api/orders/:id/status para regex.
 */
class Router
{
    /** @var array<array{pattern:string, regex:string, handler:class-string}> */
    private array $routes = [];

    public function add(string $pattern, string $handlerClass): void
    {
        // Converte :param para grupos nomeados (?P<param>[^/]+)
        $regex = preg_replace('#:([a-zA-Z_]+)#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '(?:\?.*)?$#';
        $this->routes[] = [
            'pattern' => $pattern,
            'regex'   => $regex,
            'handler' => $handlerClass,
        ];
    }

    /**
     * Resolve a rota e executa o handler correspondente.
     * Encerra com 404 se nenhuma rota for encontrada.
     *
     * @param string $path    URI sem query string (ex: /api/orders/42/status)
     * @param array  $context Contexto compartilhado (headers, body, user, etc.)
     */
    public function dispatch(string $path, array $context): void
    {
        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                // Extrai parâmetros nomeados
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $context['params'] = $params;

                $handlerClass = $route['handler'];
                if (!class_exists($handlerClass)) {
                    Response::serverError("Handler {$handlerClass} não encontrado");
                }

                (new $handlerClass())->handle($context);
                return;
            }
        }

        Response::error('NOT_FOUND', 'Rota não encontrada', 404);
    }
}
