# SafeVoice Authentication API Documentation

## Overview

The SafeVoice system provides two separate authentication systems:

1. **Admin Authentication** - For system administrators and company admins
2. **User Authentication** - For company branch users (managers, investigators, regular users)

## Base URL

```
http://localhost:8000/api
```

## Authentication Types

### Admin Users

-   `super_admin` - System super administrator
-   `admin` - Company administrator

### Branch Users

-   `branch_manager` - Branch manager
-   `department_head` - Department head
-   `investigator` - Case investigator
-   `user` - Regular company user
-   `viewer` - Read-only user

## API Endpoints

### 1. Health Check

**GET** `/health`

**Response:**

```json
{
    "status": "OK",
    "timestamp": "2025-10-14T18:45:00.000Z",
    "version": "1.0.0"
}
```

### 2. Admin Authentication

#### Admin Login

**POST** `/admin/auth/login`

**Request Body:**

```json
{
    "email": "admin@safevoice.com",
    "password": "Admin123!"
}
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "Admin logged in successfully",
    "data": {
        "user": {
            "id": "01k7j0p3g10wyf9sr2fd6gn6f2",
            "name": "System Administrator",
            "email": "admin@safevoice.com",
            "role": "admin",
            "permissions": null,
            "company_id": "01k7j0p1h2chgsd4sqr9y0x58c"
        },
        "token": "1|abc123...",
        "token_type": "Bearer",
        "expires_in": null
    }
}
```

**Error Response (401):**

```json
{
    "success": false,
    "message": "Invalid admin credentials"
}
```

#### Admin Logout

**POST** `/admin/auth/logout`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Admin logged out successfully"
}
```

#### Get Admin Profile

**GET** `/admin/auth/me`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": "01k7j0p3g10wyf9sr2fd6gn6f2",
            "name": "System Administrator",
            "email": "admin@safevoice.com",
            "role": "admin",
            "permissions": null,
            "company_id": "01k7j0p1h2chgsd4sqr9y0x58c",
            "last_login_at": "2025-10-14T18:45:03.000Z",
            "is_super_admin": false,
            "company": {
                "id": "01k7j0p1h2chgsd4sqr9y0x58c",
                "name": "SafeVoice Admin"
            }
        }
    }
}
```

#### Refresh Admin Token

**POST** `/admin/auth/refresh`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "2|def456...",
        "token_type": "Bearer",
        "expires_in": null
    }
}
```

### 3. User Authentication

#### User Login

**POST** `/user/auth/login`

**Request Body:**

```json
{
    "email": "manager@testcompany.com",
    "password": "Manager123!",
    "company_id": "01k7j0p3g10wyf9sr2fd6gn6f2" // Optional company verification
}
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "User logged in successfully",
    "data": {
        "user": {
            "id": "01k7j0p5h3chgsd4sqr9y0x58c",
            "name": "Branch Manager",
            "email": "manager@testcompany.com",
            "role": "branch_manager",
            "employee_id": "BM001",
            "phone": "+1555123456",
            "company_id": "01k7j0p3g10wyf9sr2fd6gn6f2",
            "branch_id": null,
            "department_id": null,
            "permissions": null,
            "company": {
                "id": "01k7j0p3g10wyf9sr2fd6gn6f2",
                "name": "Test Company Inc"
            },
            "branch": null,
            "department": null
        },
        "token": "3|ghi789...",
        "token_type": "Bearer",
        "expires_in": null
    }
}
```

#### User Logout

**POST** `/user/auth/logout`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "message": "User logged out successfully"
}
```

#### Get User Profile

**GET** `/user/auth/me`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "data": {
        "user": {
            "id": "01k7j0p5h3chgsd4sqr9y0x58c",
            "name": "Branch Manager",
            "email": "manager@testcompany.com",
            "role": "branch_manager",
            "employee_id": "BM001",
            "phone": "+1555123456",
            "company_id": "01k7j0p3g10wyf9sr2fd6gn6f2",
            "branch_id": null,
            "department_id": null,
            "permissions": null,
            "last_login_at": "2025-10-14T18:46:15.000Z",
            "is_branch_manager": true,
            "force_password_change": false,
            "company": {
                "id": "01k7j0p3g10wyf9sr2fd6gn6f2",
                "name": "Test Company Inc",
                "plan": "premium"
            },
            "branch": null,
            "department": null
        }
    }
}
```

#### Change Password

**POST** `/user/auth/change-password`

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "current_password": "Manager123!",
    "new_password": "NewPassword123!",
    "new_password_confirmation": "NewPassword123!"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Password changed successfully"
}
```

#### Update Profile

**PUT** `/user/auth/profile`

**Headers:**

```
Authorization: Bearer {token}
```

**Request Body:**

```json
{
    "name": "Updated Name",
    "phone": "+1555999888"
}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Profile updated successfully",
    "data": {
        "user": {
            "id": "01k7j0p5h3chgsd4sqr9y0x58c",
            "name": "Updated Name",
            "email": "manager@testcompany.com",
            "phone": "+1555999888"
        }
    }
}
```

#### Refresh User Token

**POST** `/user/auth/refresh`

**Headers:**

```
Authorization: Bearer {token}
```

**Response (200):**

```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "data": {
        "token": "4|jkl012...",
        "token_type": "Bearer",
        "expires_in": null
    }
}
```

## Default Test Users

### Admin Users

1. **Super Admin**

    - Email: `superadmin@safevoice.com`
    - Password: `SuperAdmin123!`
    - Role: `super_admin`

2. **Company Admin**
    - Email: `admin@safevoice.com`
    - Password: `Admin123!`
    - Role: `admin`

### Branch Users

1. **Branch Manager**

    - Email: `manager@testcompany.com`
    - Password: `Manager123!`
    - Role: `branch_manager`
    - Employee ID: `BM001`

2. **Regular User**

    - Email: `user@testcompany.com`
    - Password: `User123!`
    - Role: `user`
    - Employee ID: `USR001`

3. **Investigator**
    - Email: `investigator@testcompany.com`
    - Password: `Investigator123!`
    - Role: `investigator`
    - Employee ID: `INV001`

## Security Features

### Account Protection

-   **Account Locking**: After 5 failed login attempts, accounts are locked for 30 minutes
-   **Status Verification**: Only active and verified accounts can login
-   **Role-based Access**: Separate authentication systems prevent cross-access

### Token Management

-   **Laravel Sanctum**: Secure token-based authentication
-   **Token Abilities**: Role-specific abilities assigned to tokens
-   **Token Refresh**: Secure token renewal without re-authentication

### User Abilities by Role

#### Admin Token Abilities

-   `admin` - General admin access

#### Branch User Token Abilities

-   `user` - Basic user access
-   **Branch Manager**: `branch-manager`, `manage-branch-users`, `view-all-cases`
-   **Department Head**: `department-head`, `manage-department-users`, `view-department-cases`
-   **Investigator**: `investigator`, `manage-assigned-cases`, `create-reports`
-   **Regular User**: `create-cases`, `view-own-cases`
-   **Viewer**: `view-only`

## Error Responses

### Common Error Codes

-   **401 Unauthorized**: Invalid credentials or missing token
-   **403 Forbidden**: Access denied or account not active
-   **422 Validation Error**: Invalid request data
-   **423 Locked**: Account temporarily locked

### Example Error Response

```json
{
    "success": false,
    "message": "Account is temporarily locked due to multiple failed login attempts"
}
```

## Getting Started

1. **Start the server:**

    ```bash
    php artisan serve
    ```

2. **Seed test users:**

    ```bash
    php artisan db:seed --class=AdminSeeder
    ```

3. **Test admin login:**

    ```bash
    curl -X POST http://localhost:8000/api/admin/auth/login \
      -H "Content-Type: application/json" \
      -d '{"email":"admin@safevoice.com","password":"Admin123!"}'
    ```

4. **Test user login:**
    ```bash
    curl -X POST http://localhost:8000/api/user/auth/login \
      -H "Content-Type: application/json" \
      -d '{"email":"manager@testcompany.com","password":"Manager123!"}'
    ```

The authentication system is now fully functional with comprehensive security features and role-based access control!
