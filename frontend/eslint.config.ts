import js from "@eslint/js";
import tsPlugin from "@typescript-eslint/eslint-plugin";
import tsParser from "@typescript-eslint/parser";
import importPlugin from "eslint-plugin-import";
import prettierRecommended from "eslint-plugin-prettier/recommended";
import reactPlugin from "eslint-plugin-react";
import reactHooksPlugin from "eslint-plugin-react-hooks";
import globals from "globals";

export default [
	{ ignores: ["node_modules/", "packages/", "dist/"] },
	js.configs.recommended,
	...tsPlugin.configs["flat/strict"],
	...tsPlugin.configs["flat/stylistic"],
	reactPlugin.configs.flat.all,
	reactHooksPlugin.configs.flat["recommended-latest"],
	importPlugin.flatConfigs.recommended,
	importPlugin.flatConfigs.typescript,
	{
		files: ["**/*.{ts,tsx}"],
		languageOptions: {
			parser: tsParser,
			parserOptions: {
				project: "./tsconfig.json",
				tsconfigRootDir: import.meta.dirname,
			},
			globals: {
				...globals.browser,
				...globals.es2021,
			},
		},
		settings: {
			react: { version: "detect" },
		},
		rules: {
			"@typescript-eslint/ban-ts-comment": [
				"error",
				{
					"ts-expect-error": "allow-with-description",
					"ts-ignore": false,
					"ts-nocheck": false,
					"ts-check": false,
				},
			],
			"@typescript-eslint/consistent-type-imports": [
				"error",
				{ prefer: "type-imports" },
			],
			"@typescript-eslint/strict-boolean-expressions": [
				"error",
				{
					allowString: false,
					allowNumber: false,
					allowNullableObject: false,
				},
			],
			"@typescript-eslint/consistent-type-definitions": ["error", "type"],
			"@typescript-eslint/no-invalid-void-type": "off",
			"react/jsx-uses-react": "off",
			"react/react-in-jsx-scope": "off",
			"react/function-component-definition": [
				"error",
				{
					namedComponents: "arrow-function",
					unnamedComponents: "arrow-function",
				},
			],
			"react/jsx-filename-extension": ["error", { extensions: [".tsx"] }],
			"react/jsx-max-depth": ["warn", { max: 4 }],
			"react/jsx-sort-props": "off",
			"react/require-default-props": "off",
			"react/jsx-no-bind": ["error", { allowArrowFunctions: true }],
			"react/jsx-props-no-spreading": "off",
			"react/jsx-no-useless-fragment": ["error", { allowExpressions: true }],
			// jsx-no-literals requires wrapping text in {expr}; the react/all preset sets
			// jsx-curly-brace-presence to "never" which conflicts. Override to "ignore"
			// for children so jsx-no-literals handles the children case.
			"react/jsx-curly-brace-presence": [
				"error",
				{ props: "never", children: "ignore" },
			],
			"import/order": [
				"error",
				{
					groups: [
						"builtin",
						"external",
						"internal",
						"parent",
						"sibling",
						"index",
						"object",
						"type",
						"unknown",
					],
					pathGroups: [
						{
							pattern: "{react,react-dom/**,react-router-dom}",
							group: "builtin",
							position: "before",
						},
					],
					pathGroupsExcludedImportTypes: ["builtin", "object"],
					alphabetize: { order: "asc" },
					"newlines-between": "always",
				},
			],
		},
	},
	prettierRecommended,
];
