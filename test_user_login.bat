curl -X POST http://localhost:8000/api/user/auth/login ^
  -H "Content-Type: application/json" ^
  -d "{\"email\":\"manager@testcompany.com\",\"password\":\"Manager123!\"}"