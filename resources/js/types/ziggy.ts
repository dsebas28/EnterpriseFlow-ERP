import { Config, RouteParams } from 'ziggy-js';

declare global {
    function route(): Config;
    // Ziggy accepts a single scalar (the first route parameter) at runtime.
    function route(name: string, params?: RouteParams<typeof name> | string | number, absolute?: boolean): string;
}

// Augment 'vue' (not '@vue/runtime-core'): augmenting the internal package
// shadows the augmentations other libraries (e.g. Inertia's $page) add to 'vue'.
declare module 'vue' {
    interface ComponentCustomProperties {
        route: typeof route;
    }
}
