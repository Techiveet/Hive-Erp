<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ReflectionMethod;

class ApiDocumentationController extends \App\Http\Controllers\Controller
{
    public function index(Request $request): View
    {
        return view('api-docs', [
            'appName' => config('app.name', 'HIVE.OS'),
            'specUrl' => route('api-docs.api-spec'),
            'apiRoot' => $this->baseOrigin($request).'/api',
            'frontendDocsUrl' => $this->frontendDocsUrl($request),
        ]);
    }

    public function spec(Request $request): JsonResponse
    {
        return response()->json(
            $this->buildOpenApiSpec($request),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }

    protected function buildOpenApiSpec(Request $request): array
    {
        $paths = [];
        $seen = [];

        foreach (Route::getRoutes() as $route) {
            $path = $this->normalizeApiPath($route);

            if ($path === null) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $method = strtolower($method);
                $fingerprint = "{$method}:{$path}";

                if (isset($seen[$fingerprint])) {
                    continue;
                }

                $seen[$fingerprint] = true;
                $paths[$path][$method] = $this->buildOperation($route, $method, $path);
            }
        }

        ksort($paths);

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => config('app.name', 'HIVE.OS').' API',
                'version' => '1.2.0',
                'description' => 'Interactive API reference generated from live Laravel routes. Request forms are inferred from controller validation rules where available, including login, 2FA, uploads, tenant headers, and CRUD payloads.',
            ],
            'servers' => [
                [
                    'url' => $this->baseOrigin($request).'/api',
                    'description' => 'Shared API root for central and tenant traffic',
                ],
            ],
            'tags' => $this->buildTagIndex($paths),
            'paths' => $paths,
            'components' => [
                'securitySchemes' => [
                    'sanctumBearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'Token',
                        'description' => 'Paste the Sanctum bearer token issued after login. The docs UI can inject it automatically into every request.',
                    ],
                ],
                'parameters' => [
                    'TenantHeader' => [
                        'name' => 'X-Tenant',
                        'in' => 'header',
                        'required' => false,
                        'description' => 'Tenant identifier for shared-host tenant requests. Leave empty for central calls or when using a tenant hostname.',
                        'schema' => [
                            'type' => 'string',
                            'example' => 'tenantapple',
                        ],
                    ],
                ],
                'schemas' => [
                    'UnauthorizedError' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'example' => 'Unauthenticated.'],
                        ],
                    ],
                    'ValidationError' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'example' => 'The given data was invalid.'],
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'LoginSuccess' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'example' => 'Authentication successful.'],
                            'data' => [
                                'type' => 'object',
                                'properties' => [
                                    'token' => ['type' => 'string', 'example' => '1|sanctum-example-token'],
                                    'context' => ['type' => 'string', 'example' => 'central'],
                                    'user' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'id' => ['type' => 'integer', 'example' => 1],
                                            'name' => ['type' => 'string', 'example' => 'System Admin'],
                                            'email' => ['type' => 'string', 'format' => 'email', 'example' => 'admin@hive-os.com'],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'TwoFactorChallenge' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string', 'example' => 'Verification required.'],
                            'requires_2fa' => ['type' => 'boolean', 'example' => true],
                            'two_factor_token' => ['type' => 'string', 'example' => 'temporary-2fa-token'],
                            'global_2fa_enforced' => ['type' => 'boolean', 'example' => true],
                            'requires_2fa_setup' => ['type' => 'boolean', 'example' => true],
                            'qr_code_url' => ['type' => 'string', 'example' => 'otpauth://totp/HIVE.OS:admin@hive-os.com?...'],
                            'secret' => ['type' => 'string', 'example' => 'ABC123SECRET'],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function normalizeApiPath(LaravelRoute $route): ?string
    {
        $uri = ltrim($route->uri(), '/');

        if (! Str::startsWith($uri, 'api/')) {
            return null;
        }

        if (Str::startsWith($uri, 'api/docs') || Str::startsWith($uri, 'api/api-docs')) {
            return null;
        }

        return '/'.Str::after($uri, 'api/');
    }

    protected function buildOperation(LaravelRoute $route, string $method, string $path): array
    {
        $middleware = $route->gatherMiddleware();
        $controller = $route->getControllerClass();
        $actionMethod = $route->getActionMethod();
        $requiresAuth = collect($middleware)->contains(fn (string $item) => Str::startsWith($item, 'auth:'));
        $parameters = $this->extractPathParameters($path);

        if ($this->supportsTenantHeader($path, $route)) {
            $parameters[] = ['$ref' => '#/components/parameters/TenantHeader'];
        }

        $operation = [
            'tags' => [$this->inferTag($path, $controller)],
            'summary' => $this->makeSummary($method, $path, $controller, $actionMethod),
            'operationId' => $this->makeOperationId($method, $path),
            'parameters' => $parameters,
            'responses' => $this->buildResponses($requiresAuth, $path, $actionMethod),
        ];

        if ($description = $this->makeDescription($route, $controller, $actionMethod, $middleware, $path)) {
            $operation['description'] = $description;
        }

        if ($requiresAuth) {
            $operation['security'] = [
                ['sanctumBearerAuth' => []],
            ];
        }

        if ($requestBody = $this->buildRequestBody($route, $method, $path, $controller, $actionMethod)) {
            $operation['requestBody'] = $requestBody;
        }

        return $operation;
    }

    protected function buildRequestBody(LaravelRoute $route, string $method, string $path, ?string $controller, string $actionMethod): ?array
    {
        if (in_array($method, ['get', 'delete'], true)) {
            return null;
        }

        $inferred = $this->inferValidationRequestSchema($controller, $actionMethod);

        if ($inferred !== null) {
            return $inferred;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'additionalProperties' => true,
                    ],
                ],
            ],
        ];
    }

    protected function inferValidationRequestSchema(?string $controller, string $actionMethod): ?array
    {
        if (! $controller || ! class_exists($controller) || ! method_exists($controller, $actionMethod)) {
            return null;
        }

        try {
            $reflection = new ReflectionMethod($controller, $actionMethod);
            $file = $reflection->getFileName();
            if (! $file || ! is_file($file)) {
                return null;
            }

            $lines = file($file);
            if ($lines === false) {
                return null;
            }

            $source = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
            $validationArray = $this->extractValidationArray($source);
            if ($validationArray === null) {
                return null;
            }

            $parsed = $this->parseValidationRules($validationArray);
            if ($parsed['properties'] === []) {
                return null;
            }

            $contentType = $parsed['hasBinary'] ? 'multipart/form-data' : 'application/json';

            return [
                'required' => $parsed['required'] !== [],
                'content' => [
                    $contentType => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => $parsed['properties'],
                            'required' => array_values(array_unique($parsed['required'])),
                        ],
                    ],
                ],
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    protected function extractValidationArray(string $source): ?string
    {
        if (! preg_match('/(?:\$request\s*->\s*validate|validate)\s*\(\s*\[/s', $source, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]) - 1;
        $depth = 0;
        $length = strlen($source);

        for ($i = $start; $i < $length; $i++) {
            $char = $source[$i];

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    protected function parseValidationRules(string $rulesSource): array
    {
        $entries = $this->splitTopLevel($rulesSource);
        $properties = [];
        $required = [];
        $hasBinary = false;

        foreach ($entries as $entry) {
            if (! preg_match('/^[\s\'\"]*([^\'\"\s]+)[\'\"]?\s*=>\s*(.+)$/s', trim($entry), $matches)) {
                continue;
            }

            $field = trim($matches[1]);
            if (Str::contains($field, '.')) {
                continue;
            }

            $ruleExpression = trim(rtrim($matches[2], ','));
            $ruleParts = $this->normalizeRuleParts($ruleExpression);
            $schema = $this->rulesToSchema($field, $ruleParts);

            $properties[$field] = $schema['schema'];
            $required = array_merge($required, $schema['required']);
            $hasBinary = $hasBinary || $schema['binary'];

            if (in_array('confirmed', $ruleParts, true) && ! isset($properties[$field.'_confirmation'])) {
                $properties[$field.'_confirmation'] = [
                    'type' => 'string',
                    'example' => $field === 'password' ? 'Secret123!' : 'confirmation-value',
                ];
                if (in_array($field, $schema['required'], true)) {
                    $required[] = $field.'_confirmation';
                }
            }
        }

        return [
            'properties' => $properties,
            'required' => array_values(array_unique($required)),
            'hasBinary' => $hasBinary,
        ];
    }

    protected function splitTopLevel(string $source): array
    {
        $parts = [];
        $buffer = '';
        $square = 0;
        $paren = 0;
        $inSingle = false;
        $inDouble = false;
        $escape = false;

        foreach (str_split($source) as $char) {
            if ($escape) {
                $buffer .= $char;
                $escape = false;
                continue;
            }

            if ($char === '\\') {
                $buffer .= $char;
                $escape = true;
                continue;
            }

            if ($char === "'" && ! $inDouble) {
                $inSingle = ! $inSingle;
                $buffer .= $char;
                continue;
            }

            if ($char === '"' && ! $inSingle) {
                $inDouble = ! $inDouble;
                $buffer .= $char;
                continue;
            }

            if (! $inSingle && ! $inDouble) {
                if ($char === '[') {
                    $square++;
                } elseif ($char === ']') {
                    $square--;
                } elseif ($char === '(') {
                    $paren++;
                } elseif ($char === ')') {
                    $paren--;
                } elseif ($char === ',' && $square === 0 && $paren === 0) {
                    if (trim($buffer) !== '') {
                        $parts[] = trim($buffer);
                    }
                    $buffer = '';
                    continue;
                }
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $parts[] = trim($buffer);
        }

        return $parts;
    }

    protected function normalizeRuleParts(string $expression): array
    {
        $expression = trim($expression);

        if (Str::startsWith($expression, '[') && Str::endsWith($expression, ']')) {
            $inner = substr($expression, 1, -1);
            $parts = $this->splitTopLevel($inner);

            return collect($parts)
                ->map(fn (string $part) => trim($part, " \t\n\r\0\x0B'\""))
                ->filter()
                ->values()
                ->all();
        }

        return collect(explode('|', trim($expression, "'\"")))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->values()
            ->all();
    }

    protected function rulesToSchema(string $field, array $rules): array
    {
        $schema = [
            'type' => 'string',
            'example' => $this->defaultExample($field),
        ];
        $required = [];
        $binary = false;

        foreach ($rules as $rule) {
            if ($rule === 'required') {
                $required[] = $field;
                continue;
            }

            if ($rule === 'email') {
                $schema['format'] = 'email';
                $schema['example'] = 'admin@hive-os.com';
                continue;
            }

            if ($rule === 'integer') {
                $schema['type'] = 'integer';
                $schema['example'] = 1;
                continue;
            }

            if (in_array($rule, ['numeric', 'decimal'], true)) {
                $schema['type'] = 'number';
                $schema['example'] = 1.5;
                continue;
            }

            if ($rule === 'boolean') {
                $schema['type'] = 'boolean';
                $schema['example'] = true;
                continue;
            }

            if ($rule === 'array') {
                $schema = [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'example' => ['example-item'],
                ];
                continue;
            }

            if (in_array($rule, ['file', 'image'], true) || Str::startsWith($rule, 'mimes:')) {
                $schema = [
                    'type' => 'string',
                    'format' => 'binary',
                ];
                $binary = true;
                continue;
            }

            if (Str::startsWith($rule, 'in:')) {
                $schema['enum'] = array_values(array_filter(explode(',', Str::after($rule, 'in:'))));
                if (($schema['enum'][0] ?? null) !== null) {
                    $schema['example'] = $schema['enum'][0];
                }
                continue;
            }

            if (Str::startsWith($rule, 'min:')) {
                $value = (int) Str::after($rule, 'min:');
                if (($schema['type'] ?? 'string') === 'string') {
                    $schema['minLength'] = $value;
                } else {
                    $schema['minimum'] = $value;
                }
                continue;
            }

            if (Str::startsWith($rule, 'max:')) {
                $value = (int) Str::after($rule, 'max:');
                if (($schema['type'] ?? 'string') === 'string') {
                    $schema['maxLength'] = $value;
                } else {
                    $schema['maximum'] = $value;
                }
                continue;
            }

            if (Str::contains($rule, 'PasswordRule::')) {
                $schema['type'] = 'string';
                $schema['format'] = 'password';
                $schema['example'] = 'Secret123!';

                if (preg_match('/min\((\d+)\)/', $rule, $matches)) {
                    $schema['minLength'] = (int) $matches[1];
                }
                continue;
            }
        }

        if ($field === 'password') {
            $schema['format'] = 'password';
            $schema['example'] = 'Secret123!';
        }

        if ($field === 'code') {
            $schema['example'] = '123456';
        }

        if ($field === 'two_factor_token') {
            $schema['example'] = 'temporary-2fa-token';
        }

        return [
            'schema' => $schema,
            'required' => $required,
            'binary' => $binary,
        ];
    }

    protected function defaultExample(string $field): mixed
    {
        return match ($field) {
            'email' => 'admin@hive-os.com',
            'password' => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'token' => 'reset-token-value',
            'two_factor_token' => 'temporary-2fa-token',
            'code' => '123456',
            'name' => 'Example Name',
            'title' => 'Example Title',
            'description' => 'Example description',
            'is_active' => true,
            'items' => ['example-item'],
            default => Str::contains($field, 'id') ? '1' : 'example-value',
        };
    }

    protected function extractPathParameters(string $path): array
    {
        preg_match_all('/{([^}]+)}/', $path, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $parameter) => [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
            ])
            ->values()
            ->all();
    }

    protected function supportsTenantHeader(string $path, LaravelRoute $route): bool
    {
        return Str::startsWith($path, ['/v1/tenant', '/v1/translations', '/v1/settings', '/v1/system', '/v1/dashboard', '/v1/search', '/v1/convert', '/v1/files', '/v1/logs', '/v1/localization', '/v1/users', '/v1/roles', '/v1/permissions', '/v1/profile', '/v1/login', '/v1/verify-2fa', '/v1/reset-password', '/v1/password-policy'])
            || $route->getDomain() === null;
    }

    protected function buildResponses(bool $requiresAuth, string $path, string $actionMethod): array
    {
        $successSchema = null;

        if (Str::contains($path, '/login')) {
            $successSchema = ['$ref' => '#/components/schemas/LoginSuccess'];
        }

        $responses = [
            '200' => [
                'description' => 'Successful response',
            ],
            '422' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationError'],
                    ],
                ],
            ],
        ];

        if ($successSchema !== null) {
            $responses['200']['content'] = [
                'application/json' => [
                    'schema' => $successSchema,
                ],
            ];
        }

        if ($actionMethod === 'login') {
            $responses['200']['description'] = 'Authentication succeeded or 2FA challenge returned';
            $responses['200']['content'] = [
                'application/json' => [
                    'schema' => [
                        'oneOf' => [
                            ['$ref' => '#/components/schemas/LoginSuccess'],
                            ['$ref' => '#/components/schemas/TwoFactorChallenge'],
                        ],
                    ],
                ],
            ];
        }

        if ($requiresAuth) {
            $responses['401'] = [
                'description' => 'Authentication required',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/UnauthorizedError'],
                    ],
                ],
            ];
        }

        return $responses;
    }

    protected function makeSummary(string $method, string $path, ?string $controller, string $actionMethod): string
    {
        if ($controller) {
            $controllerName = Str::of(class_basename($controller))
                ->replace('Controller', '')
                ->headline();

            return trim("{$controllerName} {$this->humanizeAction($actionMethod, $method)}");
        }

        return sprintf('%s %s', strtoupper($method), $this->headlineFromPath($path));
    }

    protected function makeDescription(LaravelRoute $route, ?string $controller, string $actionMethod, array $middleware, string $path): ?string
    {
        $details = [];

        if (Str::contains($path, '/tenant/')) {
            $details[] = 'Scope: tenant alias endpoint.';
        } elseif ($route->getDomain() === null) {
            $details[] = 'Scope: shared endpoint. Use X-Tenant or a tenant hostname for tenant traffic.';
        } else {
            $details[] = 'Scope: central-domain endpoint.';
        }

        if ($controller) {
            $details[] = 'Handler: '.class_basename($controller).'@'.$actionMethod;
        } else {
            $details[] = 'Handler: route closure';
        }

        $filteredMiddleware = collect($middleware)
            ->reject(fn (string $item) => in_array($item, ['api', 'web'], true))
            ->values()
            ->all();

        if ($filteredMiddleware !== []) {
            $details[] = 'Middleware: '.implode(', ', $filteredMiddleware);
        }

        if ($route->getDomain()) {
            $details[] = 'Domain scope: '.$route->getDomain();
        }

        return implode("\n\n", $details);
    }

    protected function inferTag(string $path, ?string $controller): string
    {
        if ($controller) {
            $controllerName = class_basename($controller);

            if (Str::contains($controllerName, 'Auth')) {
                return 'Authentication';
            }

            if (Str::contains($controllerName, 'Profile')) {
                return 'Profile';
            }

            if (Str::contains($controllerName, 'SystemOperations')) {
                return 'System Operations';
            }

            return Str::of($controllerName)->replace('Controller', '')->headline()->toString();
        }

        $segments = collect(explode('/', trim($path, '/')))
            ->reject(fn (string $segment) => in_array($segment, ['v1', 'tenant', 'central'], true))
            ->values();

        return Str::headline($segments->first() ?? 'General');
    }

    protected function makeOperationId(string $method, string $path): string
    {
        return Str::camel(strtoupper($method).' '.str_replace(['/', '{', '}'], [' ', ' ', ' '], trim($path, '/')));
    }

    protected function humanizeAction(string $actionMethod, string $httpMethod): string
    {
        return match ($actionMethod) {
            'index' => 'list',
            'show' => 'detail',
            'store' => 'create',
            'update' => 'update',
            'destroy' => 'delete',
            default => Str::headline($actionMethod),
        } ?: strtoupper($httpMethod);
    }

    protected function headlineFromPath(string $path): string
    {
        return Str::of(trim($path, '/'))
            ->replace(['v1/', '/'], ['', ' '])
            ->headline()
            ->toString();
    }

    protected function buildTagIndex(array $paths): array
    {
        $tags = collect($paths)
            ->flatMap(fn (array $methods) => collect($methods)->pluck('tags')->flatten(1))
            ->unique()
            ->sort()
            ->values();

        return $tags->map(fn (string $tag) => ['name' => $tag])->all();
    }

    protected function baseOrigin(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    protected function frontendDocsUrl(Request $request): ?string
    {
        $host = $request->getHost();
        $scheme = $request->getScheme();

        return "{$scheme}://{$host}:3000/api-docs";
    }
}



