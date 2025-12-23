<?php

namespace App\Http\Routing;

use Illuminate\Routing\Router;
use Illuminate\Routing\RouteCollection;
use Closure;

/**
 * Registrar de rotas para módulos
 * Cria rotas CRUD padronizadas automaticamente
 */
class ModuleRegistrar
{
    protected Router $router;
    protected string $prefix;
    protected string $controller;
    protected string $parameter;
    protected ?string $name = null;
    protected array $methods = [];
    protected array $attributes = [];
    protected bool $registered = false;

    protected array $availableMethods = ['get', 'list', 'store', 'update', 'destroy', 'destroyMany'];

    public function __construct(Router $router, string $prefix, string $controller, string $parameter)
    {
        $this->router = $router;
        $this->name = $this->extractName($prefix);
        $this->prefix = $prefix;
        $this->controller = $controller;
        $this->parameter = $parameter;
        
        // Remove métodos update e destroy por padrão (usamos os outros mais frequentemente)
        $methods = array_slice($this->availableMethods, 0, -2);
        $this->methods = array_combine($methods, $methods);
        
        $this->whereNumber($parameter);
    }

    /**
     * Extrair nome base do prefixo
     */
    protected function extractName(string $prefix): string
    {
        $parts = explode('/', trim($prefix, '/'));
        return $parts[0] ?? $prefix;
    }

    /**
     * Definir métodos do controller
     */
    public function methods(array $methods): self
    {
        $this->methods = $methods;
        return $this;
    }

    /**
     * Apenas métodos específicos
     */
    public function only(array $methods): self
    {
        $this->methods = array_intersect_key(
            array_combine($this->availableMethods, $this->availableMethods),
            array_flip($methods)
        );
        return $this;
    }

    /**
     * Excluir métodos
     */
    public function except(array $methods): self
    {
        $this->methods = array_diff_key(
            array_combine($this->availableMethods, $this->availableMethods),
            array_flip($methods)
        );
        return $this;
    }

    /**
     * Rotas aninhadas (filhas)
     */
    public function children(array|string|Closure $callback): self
    {
        $this->ensureRegistered();
        $this->router->group(
            $this->getCompiledAttributes(['prefix' => $this->prefix . "/{" . $this->parameter . "}"]),
            $callback
        );
        return $this;
    }

    /**
     * Agrupar rotas com mesmo prefixo
     */
    public function group(array|string|Closure $callback): self
    {
        $this->ensureRegistered();
        $attributes = $this->getCompiledAttributes(['prefix' => $this->prefix ?: null]);
        unset($attributes['where'][$this->parameter]);
        $this->router->group($attributes, $callback);
        return $this;
    }

    /**
     * Adicionar middleware
     */
    public function middleware(array|string $middleware): self
    {
        if (!isset($this->attributes['middleware'])) {
            $this->attributes['middleware'] = [];
        }
        
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->attributes['middleware'] = array_merge($this->attributes['middleware'], $middleware);
        
        return $this;
    }

    /**
     * Remover middleware
     */
    public function withoutMiddleware(array|string $middleware): self
    {
        if (!isset($this->attributes['withoutMiddleware'])) {
            $this->attributes['withoutMiddleware'] = [];
        }
        
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->attributes['withoutMiddleware'] = array_merge($this->attributes['withoutMiddleware'], $middleware);
        
        return $this;
    }

    /**
     * Definir nome customizado
     */
    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Constraints de rota
     */
    public function where(string $parameter, string $constraint): self
    {
        if (!isset($this->attributes['where'])) {
            $this->attributes['where'] = [];
        }
        $this->attributes['where'][$parameter] = $constraint;
        return $this;
    }

    /**
     * Constraint para número
     */
    protected function whereNumber(string $parameter): self
    {
        return $this->where($parameter, '[0-9]+');
    }

    /**
     * Obter atributos compilados
     */
    protected function getCompiledAttributes(array $merge = []): array
    {
        $attributes = array_merge($this->attributes, $merge);
        
        if (isset($attributes['where'])) {
            $attributes['where'] = array_merge(
                $attributes['where'] ?? [],
                $merge['where'] ?? []
            );
        }
        
        return $attributes;
    }

    /**
     * Registrar rota
     */
    protected function registerRoute(string $method, string $uri, array|string $action): \Illuminate\Routing\Route
    {
        $route = $this->router->addRoute(
            strtoupper($method),
            $uri,
            $action
        );
        
        // Aplicar atributos
        $attributes = $this->getCompiledAttributes();
        
        if (isset($attributes['middleware'])) {
            $route->middleware($attributes['middleware']);
        }
        
        if (isset($attributes['withoutMiddleware'])) {
            $route->withoutMiddleware($attributes['withoutMiddleware']);
        }
        
        if (isset($attributes['where'])) {
            foreach ($attributes['where'] as $param => $constraint) {
                $route->where($param, $constraint);
            }
        }
        
        return $route;
    }

    /**
     * Garantir que as rotas estão registradas
     */
    protected function ensureRegistered(): void
    {
        if (!$this->registered) {
            $this->register();
        }
    }

    /**
     * Registrar todas as rotas
     */
    protected function register(): void
    {
        if ($this->registered) {
            return;
        }
        
        $this->registered = true;
        $collection = new RouteCollection();
        
        if ($method = $this->methods['get'] ?? false) {
            $route = $this->registerRoute('get', $this->prefix . "/{" . $this->parameter . "}", [$this->controller, $method]);
            if ($this->name) $route->name("$this->name.get");
            $collection->add($route);
        }
        
        if ($method = $this->methods['list'] ?? false) {
            $route = $this->registerRoute('get', $this->prefix, [$this->controller, $method]);
            if ($this->name) $route->name("$this->name.list");
            $collection->add($route);
        }
        
        if ($method = $this->methods['store'] ?? false) {
            $routeName = $this->name ? "{$this->name}.store" : null;
            // Verificar se a rota já existe
            if (!$routeName || !$this->router->getRoutes()->getByName($routeName)) {
                $route = $this->registerRoute('post', $this->prefix . "/{" . $this->parameter . "?}", [$this->controller, $method]);
                if ($this->name) $route->name($routeName);
                $collection->add($route);
            }
        }
        
        if ($method = $this->methods['update'] ?? false) {
            $route = $this->registerRoute('put', $this->prefix . "/{" . $this->parameter . "}", [$this->controller, $method]);
            if ($this->name) $route->name("$this->name.update");
            $collection->add($route);
        }
        
        if ($method = $this->methods['destroy'] ?? false) {
            $route = $this->registerRoute('delete', $this->prefix . "/{" . $this->parameter . "}", [$this->controller, $method]);
            if ($this->name) $route->name("$this->name.destroy");
            $collection->add($route);
        }
        
        if ($method = $this->methods['destroyMany'] ?? false) {
            $route = $this->registerRoute('delete', $this->prefix, [$this->controller, $method]);
            if ($this->name) $route->name("$this->name.destroyMany");
            $collection->add($route);
        }
        
        // Adicionar todas as rotas ao router
        foreach ($collection->getRoutes() as $route) {
            $routeName = $route->getName();
            // Verificar se a rota já existe antes de adicionar
            if ($routeName && $this->router->getRoutes()->getByName($routeName)) {
                // Rota já existe, pular
                continue;
            }
            $this->router->getRoutes()->add($route);
        }
    }

    /**
     * Registrar automaticamente quando o objeto é destruído
     * Mas apenas se ainda não foi registrado e não há métodos encadeados pendentes
     */
    public function __destruct()
    {
        // Não registrar no destruct se já foi registrado ou se há métodos encadeados
        // que indicam que o registro deve acontecer explicitamente
        if ($this->registered) return;
        
        // Verificar se há rotas com o mesmo nome já registradas
        // Se sim, não registrar para evitar duplicação
        $routes = $this->router->getRoutes();
        $testName = $this->name ? "{$this->name}.store" : null;
        if ($testName && $routes->getByName($testName)) {
            // Rota já existe, marcar como registrado para evitar tentativas futuras
            $this->registered = true;
            return;
        }
        
        $this->register();
    }
}

