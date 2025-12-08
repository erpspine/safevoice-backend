# Subscription System API Usage Examples

This document shows how to use the subscription system with branch selection functionality.

## API Endpoints

### 1. Get Available Branches for Selection

```http
GET /api/admin/subscriptions/available-branches
Content-Type: application/json
Authorization: Bearer {token}

{
    "company_id": "01k7rjt9vjh4zdkv38nq4akwdj",
    "plan_id": "01k958je6mda6cqn6p5rybgndp"
}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "branches": [
            {
                "id": "01k7rjtb2vh4zdkv38nq4akwef",
                "name": "Main Office",
                "location": "Dar es Salaam",
                "full_name": "Main Office - Dar es Salaam",
                "is_currently_active": false,
                "activated_until": null
            },
            {
                "id": "01k7rjtb2vh4zdkv38nq4akweg",
                "name": "Mwanza Branch",
                "location": "Mwanza",
                "full_name": "Mwanza Branch - Mwanza",
                "is_currently_active": false,
                "activated_until": null
            }
        ],
        "max_selectable": 5,
        "plan_name": "Professional",
        "total_available": 5
    }
}
```

### 2. Create Subscription with Selected Branches

```http
POST /api/admin/subscriptions
Content-Type: application/json
Authorization: Bearer {token}

{
    "company_id": "01k7rjt9vjh4zdkv38nq4akwdj",
    "plan_id": "01k958je6mda6cqn6p5rybgndp",
    "duration_months": 12,
    "selected_branches": [
        "01k7rjtb2vh4zdkv38nq4akwef",
        "01k7rjtb2vh4zdkv38nq4akweg"
    ],
    "payment_method": "card",
    "amount_paid": 79.99,
    "payment_reference": "PAY_2025110312345",
    "auto_renew": true,
    "renewal_token": "tok_visa_4242"
}
```

**Response:**

```json
{
    "success": true,
    "message": "Subscription created successfully",
    "data": {
        "id": 1,
        "company_id": "01k7rjt9vjh4zdkv38nq4akwdj",
        "plan_id": "01k958je6mda6cqn6p5rybgndp",
        "starts_on": "2025-11-03",
        "ends_on": "2026-11-02",
        "grace_until": "2026-11-16",
        "status": "active",
        "auto_renew": true,
        "company": {
            "id": "01k7rjt9vjh4zdkv38nq4akwdj",
            "name": "SafeVoice Corp"
        },
        "plan": {
            "id": "01k958je6mda6cqn6p5rybgndp",
            "name": "Professional",
            "price": "79.99",
            "max_branches": 5
        },
        "branches": [
            {
                "id": "01k7rjtb2vh4zdkv38nq4akwef",
                "name": "Main Office",
                "location": "Dar es Salaam",
                "pivot": {
                    "activated_from": "2025-11-03",
                    "activated_until": "2026-11-02"
                }
            }
        ],
        "payments": [
            {
                "id": "01k958je6mda6cqn6p5rybgned",
                "amount_paid": "79.99",
                "payment_method": "card",
                "status": "completed"
            }
        ]
    }
}
```

### 3. Update Subscription Branches

```http
PUT /api/admin/subscriptions/{subscription_id}/branches
Content-Type: application/json
Authorization: Bearer {token}

{
    "branch_ids": [
        "01k7rjtb2vh4zdkv38nq4akwef",
        "01k7rjtb2vh4zdkv38nq4akweg",
        "01k7rjtb2vh4zdkv38nq4akweh"
    ]
}
```

### 4. Get All Subscriptions

```http
GET /api/admin/subscriptions?company_id=01k7rjt9vjh4zdkv38nq4akwdj&status=active
Authorization: Bearer {token}
```

### 5. Cancel Subscription

```http
POST /api/admin/subscriptions/{subscription_id}/cancel
Content-Type: application/json
Authorization: Bearer {token}

{
    "immediate": false
}
```

### 6. Extend Subscription

```http
POST /api/admin/subscriptions/{subscription_id}/extend
Content-Type: application/json
Authorization: Bearer {token}

{
    "additional_months": 6,
    "payment_method": "card",
    "amount_paid": 239.97,
    "payment_reference": "PAY_EXT_2025110312345"
}
```

### 7. Get Subscription Statistics

```http
GET /api/admin/subscriptions/stats
Authorization: Bearer {token}
```

**Response:**

```json
{
    "success": true,
    "data": {
        "total_subscriptions": 45,
        "active_subscriptions": 32,
        "expired_subscriptions": 8,
        "in_grace_subscriptions": 5,
        "total_revenue": "15247.85",
        "monthly_revenue": "2840.50"
    }
}
```

## Workflow Example

1. **Company Selection**: User selects a company
2. **Plan Selection**: User selects a subscription plan
3. **Branch Selection**: System calls `GET /subscriptions/available-branches` to show available branches
4. **User Selection**: User selects which branches to activate (up to plan limit)
5. **Payment**: User provides payment information
6. **Subscription Creation**: System calls `POST /subscriptions` with selected branches
7. **Activation**: System activates only the selected branches

## Key Features

-   ✅ Branch-level activation control
-   ✅ Plan limits enforcement
-   ✅ Payment tracking
-   ✅ Grace period management
-   ✅ Auto-renewal support
-   ✅ Subscription analytics
-   ✅ Branch activation history
-   ✅ Multiple payment methods support

## Database Structure

The system uses these key tables:

-   `subscriptions` - Main subscription records
-   `subscription_branch` - Pivot table linking subscriptions to branches
-   `payments` - Payment history
-   `branches` - Branch activation status

This allows for granular control over which branches are active under each subscription.
