# Testing Patterns

Best-Practice Patterns für Testing in JavaScript/TypeScript und Python Projekten.

> **Referenz:** Ergänzt die Testing-Strategie aus `dev.md`.

---

## 1. Testing Pyramide

```
        ╱╲
       ╱  ╲         E2E Tests (10%)
      ╱────╲        - Kritische User Journeys
     ╱      ╲       - Playwright
    ╱────────╲
   ╱          ╲     Integration Tests (20%)
  ╱────────────╲    - API Endpoints
 ╱              ╲   - Database Operations
╱────────────────╲
                    Unit Tests (70%)
                    - Pure Functions
                    - Business Logic
                    - Components (isoliert)
```

**MVP-Fokus:** Für MVP nur Unit Tests (gemäß `dev.md`).

---

## 2. JavaScript/TypeScript Testing

### 2.1 Jest/Vitest Setup

```typescript
// vitest.config.ts
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      exclude: ['node_modules/', 'tests/'],
    },
  },
});
```

### 2.2 Unit Test Pattern

```typescript
// utils/formatDate.test.ts
import { describe, it, expect } from 'vitest';
import { formatDate } from './formatDate';

describe('formatDate', () => {
  it('should format date in German locale', () => {
    const date = new Date('2024-01-15');
    expect(formatDate(date)).toBe('15.01.2024');
  });

  it('should handle invalid date', () => {
    expect(formatDate(null)).toBe('');
  });

  it.each([
    [new Date('2024-01-01'), '01.01.2024'],
    [new Date('2024-12-31'), '31.12.2024'],
  ])('should format %s as %s', (input, expected) => {
    expect(formatDate(input)).toBe(expected);
  });
});
```

### 2.3 React Component Testing

```typescript
// components/Button.test.tsx
import { render, screen, fireEvent } from '@testing-library/react';
import { Button } from './Button';

describe('Button', () => {
  it('should render with text', () => {
    render(<Button>Click me</Button>);
    expect(screen.getByRole('button')).toHaveTextContent('Click me');
  });

  it('should call onClick when clicked', () => {
    const handleClick = vi.fn();
    render(<Button onClick={handleClick}>Click</Button>);

    fireEvent.click(screen.getByRole('button'));

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('should be disabled when loading', () => {
    render(<Button loading>Submit</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
  });
});
```

### 2.4 Hook Testing

```typescript
// hooks/useCounter.test.ts
import { renderHook, act } from '@testing-library/react';
import { useCounter } from './useCounter';

describe('useCounter', () => {
  it('should initialize with default value', () => {
    const { result } = renderHook(() => useCounter());
    expect(result.current.count).toBe(0);
  });

  it('should increment count', () => {
    const { result } = renderHook(() => useCounter());

    act(() => {
      result.current.increment();
    });

    expect(result.current.count).toBe(1);
  });

  it('should use initial value', () => {
    const { result } = renderHook(() => useCounter(10));
    expect(result.current.count).toBe(10);
  });
});
```

---

## 3. Python Testing (pytest)

### 3.1 pytest Setup

```ini
# pytest.ini
[pytest]
testpaths = tests
python_files = test_*.py
python_functions = test_*
addopts = -v --cov=app --cov-report=term-missing
```

```python
# conftest.py
import pytest
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

@pytest.fixture(scope="function")
def db_session():
    engine = create_engine("sqlite:///:memory:")
    Session = sessionmaker(bind=engine)
    session = Session()
    yield session
    session.close()

@pytest.fixture
def sample_user():
    return {"email": "test@example.com", "name": "Test User"}
```

### 3.2 Unit Test Pattern

```python
# tests/test_utils.py
import pytest
from app.utils import format_currency, validate_email

class TestFormatCurrency:
    def test_formats_positive_amount(self):
        assert format_currency(1234.56) == "1.234,56 €"

    def test_formats_zero(self):
        assert format_currency(0) == "0,00 €"

    def test_formats_negative_amount(self):
        assert format_currency(-100) == "-100,00 €"

    @pytest.mark.parametrize("amount,expected", [
        (1000, "1.000,00 €"),
        (1000000, "1.000.000,00 €"),
        (0.01, "0,01 €"),
    ])
    def test_various_amounts(self, amount, expected):
        assert format_currency(amount) == expected


class TestValidateEmail:
    def test_valid_email(self):
        assert validate_email("user@example.com") is True

    def test_invalid_email(self):
        assert validate_email("not-an-email") is False

    def test_empty_email(self):
        assert validate_email("") is False
```

### 3.3 FastAPI Endpoint Testing

```python
# tests/test_api.py
import pytest
from fastapi.testclient import TestClient
from app.main import app

@pytest.fixture
def client():
    return TestClient(app)

class TestUsersAPI:
    def test_create_user(self, client):
        response = client.post(
            "/api/users",
            json={"email": "new@example.com", "name": "New User"}
        )
        assert response.status_code == 201
        data = response.json()
        assert data["email"] == "new@example.com"
        assert "id" in data

    def test_create_user_invalid_email(self, client):
        response = client.post(
            "/api/users",
            json={"email": "invalid", "name": "Test"}
        )
        assert response.status_code == 422

    def test_get_user_not_found(self, client):
        response = client.get("/api/users/999")
        assert response.status_code == 404
```

---

## 4. Mock Patterns

### 4.1 JavaScript/TypeScript Mocking

```typescript
// services/userService.test.ts
import { vi, describe, it, expect, beforeEach } from 'vitest';
import { UserService } from './userService';
import { api } from '../lib/api';

// Mock das API-Modul
vi.mock('../lib/api');

describe('UserService', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('should fetch user by id', async () => {
    const mockUser = { id: 1, name: 'Test' };
    vi.mocked(api.get).mockResolvedValue({ data: mockUser });

    const user = await UserService.getById(1);

    expect(api.get).toHaveBeenCalledWith('/users/1');
    expect(user).toEqual(mockUser);
  });

  it('should throw on API error', async () => {
    vi.mocked(api.get).mockRejectedValue(new Error('Network error'));

    await expect(UserService.getById(1)).rejects.toThrow('Network error');
  });
});
```

### 4.2 Python Mocking

```python
# tests/test_services.py
from unittest.mock import Mock, patch
import pytest
from app.services import EmailService

class TestEmailService:
    @patch('app.services.smtp_client')
    def test_send_email(self, mock_smtp):
        service = EmailService()
        service.send("test@example.com", "Subject", "Body")

        mock_smtp.send.assert_called_once_with(
            to="test@example.com",
            subject="Subject",
            body="Body"
        )

    @patch('app.services.smtp_client')
    def test_send_email_failure(self, mock_smtp):
        mock_smtp.send.side_effect = Exception("SMTP Error")

        service = EmailService()
        with pytest.raises(Exception, match="SMTP Error"):
            service.send("test@example.com", "Subject", "Body")
```

---

## 5. Test Organisation

### 5.1 Dateistruktur

```
tests/
├── unit/                    # Unit Tests
│   ├── utils/
│   │   └── test_format.py
│   └── services/
│       └── test_user.py
├── integration/             # Integration Tests
│   └── api/
│       └── test_endpoints.py
├── e2e/                     # E2E Tests (post-MVP)
│   └── test_user_flow.py
├── fixtures/                # Test-Daten
│   └── users.json
└── conftest.py              # Shared Fixtures
```

### 5.2 Naming Conventions

| Convention | Beispiel |
|------------|----------|
| Test-Datei | `test_<module>.py` / `<module>.test.ts` |
| Test-Klasse | `Test<ClassName>` |
| Test-Funktion | `test_<behavior>` / `it('should <behavior>')` |

---

## 6. Coverage Requirements

### 6.1 Minimum Coverage (MVP)

| Bereich | Minimum |
|---------|---------|
| Gesamt | 80% |
| Business Logic | 90% |
| Utils | 95% |
| UI Components | 70% |

### 6.2 Coverage Report

```bash
# JavaScript/TypeScript
npm test -- --coverage

# Python
pytest --cov=app --cov-report=html
```

---

## 7. Test Best Practices

### 7.1 AAA Pattern

```typescript
it('should calculate total price', () => {
  // Arrange
  const items = [
    { price: 10, quantity: 2 },
    { price: 5, quantity: 3 },
  ];

  // Act
  const total = calculateTotal(items);

  // Assert
  expect(total).toBe(35);
});
```

### 7.2 Test Isolation

```typescript
describe('UserStore', () => {
  let store: UserStore;

  beforeEach(() => {
    // Frischer Store für jeden Test
    store = new UserStore();
  });

  afterEach(() => {
    // Cleanup
    store.reset();
  });
});
```

### 7.3 Descriptive Test Names

```typescript
// ❌ Schlecht
it('test1', () => { ... });
it('works', () => { ... });

// ✅ Gut
it('should return empty array when no users found', () => { ... });
it('should throw error when email is invalid', () => { ... });
```

---

## 8. CI Integration

```yaml
# .github/workflows/test.yml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v6

      - name: Setup Node
        uses: actions/setup-node@v6
        with:
          node-version: '22'

      - name: Install dependencies
        run: npm ci

      - name: Run tests
        run: npm test -- --coverage

      - name: Check coverage
        run: |
          COVERAGE=$(npm test -- --coverage --coverageReporters=text-summary | grep 'All files' | awk '{print $3}' | tr -d '%')
          if (( $(echo "$COVERAGE < 80" | bc -l) )); then
            echo "Coverage $COVERAGE% is below 80%"
            exit 1
          fi
```

---

## Referenzen

- `dev.md` - Testing-Strategie Übersicht
- `.claude/standards/ci-cd-pipelines.md` - CI Integration
- `techstack.md` - Test-Tools Spezifikation
