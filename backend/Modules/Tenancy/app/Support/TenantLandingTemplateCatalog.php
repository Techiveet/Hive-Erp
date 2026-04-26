<?php

namespace Modules\Tenancy\Support;

use Modules\Core\Models\Setting;

class TenantLandingTemplateCatalog
{
    public const SETTINGS_KEY = 'tenant_landing_template_catalog';

    protected ?array $resolvedCatalog = null;

    public function businessTypesPayload(): array
    {
        return collect($this->catalog())
            ->map(fn (array $definition, string $key) => [
                'key' => $key,
                'label' => $definition['label'] ?? '',
                'description' => $definition['description'] ?? '',
                'icon' => $definition['icon'] ?? '',
                'default_template_key' => $definition['default_template_key'] ?? 'signature',
                'default_template' => $definition['default_template'] ?? [],
                'templates' => array_values($definition['templates'] ?? []),
            ])
            ->values()
            ->all();
    }

    public function businessTypeKeys(): array
    {
        return array_keys($this->catalog());
    }

    public function normalizeBusinessType(?string $businessType): string
    {
        return $this->normalizeBusinessTypeFromCatalog($businessType, $this->catalog());
    }

    public function businessTypeMeta(?string $businessType): array
    {
        $key = $this->normalizeBusinessType($businessType);
        $definition = $this->catalog()[$key];

        return [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'icon' => $definition['icon'],
        ];
    }

    public function defaultTemplate(?string $businessType): array
    {
        return $this->catalog()[$this->normalizeBusinessType($businessType)]['default_template'];
    }

    public function normalizeTemplate(mixed $template, ?string $businessType = null): array
    {
        return $this->normalizeTemplateUsingDefault($template, $this->defaultTemplate($businessType));
    }

    protected function catalog(): array
    {
        if ($this->resolvedCatalog !== null) {
            return $this->resolvedCatalog;
        }

        $baseCatalog = $this->baseCatalog();
        $storedCatalog = $this->loadStoredCatalog($baseCatalog);

        return $this->resolvedCatalog = $storedCatalog ?: $baseCatalog;
    }

    public function persistBusinessTypesPayload(array $definitions): array
    {
        $normalized = $this->normalizeStoredCatalog($definitions, $this->baseCatalog());

        $payload = collect($normalized)->map(fn (array $data, string $key) => array_merge(['key' => $key], $data))->values()->all();

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => self::SETTINGS_KEY],
            ['value' => json_encode($payload)]
        );

        clear_system_settings_cache();
        $this->resolvedCatalog = $normalized;

        return $this->businessTypesPayload();
    }

    /**
     * Minimal fallback catalog used ONLY when the DB setting has never been seeded.
     * All rich, per-type templates are defined in BusinessTypeSeeder and stored via
     * persistBusinessTypesPayload(). Add new built-in types there, not here.
     */
    protected function baseCatalog(): array
    {
        $base = $this->baseTemplate();

        return [
            'general' => [
                'label'            => 'General Business',
                'description'      => 'Balanced landing page for agencies, service teams, and multipurpose brands.',
                'icon'             => 'layout-dashboard',
                'default_template' => $base,
            ],
        ];
    }

    protected function normalizeBusinessTypeFromCatalog(?string $businessType, array $catalog): string
    {
        $normalized = strtolower(trim((string) $businessType));

        return array_key_exists($normalized, $catalog) ? $normalized : 'general';
    }

    protected function normalizeTemplateUsingDefault(mixed $template, array $default): array
    {
        if (!is_array($template)) {
            return $default;
        }

        $filtered = [];

        foreach (['meta', 'theme', 'hero', 'stats', 'highlights', 'spotlight', 'testimonials', 'final_cta'] as $section) {
            if (isset($template[$section]) && is_array($template[$section])) {
                $filtered[$section] = $template[$section];
            }
        }

        $normalized = array_replace_recursive($default, $filtered);
        $normalized['version'] = 1;

        if (isset($filtered['meta']) && is_array($filtered['meta'])) {
            $normalized['meta'] = array_filter([
                'business_type' => isset($filtered['meta']['business_type']) ? (string) $filtered['meta']['business_type'] : null,
                'business_label' => isset($filtered['meta']['business_label']) ? (string) $filtered['meta']['business_label'] : null,
                'template_key' => isset($filtered['meta']['template_key']) ? (string) $filtered['meta']['template_key'] : null,
                'template_label' => isset($filtered['meta']['template_label']) ? (string) $filtered['meta']['template_label'] : null,
                'template_description' => isset($filtered['meta']['template_description']) ? (string) $filtered['meta']['template_description'] : null,
                'is_custom' => isset($filtered['meta']['is_custom']) ? (bool) $filtered['meta']['is_custom'] : null,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return $normalized;
    }

    protected function loadStoredCatalog(array $baseCatalog): array
    {
        $raw = Setting::on($this->centralConnection())
            ->where('key', self::SETTINGS_KEY)
            ->value('value');

        if (!$raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (!is_array($decoded) || empty($decoded)) {
            return [];
        }

        $normalized = [];
        foreach ($decoded as $item) {
            $key = $item['key'] ?? '';
            if (empty($key)) {
                continue;
            }
            $normalized[$key] = $item;
        }

        return $normalized;
    }

    protected function normalizeStoredCatalog(array $definitions, array $baseCatalog): array
    {
        $normalized = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $businessKey = strtolower(trim((string) ($definition['key'] ?? '')));
            $businessKey = preg_replace('/[^a-z0-9-]+/', '-', $businessKey) ?: 'general';
            $businessKey = trim($businessKey, '-');
            if ($businessKey === '') {
                $businessKey = 'general';
            }
            $baseDefinition = $baseCatalog[$businessKey] ?? $baseCatalog['general'];
            $templates = $this->normalizeTemplateDefinitions(
                is_array($definition['templates'] ?? null) ? $definition['templates'] : [],
                $businessKey,
                $baseDefinition
            );

            if ($templates === []) {
                $fallbackKey = $this->normalizeTemplateKey($definition['default_template_key'] ?? 'signature');
                $templates[$fallbackKey] = $this->fallbackTemplateDefinition(
                    $fallbackKey,
                    $definition['default_template'] ?? $baseDefinition['default_template'],
                    $businessKey,
                    $baseDefinition
                );
            }

            $defaultTemplateKey = $this->normalizeTemplateKey(
                $definition['default_template_key'] ?? array_key_first($templates) ?? 'signature'
            );

            if (!isset($templates[$defaultTemplateKey])) {
                $defaultTemplateKey = array_key_first($templates) ?? 'signature';
            }

            $normalized[$businessKey] = [
                'label' => trim((string) ($definition['label'] ?? $baseDefinition['label'])) ?: $baseDefinition['label'],
                'description' => trim((string) ($definition['description'] ?? $baseDefinition['description'])) ?: $baseDefinition['description'],
                'icon' => trim((string) ($definition['icon'] ?? $baseDefinition['icon'])) ?: $baseDefinition['icon'],
                'default_template_key' => $defaultTemplateKey,
                'default_template' => $templates[$defaultTemplateKey]['template'],
                'templates' => $templates,
            ];
        }

        foreach ($baseCatalog as $businessKey => $baseDefinition) {
            if (isset($normalized[$businessKey])) {
                continue;
            }

            $normalized[$businessKey] = [
                'label' => $baseDefinition['label'],
                'description' => $baseDefinition['description'],
                'icon' => $baseDefinition['icon'],
                'default_template_key' => 'signature',
                'default_template' => $baseDefinition['default_template'],
                'templates' => [],
            ];
        }

        return $normalized;
    }

    protected function normalizeTemplateDefinitions(array $templates, string $businessKey, array $baseDefinition): array
    {
        $normalized = [];

        foreach ($templates as $index => $template) {
            if (!is_array($template)) {
                continue;
            }

            $templateKey = $this->normalizeTemplateKey($template['key'] ?? "template-".($index + 1));

            if ($templateKey === '') {
                $templateKey = "template-".($index + 1);
            }

            if (isset($normalized[$templateKey])) {
                $templateKey .= '-'.($index + 1);
            }

            $normalized[$templateKey] = $this->fallbackTemplateDefinition(
                $templateKey,
                $template['template'] ?? $template['default_template'] ?? $baseDefinition['default_template'],
                $businessKey,
                $baseDefinition,
                $template['label'] ?? null,
                $template['description'] ?? null
            );
        }

        return $normalized;
    }

    protected function fallbackTemplateDefinition(
        string $templateKey,
        mixed $template,
        string $businessKey,
        array $baseDefinition,
        mixed $label = null,
        mixed $description = null,
    ): array {
        $normalizedTemplate = $this->normalizeTemplateUsingDefault($template, $baseDefinition['default_template']);
        $resolvedLabel = trim((string) ($label ?? $normalizedTemplate['meta']['template_label'] ?? ''));
        $resolvedDescription = trim((string) ($description ?? $normalizedTemplate['meta']['template_description'] ?? ''));

        if ($resolvedLabel === '') {
            $resolvedLabel = ucfirst(str_replace('-', ' ', $templateKey));
        }

        if ($resolvedDescription === '') {
            $resolvedDescription = $baseDefinition['description'];
        }

        $normalizedTemplate['meta'] = array_filter([
            'business_type' => $businessKey,
            'business_label' => $baseDefinition['label'],
            'template_key' => $templateKey,
            'template_label' => $resolvedLabel,
            'template_description' => $resolvedDescription,
            'is_custom' => false,
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'key' => $templateKey,
            'label' => $resolvedLabel,
            'description' => $resolvedDescription,
            'template' => $normalizedTemplate,
        ];
    }

    protected function normalizeTemplateKey(mixed $value): string
    {
        $key = strtolower(trim((string) $value));
        $key = preg_replace('/[^a-z0-9-]+/', '-', $key) ?: '';
        $key = trim($key, '-');

        return $key !== '' ? $key : 'template';
    }

    protected function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', 'central');
    }

    protected function baseTemplate(): array
    {
        return [
            'version' => 1,
            'theme' => [
                'accent' => '#0f766e',
                'accent_soft' => '#ccfbf1',
                'surface' => '#f0fdfa',
                'canvas' => 'linear-gradient(135deg, #f8fafc 0%, #ecfeff 42%, #eef2ff 100%)',
                'panel' => 'rgba(255,255,255,0.82)',
                'text' => '#0f172a',
                'muted' => '#475569',
            ],
            'hero' => [
                'eyebrow' => 'Business Landing',
                'title' => 'Build a homepage that makes the business feel sharp before the first conversation starts.',
                'description' => 'This editable landing template is tuned for clarity, credibility, and fast action across service-first brands.',
                'primary_label' => 'Open Workspace',
                'primary_href' => '/sign-in',
                'secondary_label' => 'Explore What We Offer',
                'secondary_href' => '#offers',
                'announcement' => 'Use this as the public face of your tenant and tailor every line inside the admin editor.',
            ],
            'stats' => [
                ['value' => '24/7', 'label' => 'always-on discovery'],
                ['value' => '3 clicks', 'label' => 'to a clear next step'],
                ['value' => 'Editable', 'label' => 'inside admin'],
            ],
            'highlights' => [
                ['kicker' => 'Positioning', 'title' => 'Lead with a stronger first impression', 'description' => 'Use a tighter headline, better visual rhythm, and clearer calls to action to shape perception fast.'],
                ['kicker' => 'Clarity', 'title' => 'Tell visitors what matters in seconds', 'description' => 'Structure the page so people understand your offer, proof points, and next step quickly.'],
                ['kicker' => 'Momentum', 'title' => 'Turn interest into action', 'description' => 'Guide prospects toward booking, inquiry, sign-in, or direct contact with less friction.'],
            ],
            'spotlight' => [
                'heading' => 'What this template is designed to do',
                'description' => 'The structure works especially well when the business needs to look polished, modern, and easy to understand.',
                'items' => [
                    ['title' => 'Present the offer crisply', 'description' => 'Use the hero and feature blocks to explain your strongest value without clutter.'],
                    ['title' => 'Show proof early', 'description' => 'Stats and testimonials reinforce trust before a visitor decides to act.'],
                    ['title' => 'Keep editing simple', 'description' => 'Every section is stored as editable JSON so admins can tune copy without touching code.'],
                ],
            ],
            'testimonials' => [
                ['quote' => 'This template gave us a much stronger public face without a full redesign cycle.', 'author' => 'Operations Team', 'role' => 'Default Testimonial'],
                ['quote' => 'The structure makes it easy to explain what we do and move visitors toward the next step.', 'author' => 'Growth Team', 'role' => 'Default Testimonial'],
            ],
            'final_cta' => [
                'title' => 'Give your tenant a sharper landing experience.',
                'description' => 'Select a business preset, refine the copy in admin, and publish a homepage that feels purpose-built.',
                'primary_label' => 'Open Portal',
                'primary_href' => '/sign-in',
                'secondary_label' => 'Jump to Services',
                'secondary_href' => '#offers',
            ],
        ];
    }
}
