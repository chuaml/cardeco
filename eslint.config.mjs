import js from "@eslint/js";
import globals from "globals";
import { defineConfig } from "eslint/config";

export default defineConfig([
  {
    files: ["**/*.{js,mjs,cjs}"],
    plugins: { js },
    extends: ["js/recommended"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.webextensions,
        ...globals.serviceworker,
        Toastify: 'readonly',
      },
      ecmaVersion: 'latest',
    },
    rules: {
      'no-unused-vars': 'off',
      'no-undef': 'error',
      'no-unreachable': 'warn',
      'no-case-declarations': 'off',
      'no-empty': 'warn',
      'no-invalid-this': 'error',
      'no-redeclare': 'warn',

      // 'semi': ['warn', 'always'],
      // 'eqeqeq': 'warn'
      // 'quotes': ['off', 'single'],
    },
    // env: { // deprecated settings
    //   "chrome": true,
    // },
  },
]);
