<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class LocalizationController extends Controller
{
    private function getTenantContext()
    {
        $isTenant = function_exists('tenant') && tenant('id');
        return [
            'is_tenant' => $isTenant,
            'tenant_id' => $isTenant ? tenant('id') : 'central',
        ];
    }

    /**
     * Fetch all available languages
     */
    public function getLanguages()
    {
        $languages = Language::orderBy('is_default', 'desc')->orderBy('name')->get();
        return response()->json(['data' => $languages]);
    }

    /**
     * 🚀 SMART ROUTER: Fetch & Search Translations via Meilisearch
     */
    public function getTranslations(Request $request, $code)
    {
        $context = $this->getTenantContext();
        $search = $request->input('search', '');

        $query = Translation::where('language_code', $code);
        $engine = 'database';

        if (!empty($search)) {
            $scoutDriver = config('scout.driver');
            $meilisearchSuccess = false;

            // 🚀 ROUTE 1: MEILISEARCH ENGINE (With Timeout Protection)
            if ($scoutDriver === 'meilisearch') {
                try {
                    $indexName = $context['is_tenant']
                        ? "tenant_{$context['tenant_id']}_translations"
                        : "central_translations";

                    $scout = Translation::search($search)->within($indexName);

                    // Filter down to the specific language being edited
                    $scout->query(function ($q) use ($code) {
                        $q->where('language_code', $code);
                    });

                    // We use get() instead of paginate() because React matrices usually need the full dictionary
                    $translations = $scout->get();
                    $engine = 'meilisearch';
                    $meilisearchSuccess = true;
                } catch (\Exception $e) {
                    Log::warning("Localization Meilisearch failed, falling back to DB: " . $e->getMessage());
                    $meilisearchSuccess = false;
                }
            }

            // 🚀 ROUTE 2: DATABASE ENGINE FALLBACK
            if (!$meilisearchSuccess) {
                $query->where(function ($q) use ($search) {
                    $q->where('key', 'like', "%{$search}%")
                      ->orWhere('value', 'like', "%{$search}%")
                      ->orWhere('group', 'like', "%{$search}%");
                });

                $translations = $query->get();
                $engine = $scoutDriver === 'meilisearch' ? 'database_fallback' : 'database';
            }
        } else {
            // No search, return all translations for this language
            $translations = $query->get();
        }

        // Format into a flat key=>value pair for the React frontend
        $formatted = [];
        foreach ($translations as $t) {
            $fullKey = $t->group === 'global' ? $t->key : "{$t->group}.{$t->key}";
            $formatted[$fullKey] = $t->value;
        }

        return response()->json([
            'data' => $formatted,
            'meta' => [
                'engine' => $engine,
                'total' => count($formatted)
            ]
        ]);
    }

    /**
     * Register a new Language Folder
     */
    public function storeLanguage(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:10|unique:languages,code',
        ]);

        $lang = Language::create([
            'name' => $request->name,
            'code' => strtolower($request->code),
            'is_default' => false
        ]);

        return response()->json(['message' => 'Language registered successfully', 'data' => $lang], 201);
    }

    /**
     * Purge a Language and its translations
     */
    public function destroyLanguage($code)
    {
        $lang = Language::where('code', $code)->firstOrFail();

        if ($lang->is_default) {
            return response()->json(['message' => 'Cannot delete the master source language.'], 403);
        }

        Translation::where('language_code', $code)->delete();
        $lang->delete();

        return response()->json(['message' => 'Language matrix purged.']);
    }

    /**
     * Update or Create a single translation entry
     */
    public function updateTranslation(Request $request)
    {
        $request->validate([
            'language_code' => 'required|string|exists:languages,code',
            'key' => 'required|string',
            'value' => 'nullable|string',
        ]);

        // Parse group from key (e.g., "auth.login_title" -> group: "auth", key: "login_title")
        $parts = explode('.', $request->key, 2);
        $group = count($parts) > 1 ? $parts[0] : 'global';
        $actualKey = count($parts) > 1 ? $parts[1] : $request->key;

        $translation = Translation::updateOrCreate(
            [
                'language_code' => $request->language_code,
                'group' => $group,
                'key' => $actualKey,
            ],
            [
                'value' => $request->value ?? ''
            ]
        );

        return response()->json(['message' => 'Translation saved.', 'data' => $translation]);
    }

    /**
     * Add a global system key to ALL languages
     */
    public function addSystemKey(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|string',
        ]);

        $parts = explode('.', $request->key, 2);
        $group = count($parts) > 1 ? $parts[0] : 'global';
        $actualKey = count($parts) > 1 ? $parts[1] : $request->key;

        $languages = Language::all();
        $sourceLang = Language::where('is_default', true)->first();

        foreach ($languages as $lang) {
            Translation::firstOrCreate(
                [
                    'language_code' => $lang->code,
                    'group' => $group,
                    'key' => $actualKey,
                ],
                [
                    // Only inject the value into the master language, leave others blank for translating later
                    'value' => ($sourceLang && $lang->code === $sourceLang->code) ? $request->value : ''
                ]
            );
        }

        return response()->json(['message' => 'System key injected globally.']);
    }

    /**
     * Purge a global system key from ALL languages
     */
    public function destroySystemKey(Request $request)
    {
        $request->validate(['key' => 'required|string']);

        $parts = explode('.', $request->key, 2);
        $group = count($parts) > 1 ? $parts[0] : 'global';
        $actualKey = count($parts) > 1 ? $parts[1] : $request->key;

        Translation::where('group', $group)->where('key', $actualKey)->delete();

        return response()->json(['message' => 'System key purged globally.']);
    }

    /**
     * Compile Database Translations into physical JSON files
     */
    public function publish()
    {
        $context = $this->getTenantContext();
        $languages = Language::all();

        // If multi-tenant, you likely want to store files in storage/app/tenants/{id}/lang
        // If central, resources/lang
        $basePath = $context['is_tenant']
            ? storage_path("app/tenants/{$context['tenant_id']}/lang")
            : base_path('lang');

        foreach ($languages as $lang) {
            $langPath = "{$basePath}/{$lang->code}";
            if (!File::exists($langPath)) {
                File::makeDirectory($langPath, 0755, true);
            }

            // Group translations by file
            $translations = Translation::where('language_code', $lang->code)->get()->groupBy('group');

            foreach ($translations as $group => $items) {
                $content = [];
                foreach ($items as $item) {
                    $content[$item->key] = $item->value;
                }

                // Global group goes to the root {lang}.json, others go to {group}.php or {group}.json
                if ($group === 'global') {
                    File::put("{$basePath}/{$lang->code}.json", json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                } else {
                    File::put("{$langPath}/{$group}.json", json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return response()->json(['message' => 'All matrices compiled and published successfully.']);
    }

    public function fetchTranslations(Request $request)
{
    // 🚀 FIX: Use ->input() or ->query() instead of ->get()
    $locale = $request->input('locale', app()->getLocale());

    $path = resource_path("lang/{$locale}.json");

    if (File::exists($path)) {
        return response()->json(json_decode(File::get($path), true));
    }

    return response()->json([]);
}
}
