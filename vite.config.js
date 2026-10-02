import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { readFileSync } from 'fs'
import { resolve } from 'path'

// import.meta.dirname statt __dirname: Vite 8 warnt, dass __dirname vom kuenftigen
// Standard-Config-Loader ('native') nicht mehr unterstuetzt wird. Diese Datei ist
// ESM ("type": "module"), __dirname funktionierte bisher nur, weil Vite die
// Konfiguration vorher nach CJS umschreibt. Mit dem nativen Loader waere es ein
// Laufzeitfehler beim Build — jetzt statt beim naechsten Major.
const dirname = import.meta.dirname

// `appName` und `appVersion` sind globale Bezeichner, die @nextcloud/vue liest
// (dist/chunks/appName.mjs) — ohne Ersetzung protokolliert die Bibliothek einen
// Fehler. Beide standen hier bis 10/2026 als Literal: der Name in Grossbuchstaben
// und die Version fest auf '0.1.0' (#301).
//
// Der Name muss die APP-ID sein, nicht ein Anzeigename: `useLocalizedAppName()`
// sucht damit in der App-Liste (`apps.find(({ id }) => id === appName)`) und fand
// mit 'VINARIUM' nie etwas — der uebersetzte Anzeigename liess sich also gar
// nicht aufloesen.
//
// Die Version kommt aus `appinfo/info.xml`, der fuehrenden Quelle beim Release
// (package.json traegt seit v0.5.0 unveraendert 0.5.0 und wird nicht gepflegt).
// Damit stimmt sie kuenftig von selbst; NcAppSettingsDialog zeigt sie als
// "<Name> <Version>" an. Muster: rechnungswerk#342.
const appName = JSON.parse(readFileSync(resolve(dirname, 'package.json'), 'utf8')).name
const appVersion = readFileSync(resolve(dirname, 'appinfo/info.xml'), 'utf8')
  .match(/<version>([^<]+)<\/version>/)?.[1]
if (!appVersion) {
  throw new Error('vite.config.js: <version> nicht in appinfo/info.xml gefunden')
}

export default defineConfig(({ mode }) => ({
  plugins: [vue()],
  define: {
    // Vue, Pinia und Vue Router werden als esm-bundler-Builds eingebunden. Die
    // setzen voraus, dass der Bundler process.env.NODE_ENV ersetzt, und liefern
    // bewusst keine eigene Definition mit. Im Library-Modus nimmt Vite das nicht
    // automatisch vor, und ein pauschales 'process.env': {} liess NODE_ENV als
    // undefined durchlaufen — womit Vue im Entwicklungspfad blieb und Warnungen,
    // Prop-Typpruefungen und Devtools-Hooks im Produktionsbundle landeten.
    'process.env.NODE_ENV': JSON.stringify(mode === 'production' ? 'production' : 'development'),
    // Auffangnetz fuer andere process.env.*-Zugriffe; greift erst nach der
    // spezifischeren Ersetzung oben, da Vite laengere Schluessel zuerst anwendet.
    'process.env': {},
    '__VUE_OPTIONS_API__': true,
    '__VUE_PROD_DEVTOOLS__': false,
    '__VUE_PROD_HYDRATION_MISMATCH_DETAILS__': false,
    appName: JSON.stringify(appName),
    appVersion: JSON.stringify(appVersion),
  },
  resolve: {
    alias: {
      vue: resolve(dirname, 'node_modules/vue/dist/vue.esm-bundler.js'),
      '@': resolve(dirname, 'src'),
    },
    dedupe: ['vue'],
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
    lib: {
      entry: resolve(dirname, 'src/main.js'),
      name: 'vinarium',
      formats: ['iife'],
      fileName: () => 'js/vinarium-main.js',
    },
    rollupOptions: {
      output: {
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'css/vinarium-main.css'
          }
          return 'css/[name][extname]'
        },
        globals: {
          vue: 'Vue',
        },
      },
    },
  },
}))
