# Code Style & Linting Standards

Diese Standards definieren einheitliche Code Style Konfigurationen fuer alle Projekte.

---

## Universelle Prinzipien (alle Stacks)

Unabhaengig vom Tech-Stack gelten folgende Grundregeln:

1. **Automatisierte Formatierung** - Kein manuelles Formatieren. Tools erzwingen Konsistenz.
2. **Linting on Save** - Editor/IDE soll beim Speichern automatisch linten.
3. **Linting in CI** - Jeder Push wird automatisch gelintet (siehe `ci-cd-pipelines.md`).
4. **Pre-Commit Hooks** - Linting vor jedem Commit (optional, empfohlen).
5. **EditorConfig** - `.editorconfig` fuer grundlegendes Formatting (Tabs/Spaces, Encoding, Line Endings).
6. **Konsistenz vor Perfektion** - Wichtiger als die "beste" Regel ist, dass ALLE Dateien gleich formatiert sind.
7. **Keine Warnings ignorieren** - Warnings werden gefixt oder die Regel wird deaktiviert, aber nicht ignoriert.

### .editorconfig (alle Projekte)

```ini
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
trim_trailing_whitespace = true
indent_style = space
indent_size = 2

[*.{py,php,java,go,rs}]
indent_size = 4

[Makefile]
indent_style = tab
```

> **Hinweis:** Die folgenden Sektionen sind **stack-spezifisch**. Nur die Sektion lesen/anwenden die zum eigenen Stack passt (siehe `techstack.md`).

---

## 1. TypeScript/JavaScript (ESLint + Prettier)

### Installation

```bash
npm install -D eslint prettier eslint-config-prettier eslint-plugin-prettier \
  @typescript-eslint/eslint-plugin @typescript-eslint/parser \
  eslint-plugin-import eslint-plugin-react eslint-plugin-react-hooks
```

### .eslintrc.js

```javascript
// .eslintrc.js
module.exports = {
  root: true,
  parser: '@typescript-eslint/parser',
  parserOptions: {
    ecmaVersion: 2022,
    sourceType: 'module',
    ecmaFeatures: {
      jsx: true,
    },
    project: './tsconfig.json',
  },
  env: {
    browser: true,
    node: true,
    es2022: true,
  },
  plugins: [
    '@typescript-eslint',
    'import',
    'react',
    'react-hooks',
    'prettier',
  ],
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:@typescript-eslint/recommended-requiring-type-checking',
    'plugin:react/recommended',
    'plugin:react-hooks/recommended',
    'plugin:import/errors',
    'plugin:import/warnings',
    'plugin:import/typescript',
    'prettier',
  ],
  rules: {
    // TypeScript
    '@typescript-eslint/explicit-function-return-type': 'off',
    '@typescript-eslint/explicit-module-boundary-types': 'off',
    '@typescript-eslint/no-explicit-any': 'warn',
    '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
    '@typescript-eslint/no-non-null-assertion': 'warn',
    '@typescript-eslint/prefer-nullish-coalescing': 'error',
    '@typescript-eslint/prefer-optional-chain': 'error',

    // Import
    'import/order': [
      'error',
      {
        groups: [
          'builtin',
          'external',
          'internal',
          'parent',
          'sibling',
          'index',
        ],
        'newlines-between': 'always',
        alphabetize: { order: 'asc', caseInsensitive: true },
      },
    ],
    'import/no-duplicates': 'error',
    'import/no-unresolved': 'error',

    // React
    'react/react-in-jsx-scope': 'off',
    'react/prop-types': 'off',
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'warn',

    // General
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'prefer-const': 'error',
    'no-var': 'error',
    'eqeqeq': ['error', 'always'],

    // Prettier
    'prettier/prettier': 'error',
  },
  settings: {
    react: {
      version: 'detect',
    },
    'import/resolver': {
      typescript: {
        alwaysTryTypes: true,
      },
    },
  },
  ignorePatterns: [
    'node_modules/',
    'dist/',
    'build/',
    '.next/',
    'coverage/',
    '*.config.js',
  ],
};
```

### .prettierrc

```json
{
  "semi": true,
  "singleQuote": true,
  "tabWidth": 2,
  "trailingComma": "es5",
  "printWidth": 100,
  "bracketSpacing": true,
  "arrowParens": "avoid",
  "endOfLine": "lf",
  "plugins": ["prettier-plugin-tailwindcss"]
}
```

### .prettierignore

```
node_modules/
dist/
build/
.next/
coverage/
*.min.js
pnpm-lock.yaml
package-lock.json
```

### TypeScript Config (tsconfig.json)

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "lib": ["dom", "dom.iterable", "ES2022"],
    "allowJs": true,
    "skipLibCheck": true,
    "strict": true,
    "noEmit": true,
    "esModuleInterop": true,
    "module": "esnext",
    "moduleResolution": "bundler",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "jsx": "preserve",
    "incremental": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["./src/*"]
    },
    "forceConsistentCasingInFileNames": true,
    "noUncheckedIndexedAccess": true,
    "noImplicitReturns": true,
    "noFallthroughCasesInSwitch": true
  },
  "include": ["next-env.d.ts", "**/*.ts", "**/*.tsx"],
  "exclude": ["node_modules"]
}
```

## 2. Python (Ruff)

### Installation

```bash
pip install ruff
```

### pyproject.toml

```toml
[tool.ruff]
# Same as Black
line-length = 100
indent-width = 4

# Target Python 3.12+
target-version = "py311"

# Exclude common directories
exclude = [
    ".bzr",
    ".direnv",
    ".eggs",
    ".git",
    ".hg",
    ".mypy_cache",
    ".nox",
    ".pants.d",
    ".pytype",
    ".ruff_cache",
    ".svn",
    ".tox",
    ".venv",
    "__pypackages__",
    "_build",
    "buck-out",
    "build",
    "dist",
    "node_modules",
    "venv",
    "migrations",
]

[tool.ruff.lint]
# Enable
select = [
    "E",      # pycodestyle errors
    "W",      # pycodestyle warnings
    "F",      # Pyflakes
    "I",      # isort
    "B",      # flake8-bugbear
    "C4",     # flake8-comprehensions
    "UP",     # pyupgrade
    "ARG",    # flake8-unused-arguments
    "SIM",    # flake8-simplify
    "TCH",    # flake8-type-checking
    "PTH",    # flake8-use-pathlib
    "ERA",    # eradicate (commented-out code)
    "PL",     # pylint
    "RUF",    # Ruff-specific rules
]

# Ignore
ignore = [
    "E501",   # line too long (handled by formatter)
    "B008",   # do not perform function calls in argument defaults
    "PLR0913", # too many arguments
    "PLR2004", # magic value comparison
]

# Allow fix for all enabled rules (when `--fix` is provided)
fixable = ["ALL"]
unfixable = []

[tool.ruff.lint.per-file-ignores]
"__init__.py" = ["F401"]  # unused imports in __init__
"tests/**/*" = ["PLR2004", "ARG"]  # magic values and unused args OK in tests

[tool.ruff.lint.isort]
known-first-party = ["app"]
force-single-line = false
lines-after-imports = 2

[tool.ruff.format]
# Use double quotes for strings
quote-style = "double"

# Indent with spaces
indent-style = "space"

# Respect magic trailing commas
skip-magic-trailing-comma = false

# Auto-detect line endings
line-ending = "auto"
```

### Alternative: Black + Pylint

Falls Ruff nicht geeignet ist:

```toml
# pyproject.toml
[tool.black]
line-length = 100
target-version = ['py311']
include = '\.pyi?$'
exclude = '''
/(
    \.eggs
  | \.git
  | \.hg
  | \.mypy_cache
  | \.tox
  | \.venv
  | _build
  | buck-out
  | build
  | dist
  | migrations
)/
'''

[tool.pylint.main]
py-version = "3.11"
jobs = 0  # Auto-detect

[tool.pylint.messages_control]
disable = [
    "missing-docstring",
    "too-few-public-methods",
    "line-too-long",  # handled by black
]

[tool.pylint.format]
max-line-length = 100
```

## 3. Editor Configuration (.editorconfig)

```ini
# .editorconfig
root = true

[*]
charset = utf-8
end_of_line = lf
indent_style = space
indent_size = 2
insert_final_newline = true
trim_trailing_whitespace = true

[*.md]
trim_trailing_whitespace = false

[*.py]
indent_size = 4

[Makefile]
indent_style = tab
```

## 4. VS Code Settings

### .vscode/settings.json

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": "explicit",
    "source.organizeImports": "never"
  },

  "[typescript]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },
  "[typescriptreact]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },
  "[javascript]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },
  "[json]": {
    "editor.defaultFormatter": "esbenp.prettier-vscode"
  },

  "[python]": {
    "editor.defaultFormatter": "charliermarsh.ruff",
    "editor.codeActionsOnSave": {
      "source.fixAll.ruff": "explicit",
      "source.organizeImports.ruff": "explicit"
    }
  },

  "typescript.preferences.importModuleSpecifier": "relative",
  "typescript.suggest.autoImports": true,
  "eslint.validate": ["javascript", "javascriptreact", "typescript", "typescriptreact"],

  "files.associations": {
    "*.css": "tailwindcss"
  }
}
```

### .vscode/extensions.json

```json
{
  "recommendations": [
    "esbenp.prettier-vscode",
    "dbaeumer.vscode-eslint",
    "charliermarsh.ruff",
    "bradlc.vscode-tailwindcss",
    "editorconfig.editorconfig"
  ]
}
```

## 5. Git Hooks (Husky + lint-staged)

### Installation

```bash
npm install -D husky lint-staged
npx husky init
```

### package.json

```json
{
  "scripts": {
    "lint": "eslint . --ext .ts,.tsx",
    "lint:fix": "eslint . --ext .ts,.tsx --fix",
    "format": "prettier --write .",
    "format:check": "prettier --check .",
    "typecheck": "tsc --noEmit",
    "prepare": "husky"
  },
  "lint-staged": {
    "*.{ts,tsx}": [
      "eslint --fix",
      "prettier --write"
    ],
    "*.{json,md,yml,yaml}": [
      "prettier --write"
    ],
    "*.py": [
      "ruff check --fix",
      "ruff format"
    ]
  }
}
```

### .husky/pre-commit

```bash
#!/usr/bin/env sh
. "$(dirname -- "$0")/_/husky.sh"

npx lint-staged
```

## 6. Python Pre-commit Hooks

### Installation

```bash
pip install pre-commit
```

### .pre-commit-config.yaml

```yaml
repos:
  - repo: https://github.com/astral-sh/ruff-pre-commit
    rev: v0.7.0
    hooks:
      - id: ruff
        args: [--fix]
      - id: ruff-format

  - repo: https://github.com/pre-commit/pre-commit-hooks
    rev: v4.5.0
    hooks:
      - id: trailing-whitespace
      - id: end-of-file-fixer
      - id: check-yaml
      - id: check-json
      - id: check-added-large-files
      - id: check-merge-conflict
      - id: detect-private-key

  - repo: https://github.com/pre-commit/mirrors-mypy
    rev: v1.8.0
    hooks:
      - id: mypy
        additional_dependencies:
          - pydantic
          - types-requests
```

```bash
# Initialize
pre-commit install
pre-commit run --all-files
```

## 7. NPM Scripts Summary

### package.json (Complete)

```json
{
  "scripts": {
    "dev": "next dev",
    "build": "next build",
    "start": "next start",
    "lint": "eslint . --ext .ts,.tsx",
    "lint:fix": "eslint . --ext .ts,.tsx --fix",
    "format": "prettier --write .",
    "format:check": "prettier --check .",
    "typecheck": "tsc --noEmit",
    "test": "vitest",
    "test:coverage": "vitest --coverage",
    "audit": "npm audit --audit-level=moderate",
    "prepare": "husky"
  }
}
```

### Python Scripts (Makefile)

```makefile
.PHONY: lint format test

lint:
	ruff check .
	ruff format --check .

lint-fix:
	ruff check --fix .
	ruff format .

typecheck:
	mypy app/

test:
	pytest

test-coverage:
	pytest --cov=app --cov-report=html

audit:
	pip-audit

all: lint typecheck test
```

## 8. Quality Gates

### Minimum Standards für Merge

| Check | Required | Tool |
|-------|----------|------|
| No ESLint Errors | Yes | `npm run lint` |
| No TypeScript Errors | Yes | `npm run typecheck` |
| Prettier Formatting | Yes | `npm run format:check` |
| No Ruff Errors (Python) | Yes | `ruff check` |
| Tests Pass | Yes | `npm run test` |
| No High Vulnerabilities | Yes | `npm audit` |

### CI Integration

Siehe `ci-cd-pipelines.md` für vollständige CI/CD Konfiguration.

---

**Letzte Aktualisierung**: 2025-12
**Owner**: Development Team
