curl -X POST http://localhost:8000/api/admin/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"admin@safevoice.com\",\"password\":\"Admin123!\"}"