<?php

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Support\TenantLandingTemplateCatalog;

/**
 * Seeds the built-in business type catalog into the central settings table.
 *
 * Custom types added later via the Business Types UI (/dashboard/settings/business-types)
 * are stored in the same settings key and will be merged with these base types.
 *
 * To add more base types: add entries here, then re-run:
 *   php artisan db:seed --class="Modules\Tenancy\Database\Seeders\BusinessTypeSeeder"
 */
class BusinessTypeSeeder extends Seeder
{
    public function __construct(private readonly TenantLandingTemplateCatalog $catalog) {}

    public function run(): void
    {
        $this->catalog->persistBusinessTypesPayload($this->definitions());

        if ($this->command) {
            $this->command->info('Business types seeded: ' . implode(', ', array_column($this->definitions(), 'key')));
        }
    }

    // -------------------------------------------------------------------------
    // Built-in business type definitions.
    // Each entry must have a "key" (slug), "label", "description", and "icon".
    // Template sections override the general base template via array_replace_recursive.
    // -------------------------------------------------------------------------
    protected function definitions(): array
    {
        return [
            [
                'key'         => 'general',
                'label'       => 'General Business',
                'description' => 'Balanced landing page for agencies, service teams, and multipurpose brands.',
                'icon'        => 'layout-dashboard',
            ],
            [
                'key'         => 'retail',
                'label'       => 'Retail Store',
                'description' => 'Merchandising-focused layout for launches, collections, and store traffic.',
                'icon'        => 'store',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#ea580c',
                        'accent_soft' => '#ffedd5',
                        'surface'     => '#fff7ed',
                        'canvas'      => 'linear-gradient(135deg, #fff7ed 0%, #fffbeb 38%, #fef2f2 100%)',
                    ],
                    'hero' => [
                        'eyebrow'      => 'Retail Experience',
                        'title'        => 'Turn every collection drop into a storefront moment.',
                        'description'  => 'Highlight best sellers, seasonal campaigns, and in-store offers from one polished homepage.',
                        'primary_label'   => 'Shop Featured Drops',
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
                        'heading'     => 'Built for modern retail teams',
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
                        'title'          => 'Make your storefront feel curated before customers step inside.',
                        'description'    => 'Spotlight what is new, trusted, and worth attention right now.',
                        'primary_label'   => 'Open Store Portal',
                        'secondary_label' => 'View Featured Lines',
                    ],
                ],
            ],
            [
                'key'         => 'restaurant',
                'label'       => 'Restaurant',
                'description' => 'Warm hospitality template for menus, reservations, and signature dishes.',
                'icon'        => 'utensils-crossed',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#b45309',
                        'accent_soft' => '#fef3c7',
                        'surface'     => '#fffbeb',
                        'canvas'      => 'linear-gradient(135deg, #fffbeb 0%, #fff7ed 42%, #fef2f2 100%)',
                    ],
                    'hero' => [
                        'eyebrow'      => 'Dining and Hospitality',
                        'title'        => 'Set the mood before the first plate lands on the table.',
                        'description'  => 'Turn your homepage into an elegant digital host for reservations, specials, and private dining.',
                        'primary_label'   => 'Reserve a Table',
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
                        'heading'     => 'What this restaurant landing page can emphasize',
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
                        'title'          => 'Invite guests in with the same care you put into the menu.',
                        'description'    => 'Make bookings, specials, and standout experiences easy to discover in one refined flow.',
                        'primary_label'   => 'Reserve Now',
                        'secondary_label' => 'See Signature Plates',
                    ],
                ],
            ],
            [
                'key'         => 'hotel',
                'label'       => 'Hotel',
                'description' => 'Premium hospitality layout for rooms, amenities, and concierge-ready booking flows.',
                'icon'        => 'hotel',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#0f766e',
                        'accent_soft' => '#ccfbf1',
                        'surface'     => '#f0fdfa',
                        'canvas'      => 'linear-gradient(135deg, #f0fdfa 0%, #ecfeff 38%, #eef2ff 100%)',
                    ],
                    'hero' => [
                        'eyebrow'      => 'Boutique Hospitality',
                        'title'        => 'Give every guest a digital first impression that feels calm and premium.',
                        'description'  => 'Present rooms, amenities, and concierge support from a homepage designed to build trust before arrival.',
                        'primary_label'   => 'Book a Stay',
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
                        'heading'     => 'Ideal for guest-first hospitality brands',
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
                        'title'          => 'Turn calm presentation into confident bookings.',
                        'description'    => 'Surface premium offers and make next steps feel effortless.',
                        'primary_label'   => 'Open Guest Portal',
                        'secondary_label' => 'Contact Concierge',
                    ],
                ],
            ],
            [
                'key'         => 'clinic',
                'label'       => 'Clinic',
                'description' => 'Trust-centered healthcare landing page for appointments and patient reassurance.',
                'icon'        => 'stethoscope',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#2563eb',
                        'accent_soft' => '#dbeafe',
                        'surface'     => '#eff6ff',
                        'canvas'      => 'linear-gradient(135deg, #eff6ff 0%, #f0f9ff 38%, #ecfeff 100%)',
                    ],
                    'hero' => [
                        'eyebrow'      => 'Patient Care',
                        'title'        => 'Design a front door that feels clear, calm, and medically trustworthy.',
                        'description'  => 'Show specialties, appointment paths, and care standards from a landing page built to reduce uncertainty.',
                        'primary_label'   => 'Book Appointment',
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
                        'heading'     => 'Designed for modern healthcare communication',
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
                        'title'          => 'Build confidence before the patient arrives.',
                        'description'    => 'Communicate care quality, accessibility, and the right next step with clarity.',
                        'primary_label'   => 'Request Care',
                        'secondary_label' => 'Explore Services',
                    ],
                ],
            ],
            [
                'key'         => 'logistics',
                'label'       => 'Logistics',
                'description' => 'Operational template for freight, fleet, fulfillment, and time-sensitive delivery businesses.',
                'icon'        => 'truck',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#0f172a',
                        'accent_soft' => '#e2e8f0',
                        'surface'     => '#f8fafc',
                        'canvas'      => 'linear-gradient(135deg, #f8fafc 0%, #eff6ff 42%, #ecfeff 100%)',
                    ],
                    'hero' => [
                        'eyebrow'      => 'Fleet and Fulfillment',
                        'title'        => 'Signal speed, control, and reliability from the first scroll.',
                        'description'  => 'Turn your landing page into a sharper operations hub for shipping requests, service coverage, and delivery trust.',
                        'primary_label'   => 'Track or Request Service',
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
                        'heading'     => 'Best for freight, delivery, and fulfillment teams',
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
                        'title'          => 'Make operational trust visible from the first click.',
                        'description'    => 'Use the homepage to frame your speed, coverage, and control as a clear edge.',
                        'primary_label'   => 'Open Operations Portal',
                        'secondary_label' => 'Review Coverage',
                    ],
                ],
            ],
            [
                'key'         => 'water-bottling',
                'label'       => 'Water Bottling',
                'description' => 'Production and distribution-focused layout for bottled water brands, supply chains, and B2B delivery.',
                'icon'        => 'droplets',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#0284c7',
                        'accent_soft' => '#e0f2fe',
                        'surface'     => '#f0f9ff',
                        'canvas'      => 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 42%, #ecfeff 100%)',
                    ],
                    'hero' => [
                        'eyebrow'         => 'Pure. Bottled. Delivered.',
                        'title'           => 'Clean water, seamless supply, and a brand people trust at first sip.',
                        'description'     => 'Present your production capacity, delivery network, and quality standards from one credible homepage.',
                        'primary_label'   => 'Request a Delivery',
                        'secondary_label' => 'View Our Products',
                        'announcement'    => 'Bulk orders, subscription deliveries, and B2B supply contracts are now available.',
                    ],
                    'stats' => [
                        ['value' => '99.9%', 'label' => 'purity compliance'],
                        ['value' => 'Same Day', 'label' => 'local delivery'],
                        ['value' => '50k+', 'label' => 'litres bottled daily'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Quality', 'title' => 'Lead with purity and production standards', 'description' => 'Show certifications, filtration stages, and testing results to build instant trust.'],
                        ['kicker' => 'Delivery', 'title' => 'Make ordering and scheduling effortless', 'description' => 'Give businesses and households a clear path from product selection to doorstep delivery.'],
                        ['kicker' => 'B2B Supply', 'title' => 'Position for corporate and institutional accounts', 'description' => 'Frame bulk pricing, recurring contracts, and volume capabilities for larger clients.'],
                    ],
                    'spotlight' => [
                        'heading'     => 'Built for bottling operations that serve at scale',
                        'description' => 'Every section is designed to communicate purity, reliability, and a frictionless supply chain.',
                        'items' => [
                            ['title' => 'Promote subscription delivery', 'description' => 'Give homes and offices a dependable way to schedule recurring orders.'],
                            ['title' => 'Showcase production capacity', 'description' => 'Highlight daily output, filtration process, and quality controls to attract larger accounts.'],
                            ['title' => 'Drive B2B inquiries', 'description' => 'Make it easy for restaurants, hospitals, and offices to reach your sales team.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'Our clients now understand our quality standards before we even speak with them.', 'author' => 'Dawit A.', 'role' => 'Operations Director'],
                        ['quote' => 'The B2B inquiry form alone has brought in three new institutional contracts.', 'author' => 'Hana B.', 'role' => 'Sales Lead'],
                    ],
                    'final_cta' => [
                        'title'           => 'Turn purity and reliability into a brand promise your clients believe.',
                        'description'     => 'Make product quality, delivery options, and bulk ordering easy to act on.',
                        'primary_label'   => 'Place an Order',
                        'secondary_label' => 'Contact Our Team',
                    ],
                ],
            ],
            [
                'key'         => 'farm',
                'label'       => 'Farm',
                'description' => 'Agricultural business layout for produce, livestock, and farm-to-table supply operations.',
                'icon'        => 'sprout',
                'default_template' => [
                    'theme' => [
                        'accent'      => '#16a34a',
                        'accent_soft' => '#dcfce7',
                        'surface'     => '#f0fdf4',
                        'canvas'      => 'linear-gradient(135deg, #f0fdf4 0%, #fefce8 42%, #f0fdf4 100%)',
                    ],
                    'hero' => [
                        'eyebrow'         => 'Farm to Market',
                        'title'           => 'Grow your reach as confidently as you grow your harvest.',
                        'description'     => 'Showcase your produce, livestock, and supply capacity from a homepage built for agricultural businesses.',
                        'primary_label'   => 'Browse Our Produce',
                        'secondary_label' => 'Get a Bulk Quote',
                        'announcement'    => 'Seasonal harvests, organic certifications, and direct-to-buyer supply are now available.',
                    ],
                    'stats' => [
                        ['value' => '500+', 'label' => 'acres under cultivation'],
                        ['value' => 'Weekly', 'label' => 'fresh harvest cycles'],
                        ['value' => '100%', 'label' => 'traceability from field to buyer'],
                    ],
                    'highlights' => [
                        ['kicker' => 'Produce', 'title' => 'Showcase seasonal and specialty crops with confidence', 'description' => 'Let buyers see what is in season, what is certified, and what is ready for direct purchase.'],
                        ['kicker' => 'Supply chain', 'title' => 'Connect directly with buyers and distributors', 'description' => 'Cut out unnecessary steps and give buyers a clearer path to bulk sourcing.'],
                        ['kicker' => 'Trust', 'title' => 'Reinforce quality with traceability and certifications', 'description' => 'Use proof points like organic status, harvest dates, and lab results to differentiate.'],
                    ],
                    'spotlight' => [
                        'heading'     => 'Ideal for farm operations selling direct or through distributors',
                        'description' => 'The layout is built to communicate freshness, reliability, and sourcing transparency.',
                        'items' => [
                            ['title' => 'Promote bulk and wholesale pricing', 'description' => 'Give distributors and restaurants a clear path to sourcing agreements.'],
                            ['title' => 'Feature seasonal availability', 'description' => 'Keep buyers informed about what is ready for harvest and when.'],
                            ['title' => 'Build trust through traceability', 'description' => 'Show certifications, growing practices, and supply chain transparency early.'],
                        ],
                    ],
                    'testimonials' => [
                        ['quote' => 'Our homepage now reflects the care we put into every crop we grow.', 'author' => 'Abebe T.', 'role' => 'Farm Owner'],
                        ['quote' => 'Buyers started reaching out directly once they could see our quality standards online.', 'author' => 'Selamawit G.', 'role' => 'Distribution Manager'],
                    ],
                    'final_cta' => [
                        'title'           => 'Bring your harvest to market with a presence buyers trust.',
                        'description'     => 'Make produce availability, bulk pricing, and direct sourcing easy to discover and act on.',
                        'primary_label'   => 'Contact Our Farm',
                        'secondary_label' => 'View Current Stock',
                    ],
                ],
            ],
        ];
    }
}
