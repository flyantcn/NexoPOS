import { defineConfig, loadEnv } from 'vite';

// import fs from 'fs';
import laravel from 'laravel-vite-plugin';
import mkcert from 'vite-plugin-mkcert';
import { resolve } from 'path';
// import path from 'path';
import vuePlugin from '@vitejs/plugin-vue';
import tailwindcss from "@tailwindcss/vite";


export default ({ mode }) => {
    process.env = {...process.env, ...loadEnv(mode, process.cwd())};

    return defineConfig({
        base: './',
        server: {
            port: 3331,
            host: '0.0.0.0',
            cors: {
                origin: [
                    'http://192.168.1.33:8080',
                    'http://localhost:8080',
                    'http://127.0.0.1:8080',
                ],
            },
            hmr: {
                protocol: 'wss',
                host: '192.168.1.33',
            },
            https: true,
            watch: {
                ignored: [
                    '**/vendor/**',
                    '**/storage/**',
                    '**/node_modules/**',
                    '**/.git/**',
                    '**/public/build/**',
                    '**/public/modules/**',
                    '**/bootstrap/cache/**',
                ],
            },
        },
        resolve: {
            alias: [
                {
                    find: '&',
                    replacement: resolve( __dirname, 'resources' ),
                }, {
                    find: '~',
                    replacement: resolve( __dirname, 'resources/ts' ),
                },
            ]
        },
        plugins: [
            tailwindcss(),
            laravel({
                input: [
                    'resources/ts/vue-runtime.ts',
                    'resources/ts/bootstrap.ts',
                    'resources/ts/app-init.ts',
                    'resources/ts/app.ts',
                    'resources/ts/auth.ts',
                    'resources/ts/pos.ts',
                    'resources/ts/pos-init.ts',
                    'resources/ts/setup.ts',
                    'resources/ts/update.ts',
                    'resources/ts/cashier.ts',
                    'resources/ts/lang-loader.ts',
                    'resources/ts/dev.ts',
                    'resources/ts/popups.ts',
                    'resources/ts/widgets.ts',
                    'resources/ts/wizard.ts',
                    'resources/ts/guide.ts',
    
                    'resources/css/app.css',
                    'resources/css/grid.css',
                    'resources/css/animations.css',
                    'resources/css/fonts.css',
                    'resources/scss/line-awesome/1.3.0/scss/line-awesome.scss',

                    // themes
                    'resources/css/light.css',
                    'resources/css/dark.css',
                    'resources/css/phosphor.css',
                    'resources/css/print.css',
                ],
                refresh: true,
            }),
            mkcert(),
            vuePlugin({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
    });
}
