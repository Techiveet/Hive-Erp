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
                'label' => $definition['label'],
                'description' => $definition['description'],
                'icon' => $definition['icon'],
                'default_template_key' => $definition['default_template_key'] ?? 'signature',
                'default_template' => $definition['default_template'],
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

        Setting::on($this->centralConnection())->updateOrCreate(
            ['key' => self::SETTINGS_KEY],
            ['value' => json_encode(array_values($normalized))]
        );

        clear_system_settings_cache();
        $this->resolvedCatalog = $normalized;

        return $this->businessTypesPayload();
    }

    protected function baseCatalog(): array
    {
        $base = $this->baseTemplate();

        return [
            'general' => [
                'label' => 'General Business',
                'description' => 'Balanced landing page for agencies, service teams, and multipurpose brands.',
                'icon' => 'layout-dashboard',
                'default_template' => $base,
            ],
            'retail' => [
                'label' => 'Retail Store',
                'description' => 'Merchandising-focused layout for launches, collections, and store traffic.',
                'icon' => 'store',
                'default_template' => array_replace_recursive($base, [
                    'theme' => [
                        'accent' => '#ea580c',
                        'accent_soft' => '#ffedd5',
                        'surface' => '#fff7ed',
                        'canvas' => 'linear-gradient(135deg, #fff7ed 0%, #fffbeb 38%, #fef2f2 100%)',
                    ],
                    'hero' => [
                        'eyebrow' => 'Retail Experience',
                        'title' => 'Turn every collection drop into a storefront moment.',
                        'description' => 'Highlight best sellers, seasonal campaigns, and in-store offers from one polished homepage.',
                        'primary_label' => 'Shop Featured Drops',
                        'secondary_label' => 'See New Arrivals',
                        'announcement' => 'Fresh arrivals, curated bundles, and limited seasonal edits are now live.',
                    ],
                    'stats' => [
                        ['value' => '38%', 'label' => 'repeat purchase lift'],
                        ['value' => '4.8/5', 'label' => 'storefront rating'],
                        ['value' => 'Same Day', 'label' => 'pickup and delivery'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Merchandising', 'title' => 'Feature collections with an editorial rhythm', 'description' => 'Give campaigns enough space to feel premium and focused.'],
                        ['kicker' => 'Offers', 'title' => 'Promotions can feel elevated', 'description' => 'Bundle sales, loyalty rewards, and launch-day offers without visual noise.'],
                        ['kicker' => 'Store traffic', 'title' => 'Bridge online discovery and walk-ins', 'description' => 'Use the homepage like a polished window display for social and in-store traffic.'],
                    ],
                    'spotlight' => [
                        'heading' => 'Built for modern retail teams',
                        'description' => 'From curated drops to loyalty campaigns, the layout supports the moments that move inventory.',
                        'items' => [
                            ['title' => 'Launch capsule collections', 'description' => 'Stage seasonal edits, hero products, and featured bundles in one place.'],
                            ['title' => 'Promote in-store pickup', 'description' => 'Give busy customers a faster path from discovery to collection.'],
                            ['title' => 'Highlight trust signals', 'description' => 'Show customer love, delivery speed, and quality guarantees early.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'Our homepage finally feels like the same brand people see in the store.', 'author' => 'Sara N.', 'role' => 'Retail Director'],
                        ['quote' => 'The featured collection blocks helped us push launches without clutter.', 'author' => 'Yonatan M.', 'role' => 'Merchandising Lead'],
                    ],
                    'final_cta' => [
                        'title' => 'Make your storefront feel curated before customers step inside.',
                        'description' => 'Spotlight what is new, trusted, and worth attention right now.',
                        'primary_label' => 'Open Store Portal',
                        'secondary_label' => 'View Featured Lines',
                    ],
                ]),
            ],
            'restaurant' => [
                'label' => 'Restaurant',
                'description' => 'Warm hospitality template for menus, reservations, and signature dishes.',
                'icon' => 'utensils-crossed',
                'default_template' => array_replace_recursive($base, [
                    'theme' => [
                        'accent' => '#b45309',
                        'accent_soft' => '#fef3c7',
                        'surface' => '#fffbeb',
                        'canvas' => 'linear-gradient(135deg, #fffbeb 0%, #fff7ed 42%, #fef2f2 100%)',
                    ],
                    'hero' => [
                        'eyebrow' => 'Dining and Hospitality',
                        'title' => 'Set the mood before the first plate lands on the table.',
                        'description' => 'Turn your homepage into an elegant digital host for reservations, specials, and private dining.',
                        'primary_label' => 'Reserve a Table',
                        'secondary_label' => 'Explore the Menu',
                        'announcement' => 'Chef tasting nights, family platters, and priority reservations are now available.',
                    ],
                    'stats' => [
                        ['value' => '12 min', 'label' => 'average reservation flow'],
                        ['value' => '4.9/5', 'label' => 'guest satisfaction'],
                        ['value' => '7 Days', 'label' => 'lunch and dinner service'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Reservations', 'title' => 'Guide guests from craving to confirmed booking', 'description' => 'Make it obvious where to book and what kind of experience to expect.'],
                        ['kicker' => 'Signature dishes', 'title' => 'Let the food carry the story', 'description' => 'Give hero dishes, tasting menus, and specials the kind of attention they deserve.'],
                        ['kicker' => 'Private events', 'title' => 'Showcase celebrations and group dining', 'description' => 'Position the restaurant for birthdays, business dinners, and curated packages.'],
                    ],
                    'spotlight' => [
                        'heading' => 'What this restaurant landing page can emphasize',
                        'description' => 'Every section is tuned to drive appetite, confidence, and easy next steps.',
                        'items' => [
                            ['title' => 'Highlight chef specials', 'description' => 'Feature weekly dishes and tasting experiences with stronger storytelling.'],
                            ['title' => 'Promote reservations and delivery', 'description' => 'Give guests a clear path whether they want a table or a quick order.'],
                            ['title' => 'Frame atmosphere and service', 'description' => 'Use layout and proof points to show that the dining experience matters.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'Guests arrive already excited because the homepage tells the story so well.', 'author' => 'Chef Liya', 'role' => 'Founder'],
                        ['quote' => 'Reservations became easier the moment we made the landing page feel intentional.', 'author' => 'Henok T.', 'role' => 'Restaurant Manager'],
                    ],
                    'final_cta' => [
                        'title' => 'Invite guests in with the same care you put into the menu.',
                        'description' => 'Make bookings, specials, and standout experiences easy to discover in one refined flow.',
                        'primary_label' => 'Reserve Now',
                        'secondary_label' => 'See Signature Plates',
                    ],
                ]),
            ],
            'hotel' => [
                'label' => 'Hotel',
                'description' => 'Premium hospitality layout for rooms, amenities, and concierge-ready booking flows.',
                'icon' => 'hotel',
                'default_template' => array_replace_recursive($base, [
                    'theme' => [
                        'accent' => '#0f766e',
                        'accent_soft' => '#ccfbf1',
                        'surface' => '#f0fdfa',
                        'canvas' => 'linear-gradient(135deg, #f0fdfa 0%, #ecfeff 38%, #eef2ff 100%)',
                    ],
                    'hero' => [
                        'eyebrow' => 'Boutique Hospitality',
                        'title' => 'Give every guest a digital first impression that feels calm and premium.',
                        'description' => 'Present rooms, amenities, and concierge support from a homepage designed to build trust before arrival.',
                        'primary_label' => 'Book a Stay',
                        'secondary_label' => 'Explore Signature Rooms',
                        'announcement' => 'Airport pickups, curated weekend packages, and suite upgrades are now bookable.',
                    ],
                    'stats' => [
                        ['value' => '96%', 'label' => 'guest return intent'],
                        ['value' => '24/7', 'label' => 'front desk support'],
                        ['value' => '4.9/5', 'label' => 'stay satisfaction'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Suites and rooms', 'title' => 'Show the property with quiet confidence', 'description' => 'Make room categories, upgrades, and packages feel memorable.'],
                        ['kicker' => 'Amenities', 'title' => 'Frame experiences, not just facilities', 'description' => 'Turn spa access, transport, and breakfast into decision-making advantages.'],
                        ['kicker' => 'Concierge', 'title' => 'Reduce guest uncertainty before arrival', 'description' => 'Answer common questions through layout, proof points, and clearer actions.'],
                    ],
                    'spotlight' => [
                        'heading' => 'Ideal for guest-first hospitality brands',
                        'description' => 'The sections are tuned to make bookings feel reassuring, elegant, and fast.',
                        'items' => [
                            ['title' => 'Feature room categories and upgrades', 'description' => 'Guide guests toward your most profitable stays with better hierarchy.'],
                            ['title' => 'Promote concierge-friendly services', 'description' => 'Make transport, amenities, and private dining easy to discover.'],
                            ['title' => 'Reinforce trust before check-in', 'description' => 'Use testimonials, stats, and CTAs to reduce hesitation for new guests.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'The new landing experience feels like our lobby translated beautifully onto the web.', 'author' => 'Marta G.', 'role' => 'Hospitality Director'],
                        ['quote' => 'Guests now understand our room differences and amenities before they ever call.', 'author' => 'Daniel K.', 'role' => 'Front Office Lead'],
                    ],
                    'final_cta' => [
                        'title' => 'Turn calm presentation into confident bookings.',
                        'description' => 'Surface premium offers and make next steps feel effortless.',
                        'primary_label' => 'Open Guest Portal',
                        'secondary_label' => 'Contact Concierge',
                    ],
                ]),
            ],
            'clinic' => [
                'label' => 'Clinic',
                'description' => 'Trust-centered healthcare landing page for appointments and patient reassurance.',
                'icon' => 'stethoscope',
                'default_template' => array_replace_recursive($base, [
                    'theme' => [
                        'accent' => '#2563eb',
                        'accent_soft' => '#dbeafe',
                        'surface' => '#eff6ff',
                        'canvas' => 'linear-gradient(135deg, #eff6ff 0%, #f0f9ff 38%, #ecfeff 100%)',
                    ],
                    'hero' => [
                        'eyebrow' => 'Patient Care',
                        'title' => 'Design a front door that feels clear, calm, and medically trustworthy.',
                        'description' => 'Show specialties, appointment paths, and care standards from a landing page built to reduce uncertainty.',
                        'primary_label' => 'Book Appointment',
                        'secondary_label' => 'View Specialties',
                        'announcement' => 'Same-day consultations, specialist referrals, and digital intake are now supported.',
                    ],
                    'stats' => [
                        ['value' => '98%', 'label' => 'patient follow-through'],
                        ['value' => '7 Days', 'label' => 'appointment availability'],
                        ['value' => '15 min', 'label' => 'average intake time'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Clarity', 'title' => 'Help patients know what to do next', 'description' => 'Present appointments, services, and contact routes in a structure that feels calm.'],
                        ['kicker' => 'Trust', 'title' => 'Use proof points without feeling cold', 'description' => 'Operational stats and testimonials can reassure patients without overwhelming them.'],
                        ['kicker' => 'Specialties', 'title' => 'Make care categories easier to understand', 'description' => 'Group core services and specialist pathways in a way that supports faster decisions.'],
                    ],
                    'spotlight' => [
                        'heading' => 'Designed for modern healthcare communication',
                        'description' => 'The landing page supports reassurance first, then moves patients smoothly toward appointments.',
                        'items' => [
                            ['title' => 'Guide patients to the right service', 'description' => 'Explain key departments and consultations with less friction.'],
                            ['title' => 'Promote digital booking', 'description' => 'Give patients a fast way to request care without losing trust signals.'],
                            ['title' => 'Answer common concerns early', 'description' => 'Reinforce timings, specialist access, and what a first visit looks like.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'Patients say the homepage makes the clinic feel organized and reassuring.', 'author' => 'Dr. Bethlehem', 'role' => 'Medical Director'],
                        ['quote' => 'Appointment requests became easier because people understand where to go.', 'author' => 'Mulu S.', 'role' => 'Operations Lead'],
                    ],
                    'final_cta' => [
                        'title' => 'Build confidence before the patient arrives.',
                        'description' => 'Communicate care quality, accessibility, and the right next step with clarity.',
                        'primary_label' => 'Request Care',
                        'secondary_label' => 'Explore Services',
                    ],
                ]),
            ],
            'logistics' => [
                'label' => 'Logistics',
                'description' => 'Operational template for freight, fleet, fulfillment, and time-sensitive delivery businesses.',
                'icon' => 'truck',
                'default_template' => array_replace_recursive($base, [
                    'theme' => [
                        'accent' => '#0f172a',
                        'accent_soft' => '#e2e8f0',
                        'surface' => '#f8fafc',
                        'canvas' => 'linear-gradient(135deg, #f8fafc 0%, #eff6ff 42%, #ecfeff 100%)',
                    ],
                    'hero' => [
                        'eyebrow' => 'Fleet and Fulfillment',
                        'title' => 'Signal speed, control, and reliability from the first scroll.',
                        'description' => 'Turn your landing page into a sharper operations hub for shipping requests, service coverage, and delivery trust.',
                        'primary_label' => 'Track or Request Service',
                        'secondary_label' => 'View Coverage',
                        'announcement' => 'Priority lanes, route optimization, and warehouse handoffs are now featured for enterprise clients.',
                    ],
                    'stats' => [
                        ['value' => '99.2%', 'label' => 'on-time dispatch'],
                        ['value' => '24/7', 'label' => 'shipment visibility'],
                        ['value' => '12 hubs', 'label' => 'regional coverage'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Reliability', 'title' => 'Translate operational precision into customer confidence', 'description' => 'Make timing, coverage, and accountability visible in a way that wins trust quickly.'],
                        ['kicker' => 'Coverage', 'title' => 'Explain routes, capacity, and services clearly', 'description' => 'Give clients a confident snapshot of what you move and how quickly you respond.'],
                        ['kicker' => 'Enterprise readiness', 'title' => 'Position the business for larger accounts', 'description' => 'Use proof points and CTA structure that feel credible to procurement teams.'],
                    ],
                    'spotlight' => [
                        'heading' => 'Best for freight, delivery, and fulfillment teams',
                        'description' => 'The structure balances credibility, speed, and operational clarity without feeling overly technical.',
                        'items' => [
                            ['title' => 'Promote service lanes and coverage', 'description' => 'Show clients how quickly you can move goods between key routes.'],
                            ['title' => 'Highlight tracking and visibility', 'description' => 'Make real-time control part of the offer, not an afterthought.'],
                            ['title' => 'Support enterprise sales conversations', 'description' => 'Use the landing page as a sharper introduction for larger contracts.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'The page finally communicates how disciplined our operations team really is.', 'author' => 'Kalkidan T.', 'role' => 'Logistics Director'],
                        ['quote' => 'Clients reach out already understanding our coverage and reliability story.', 'author' => 'Samuel R.', 'role' => 'Fleet Operations Lead'],
                    ],
                    'final_cta' => [
                        'title' => 'Make operational trust visible from the first click.',
                        'description' => 'Use the homepage to frame your speed, coverage, and control as a clear edge.',
                        'primary_label' => 'Open Operations Portal',
                        'secondary_label' => 'Review Coverage',
                    ],
                ]),
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

        return is_array($decoded)
            ? $this->normalizeStoredCatalog($decoded, $baseCatalog)
            : [];
    }

    protected function normalizeStoredCatalog(array $definitions, array $baseCatalog): array
    {
        $normalized = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $businessKey = $this->normalizeBusinessTypeFromCatalog($definition['key'] ?? null, $baseCatalog);
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
