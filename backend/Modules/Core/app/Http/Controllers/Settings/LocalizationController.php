<?php

namespace Modules\Core\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Modules\Core\Models\Language;
use Modules\Core\Models\Translation;
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

        // 🚀 FIX 1: Look up the language ID first
        $language = Language::where('code', $code)->firstOrFail();

        // 🚀 FIX 2: Query by language_id, NOT language_code
        $query = Translation::where('language_id', $language->id);
        $engine = 'database';

        if (!empty($search)) {
            $scoutDriver = config('scout.driver');
            $meilisearchSuccess = false;

            if ($scoutDriver === 'meilisearch') {
                try {
                    $indexName = $context['is_tenant']
                        ? "tenant_{$context['tenant_id']}_translations"
                        : "central_translations";

                    $scout = Translation::search($search)->within($indexName);

                    // 🚀 FIX 3: Filter Scout by language_id
                    $scout->query(function ($q) use ($language) {
                        $q->where('language_id', $language->id);
                    });

                    $translations = $scout->get();
                    $engine = 'meilisearch';
                    $meilisearchSuccess = true;
                } catch (\Exception $e) {
                    Log::warning("Localization Meilisearch failed, falling back to DB: " . $e->getMessage());
                    $meilisearchSuccess = false;
                }
            }

            if (!$meilisearchSuccess) {
                $query->where(function ($q) use ($search) {
                    // 🚀 FIX 4: Removed the old 'group' search column
                    $q->where('key', 'like', "%{$search}%")
                      ->orWhere('value', 'like', "%{$search}%");
                });

                $translations = $query->get();
                $engine = $scoutDriver === 'meilisearch' ? 'database_fallback' : 'database';
            }
        } else {
            $translations = $query->get();
        }

        // 🚀 FIX 5: Flat key formatting (No more 'group' logic)
        $formatted = [];
        foreach ($translations as $t) {
            $formatted[$t->key] = $t->value;
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
    public function addLanguage(Request $request)
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

        Translation::where('language_id', $lang->id)->delete();
        $lang->delete();

        return response()->json(['message' => 'Language matrix purged.']);
    }

    /**
     * Update or Create a single translation entry
     */
    public function updateTranslation(Request $request)
    {
        $request->validate([
            'code' => 'required|string|exists:languages,code',
            'key' => 'required|string',
            'value' => 'nullable|string',
        ]);

        $lang = Language::where('code', strtolower($request->code))->firstOrFail();

        $translation = Translation::updateOrCreate(
            [
                'language_id' => $lang->id,
                'key' => $request->key,
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
    public function addSourceKey(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required|string',
        ]);

        $languages = Language::all();
        $sourceLang = Language::where('is_default', true)->first();

        foreach ($languages as $lang) {
            Translation::firstOrCreate(
                [
                    'language_id' => $lang->id,
                    'key' => $request->key,
                ],
                [
                    'value' => ($sourceLang && $lang->id === $sourceLang->id) ? $request->value : ''
                ]
            );
        }

        return response()->json(['message' => 'System key injected globally.']);
    }

    /**
     * Purge a global system key from ALL languages
     */
    public function destroySourceKey(Request $request)
    {
        $request->validate(['key' => 'required|string']);

        Translation::where('key', $request->key)->delete();

        return response()->json(['message' => 'System key purged globally.']);
    }

    /**
     * Delete a specific translation entry for a given language
     */
    public function deleteTranslation(Request $request)
    {
        $request->validate([
            'code' => 'required|string|exists:languages,code',
            'key' => 'required|string',
        ]);

        $lang = Language::where('code', strtolower($request->code))->firstOrFail();

        Translation::where('language_id', $lang->id)
                   ->where('key', $request->key)
                   ->delete();

        return response()->json(['message' => 'Translation deleted successfully.']);
    }

    /**
     * Set a specific language as the system default
     */
    public function setDefaultLanguage(Request $request)
    {
        $request->validate([
            'code' => 'required|string|exists:languages,code',
        ]);

        Language::where('is_default', true)->update(['is_default' => false]);

        $newDefault = Language::where('code', strtolower($request->code))->first();
        $newDefault->is_default = true;
        $newDefault->save();

        return response()->json([
            'message' => "{$newDefault->name} is now the default system language.",
            'data' => $newDefault
        ], 200);
    }

    /**
     * Compile Database Translations into physical JSON files
     */
    public function publishTranslations()
    {
        $context = $this->getTenantContext();
        $languages = Language::all();

        $basePath = $context['is_tenant']
            ? storage_path("app/tenants/{$context['tenant_id']}/lang")
            : base_path('lang');

        if (!File::exists($basePath)) {
            File::makeDirectory($basePath, 0755, true);
        }

        foreach ($languages as $lang) {
            $translations = Translation::where('language_id', $lang->id)->get();
            $content = [];

            foreach ($translations as $item) {
                $content[$item->key] = $item->value;
            }

            File::put("{$basePath}/{$lang->code}.json", json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return response()->json(['message' => 'All matrices compiled and published to JSON successfully.']);
    }

    /**
     * Fetch the physical JSON dictionary for the frontend
     */
    public function fetchTranslations($locale)
    {
        // 🚀 FIX 6: Removed the broken 'is_active' check completely
        $language = Language::where('code', $locale)->first();

        if (!$language) {
            return response()->json(new \stdClass(), 200);
        }

        $translations = $language->translations()->pluck('value', 'key');

        if ($translations->isEmpty()) {
            return response()->json(new \stdClass(), 200);
        }

        return response()->json($translations, 200);
    }
}
