# FastAPI Patterns

Best-Practice Patterns für FastAPI Backend-Entwicklung.

> **Referenz:** Ergänzt `.claude/standards/api-design.md` mit FastAPI-spezifischen Patterns.

---

## 1. Projekt-Struktur

### 1.1 Kleine Projekte (MVP)

```
backend/
├── app/
│   ├── __init__.py
│   ├── main.py           # FastAPI App, Router-Includes
│   ├── config.py         # Settings via Pydantic
│   ├── database.py       # DB Connection
│   ├── models.py         # SQLAlchemy/Pydantic Models
│   ├── schemas.py        # Pydantic Request/Response Schemas
│   ├── crud.py           # Database Operations
│   └── routers/
│       ├── __init__.py
│       ├── users.py
│       └── items.py
├── tests/
│   ├── __init__.py
│   ├── conftest.py       # Fixtures
│   └── test_users.py
├── pyproject.toml
└── Dockerfile
```

### 1.2 Große Projekte

```
backend/
├── app/
│   ├── core/
│   │   ├── config.py     # Settings
│   │   ├── security.py   # Auth Logic
│   │   └── database.py   # DB Setup
│   ├── api/
│   │   ├── v1/
│   │   │   ├── endpoints/
│   │   │   └── router.py
│   │   └── deps.py       # Dependencies
│   ├── models/           # SQLAlchemy Models
│   ├── schemas/          # Pydantic Schemas
│   ├── services/         # Business Logic
│   └── main.py
```

---

## 2. Pydantic Patterns

### 2.1 Base Schema Pattern

```python
from pydantic import BaseModel, ConfigDict
from datetime import datetime

class BaseSchema(BaseModel):
    model_config = ConfigDict(from_attributes=True)

class UserBase(BaseSchema):
    email: str
    name: str

class UserCreate(UserBase):
    password: str

class UserResponse(UserBase):
    id: int
    created_at: datetime

class UserInDB(UserResponse):
    hashed_password: str
```

### 2.2 Field Validation

```python
from pydantic import BaseModel, Field, field_validator

class ItemCreate(BaseModel):
    name: str = Field(..., min_length=1, max_length=100)
    price: float = Field(..., gt=0)
    quantity: int = Field(default=1, ge=0)

    @field_validator('name')
    @classmethod
    def name_must_not_be_empty(cls, v: str) -> str:
        if not v.strip():
            raise ValueError('Name cannot be empty')
        return v.strip()
```

---

## 3. Router Patterns

### 3.1 Basic Router

```python
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.orm import Session

from app.database import get_db
from app.schemas import UserCreate, UserResponse
from app import crud

router = APIRouter(
    prefix="/users",
    tags=["users"],
)

@router.post("/", response_model=UserResponse, status_code=status.HTTP_201_CREATED)
def create_user(user: UserCreate, db: Session = Depends(get_db)):
    db_user = crud.get_user_by_email(db, email=user.email)
    if db_user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Email already registered"
        )
    return crud.create_user(db=db, user=user)

@router.get("/{user_id}", response_model=UserResponse)
def get_user(user_id: int, db: Session = Depends(get_db)):
    user = crud.get_user(db, user_id=user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found"
        )
    return user
```

### 3.2 Router mit Dependencies

```python
from fastapi import APIRouter, Depends
from app.api.deps import get_current_user, get_db

router = APIRouter(
    prefix="/protected",
    tags=["protected"],
    dependencies=[Depends(get_current_user)],  # Alle Endpoints geschützt
)
```

---

## 4. Dependency Injection

### 4.1 Database Session

```python
from typing import Generator
from sqlalchemy.orm import Session
from app.core.database import SessionLocal

def get_db() -> Generator[Session, None, None]:
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()
```

### 4.2 Current User

```python
from fastapi import Depends, HTTPException, status
from fastapi.security import OAuth2PasswordBearer

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="auth/token")

async def get_current_user(
    token: str = Depends(oauth2_scheme),
    db: Session = Depends(get_db)
) -> User:
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    try:
        payload = decode_token(token)
        user_id: int = payload.get("sub")
        if user_id is None:
            raise credentials_exception
    except JWTError:
        raise credentials_exception

    user = crud.get_user(db, user_id=user_id)
    if user is None:
        raise credentials_exception
    return user
```

---

## 5. Error Handling

### 5.1 Custom Exception Handler

```python
from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse

class AppException(Exception):
    def __init__(self, status_code: int, detail: str):
        self.status_code = status_code
        self.detail = detail

app = FastAPI()

@app.exception_handler(AppException)
async def app_exception_handler(request: Request, exc: AppException):
    return JSONResponse(
        status_code=exc.status_code,
        content={"detail": exc.detail, "type": "app_error"}
    )
```

### 5.2 Standardisierte Error Response

```python
from pydantic import BaseModel

class ErrorResponse(BaseModel):
    detail: str
    code: str | None = None

# In Router
@router.get(
    "/{id}",
    responses={
        404: {"model": ErrorResponse, "description": "Not found"},
        400: {"model": ErrorResponse, "description": "Bad request"},
    }
)
```

---

## 6. Testing

### 6.1 Test Client Setup

```python
# tests/conftest.py
import pytest
from fastapi.testclient import TestClient
from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker

from app.main import app
from app.database import get_db, Base

SQLALCHEMY_DATABASE_URL = "sqlite:///./test.db"
engine = create_engine(SQLALCHEMY_DATABASE_URL)
TestingSessionLocal = sessionmaker(bind=engine)

@pytest.fixture(scope="function")
def db():
    Base.metadata.create_all(bind=engine)
    db = TestingSessionLocal()
    try:
        yield db
    finally:
        db.close()
        Base.metadata.drop_all(bind=engine)

@pytest.fixture(scope="function")
def client(db):
    def override_get_db():
        yield db

    app.dependency_overrides[get_db] = override_get_db
    with TestClient(app) as c:
        yield c
    app.dependency_overrides.clear()
```

### 6.2 Test Examples

```python
def test_create_user(client):
    response = client.post(
        "/users/",
        json={"email": "test@example.com", "name": "Test", "password": "secret"}
    )
    assert response.status_code == 201
    data = response.json()
    assert data["email"] == "test@example.com"
    assert "id" in data

def test_get_nonexistent_user(client):
    response = client.get("/users/999")
    assert response.status_code == 404
```

---

## 7. Performance Patterns

### 7.1 Async Database

```python
from sqlalchemy.ext.asyncio import create_async_engine, AsyncSession
from sqlalchemy.orm import sessionmaker

engine = create_async_engine(DATABASE_URL, echo=True)
async_session = sessionmaker(engine, class_=AsyncSession, expire_on_commit=False)

async def get_async_db():
    async with async_session() as session:
        yield session
```

### 7.2 Background Tasks

```python
from fastapi import BackgroundTasks

def send_email(email: str, message: str):
    # Email senden (langsame Operation)
    pass

@router.post("/notify")
async def notify_user(
    email: str,
    background_tasks: BackgroundTasks
):
    background_tasks.add_task(send_email, email, "Hello!")
    return {"message": "Notification queued"}
```

---

## 8. Security Patterns

**Referenz:** `.claude/standards/security.md`

### 8.1 CORS

```python
from fastapi.middleware.cors import CORSMiddleware

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS,  # Explizite Liste!
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE"],
    allow_headers=["*"],
)
```

### 8.2 Rate Limiting

```python
from slowapi import Limiter
from slowapi.util import get_remote_address

limiter = Limiter(key_func=get_remote_address)
app.state.limiter = limiter

@router.post("/login")
@limiter.limit("5/minute")
async def login(request: Request):
    pass
```

---

## Referenzen

- `.claude/standards/api-design.md` - REST API Patterns
- `.claude/standards/error-handling-logging.md` - Logging Setup
- `.claude/standards/security.md` - Security Best Practices
- `techstack.md` - Tech Stack Spezifikation
