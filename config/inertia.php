<?php

/*
| Published to point Inertia at resources/js/pages (lowercase). The package
| default is js/Pages: identical on case-insensitive filesystems (Windows,
| macOS) but a different directory on Linux, where CI and production run.
*/

$pagePaths = [resource_path('js/pages')];
$pageExtensions = ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'];

return [

    'ssr' => [
        'enabled' => (bool) env('INERTIA_SSR_ENABLED', true),
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
    ],

    'ensure_pages_exist' => false,

    'page_paths' => $pagePaths,

    'page_extensions' => $pageExtensions,

    'use_script_element_for_initial_page' => (bool) env('INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE', false),

    // assertInertia() checks that the component file exists on disk.
    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => $pagePaths,
        'page_extensions' => $pageExtensions,
    ],

    'history' => [
        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),
    ],

];
