import { defineConfig } from 'vitest/config';
import { pterodactylTestAliases, pterodactylTestSetup, pterodactylTestDirectory } from '@pterodactyl/sdk/vitest';

export default defineConfig({
    server: { fs: { allow: [process.cwd(), pterodactylTestDirectory] } },
    resolve: { alias: pterodactylTestAliases() },
    test: {
        include: ['src/client/**/*.spec.{ts,tsx}'],
        environment: 'jsdom',
        setupFiles: [pterodactylTestSetup],
        // Keeps the SDK runtime on the same React and TanStack Query copies when it is installed as a copy.
        server: { deps: { inline: [/@pterodactyl\/sdk/] } },
    },
});
