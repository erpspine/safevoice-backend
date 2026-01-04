# Sector-Based Incident Categories API

## Overview

The Sector-Based Incident Categories API provides automatic population of incident categories when a company is created based on its industry sector. It includes hierarchical category structure with parent categories and subcategories, sector templates management, and public endpoints for reporting forms.

## Endpoints

### 1. Get Available Sectors

**URL**: `/api/public/sectors`  
**Method**: GET  
**Authentication**: Not required

**Description**: Retrieve all available industry sectors for company registration.

**Response Format**:

```json
{
    "success": true,
    "data": [
        { "key": "education", "name": "Education" },
        { "key": "corporate_workplace", "name": "Corporate Workplace" },
        { "key": "financial_insurance", "name": "Financial & Insurance" },
        { "key": "healthcare", "name": "Healthcare" },
        {
            "key": "manufacturing_industrial",
            "name": "Manufacturing & Industrial"
        },
        {
            "key": "construction_engineering",
            "name": "Construction & Engineering"
        },
        {
            "key": "security_uniformed_services",
            "name": "Security & Uniformed Services"
        },
        {
            "key": "hospitality_travel_tourism",
            "name": "Hospitality, Travel & Tourism"
        },
        { "key": "ngo_cso_donor_funded", "name": "NGO, CSO & Donor-Funded" },
        { "key": "religious_institutions", "name": "Religious Institutions" },
        { "key": "transport_logistics", "name": "Transport & Logistics" }
    ]
}
```

### 2. Create Company with Sector

**URL**: `/api/admin/companies`  
**Method**: POST  
**Authentication**: Required (Bearer token)

**Description**: Create a new company with a sector. Incident categories are automatically populated from sector templates.

**Request Body**:

```json
{
    "name": "ABC School",
    "email": "info@abcschool.com",
    "sector": "education",
    "contact": "+1234567890",
    "address": "123 Main Street",
    "website": "https://abcschool.com",
    "description": "A leading educational institution",
    "plan": "subscription_plan_ulid"
}
```

**Sector Values (enum)**:

| Value                         | Display Name                  |
| ----------------------------- | ----------------------------- |
| `education`                   | Education                     |
| `corporate_workplace`         | Corporate Workplace           |
| `financial_insurance`         | Financial & Insurance         |
| `healthcare`                  | Healthcare                    |
| `manufacturing_industrial`    | Manufacturing & Industrial    |
| `construction_engineering`    | Construction & Engineering    |
| `security_uniformed_services` | Security & Uniformed Services |
| `hospitality_travel_tourism`  | Hospitality, Travel & Tourism |
| `ngo_cso_donor_funded`        | NGO, CSO & Donor-Funded       |
| `religious_institutions`      | Religious Institutions        |
| `transport_logistics`         | Transport & Logistics         |

**Response Format** (201 Created):

```json
{
    "success": true,
    "message": "Company created successfully",
    "data": {
        "id": "01abc123def456...",
        "name": "ABC School",
        "email": "info@abcschool.com",
        "sector": "education",
        "status": true,
        "created_at": "2026-01-03T10:00:00.000000Z",
        "updated_at": "2026-01-03T10:00:00.000000Z"
    }
}
```

> **Note**: When `sector` is provided, incident categories are automatically created from pre-configured templates.

### 3. Update Company Sector

**URL**: `/api/admin/companies/{id}`  
**Method**: PUT  
**Authentication**: Required (Bearer token)

**Description**: Update a company's details including sector. Changing the sector will intelligently sync incident categories:

-   **Adds** new categories from the new sector templates
-   **Removes** (soft-delete) template-based categories that no longer exist
-   **Preserves** custom categories added by the company

**Request Body**:

```json
{
    "sector": "healthcare"
}
```

**Response Format**:

```json
{
    "success": true,
    "message": "Company updated successfully",
    "data": {
        "company": {
            "id": "01abc123def456...",
            "name": "ABC School",
            "sector": "healthcare",
            "updated_at": "2026-01-03T11:00:00.000000Z"
        },
        "category_sync": {
            "added": [
                {
                    "type": "parent",
                    "name": "Patient Safety",
                    "action": "created"
                },
                {
                    "type": "subcategory",
                    "name": "Medical errors",
                    "parent": "Patient Safety",
                    "action": "created"
                }
            ],
            "removed": [
                {
                    "type": "parent",
                    "name": "Academic Misconduct",
                    "category_key": "academic_misconduct"
                }
            ],
            "preserved": [
                {
                    "type": "parent",
                    "name": "Custom Category",
                    "reason": "custom_category"
                }
            ],
            "message": "Sync complete: 24 added, 28 removed, 2 preserved"
        }
    }
}
```

**Sync Behavior**:

| Category Type            | Template Exists            | Action           |
| ------------------------ | -------------------------- | ---------------- |
| Template-based           | Yes                        | Preserved        |
| Template-based           | No (removed from template) | Soft-deleted     |
| Custom (no category_key) | N/A                        | Always preserved |
| New in template          | Yes                        | Created          |

> **Note**: Custom categories (those without a `category_key`) are never deleted during sector changes.

### 4. Get Incident Categories by Company (Admin)

**URL**: `/api/admin/incident-categories/company/{companyId}`  
**Method**: GET  
**Authentication**: Required (Bearer token)

**Description**: Retrieve incident categories for a company in hierarchical format with parent categories and subcategories.

**Response Format**:

```json
{
    "success": true,
    "data": {
        "company": {
            "id": "01abc123def456...",
            "name": "ABC School",
            "sector": "education"
        },
        "incident_categories": [
            {
                "id": "01cat001...",
                "name": "Academic Misconduct",
                "category_key": "academic_misconduct",
                "description": "Category for Academic Misconduct",
                "status": true,
                "sort_order": 100,
                "parent_id": null,
                "subcategories": [
                    {
                        "id": "01sub001...",
                        "name": "Plagiarism",
                        "parent_id": "01cat001...",
                        "status": true,
                        "sort_order": 101
                    },
                    {
                        "id": "01sub002...",
                        "name": "Exam cheating",
                        "parent_id": "01cat001...",
                        "status": true,
                        "sort_order": 102
                    },
                    {
                        "id": "01sub003...",
                        "name": "Grade manipulation",
                        "parent_id": "01cat001...",
                        "status": true,
                        "sort_order": 103
                    },
                    {
                        "id": "01sub004...",
                        "name": "Falsification of academic records",
                        "parent_id": "01cat001...",
                        "status": true,
                        "sort_order": 104
                    }
                ]
            },
            {
                "id": "01cat002...",
                "name": "Student Welfare",
                "category_key": "student_welfare",
                "description": "Category for Student Welfare",
                "status": true,
                "sort_order": 200,
                "parent_id": null,
                "subcategories": [
                    {
                        "id": "01sub005...",
                        "name": "Bullying",
                        "parent_id": "01cat002...",
                        "status": true
                    },
                    {
                        "id": "01sub006...",
                        "name": "Cyberbullying",
                        "parent_id": "01cat002...",
                        "status": true
                    }
                ]
            }
        ]
    }
}
```

### 5. Get Incident Categories (Public)

**URL**: `/api/public/incident-categories/company/{companyId}`  
**Method**: GET  
**Authentication**: Not required

**Description**: Public endpoint for reporting forms. Returns only active categories with minimal information.

**Response Format**:

```json
{
    "success": true,
    "message": "Incident categories retrieved successfully.",
    "data": [
        {
            "id": "01cat001...",
            "name": "Academic Misconduct",
            "description": "Category for Academic Misconduct",
            "subcategories": [
                {
                    "id": "01sub001...",
                    "name": "Plagiarism",
                    "description": null
                },
                {
                    "id": "01sub002...",
                    "name": "Exam cheating",
                    "description": null
                }
            ]
        },
        {
            "id": "01cat002...",
            "name": "Student Welfare",
            "description": "Category for Student Welfare",
            "subcategories": [
                {
                    "id": "01sub005...",
                    "name": "Bullying",
                    "description": null
                },
                {
                    "id": "01sub006...",
                    "name": "Cyberbullying",
                    "description": null
                }
            ]
        }
    ]
}
```

## Sector Categories Reference

### Education

| Category                | Subcategories                                                                                                 |
| ----------------------- | ------------------------------------------------------------------------------------------------------------- |
| Academic Misconduct     | Plagiarism, Exam cheating, Grade manipulation, Falsification of academic records                              |
| Student Welfare         | Bullying, Cyberbullying, Harassment, Mental health concerns, Self-harm or suicidal ideation                   |
| Staff Misconduct        | Inappropriate relationships with students, Abuse of authority, Neglect of duty, Breach of professional ethics |
| Safeguarding            | Child protection concerns, Physical abuse, Emotional abuse, Sexual abuse, Neglect                             |
| Financial Mismanagement | Misuse of school funds, Fraudulent procurement, Bribery in admissions, Unauthorized fee collection            |
| Safety & Security       | Unsafe premises, Lack of emergency preparedness, Substance abuse on campus, Unauthorized visitors             |
| Discrimination          | Racial discrimination, Gender discrimination, Disability discrimination, Religious discrimination             |

### Corporate Workplace

| Category             | Subcategories                                                                                                         |
| -------------------- | --------------------------------------------------------------------------------------------------------------------- |
| Workplace Harassment | Sexual harassment, Verbal abuse, Bullying, Intimidation, Hostile work environment                                     |
| Discrimination       | Gender discrimination, Racial discrimination, Age discrimination, Disability discrimination, Religious discrimination |
| Financial Fraud      | Embezzlement, Expense fraud, Invoice manipulation, Payroll fraud, Asset misappropriation                              |
| Conflict of Interest | Undisclosed relationships, Outside business interests, Favoritism in hiring, Nepotism                                 |
| Data Privacy Breach  | Unauthorized data access, Data theft, GDPR violations, Customer data misuse                                           |
| Health & Safety      | Unsafe working conditions, Failure to report accidents, Violation of safety protocols, Inadequate PPE                 |
| Policy Violations    | Code of conduct breaches, IT policy violations, Attendance fraud, Substance abuse                                     |
| Retaliation          | Whistleblower retaliation, Demotion after complaint, Unfair termination, Exclusion from opportunities                 |

### Healthcare

| Category                | Subcategories                                                                                            |
| ----------------------- | -------------------------------------------------------------------------------------------------------- |
| Patient Safety          | Medical errors, Medication errors, Misdiagnosis, Surgical errors, Inadequate care                        |
| Fraud & Billing         | Insurance fraud, Overbilling, Phantom billing, Kickbacks                                                 |
| Professional Misconduct | Practicing without license, Falsification of credentials, Sexual misconduct, Boundary violations         |
| Patient Rights          | Privacy violations (HIPAA), Informed consent violations, Discrimination in care, Abuse or neglect        |
| Workplace Safety        | Exposure to hazardous materials, Inadequate infection control, Violence in workplace, Equipment failures |
| Regulatory Compliance   | License violations, Accreditation issues, Research ethics violations, Drug handling violations           |

### Manufacturing & Industrial

| Category                 | Subcategories                                                                                    |
| ------------------------ | ------------------------------------------------------------------------------------------------ |
| Safety Violations        | Equipment safety failures, Lack of PPE, Chemical exposure, Fire hazards, Electrical hazards      |
| Environmental Violations | Illegal waste disposal, Air pollution, Water contamination, Hazardous material handling          |
| Quality Control          | Product defects, Falsified testing results, Non-compliance with standards, Counterfeit materials |
| Labor Violations         | Child labor, Forced overtime, Wage theft, Unsafe working conditions                              |
| Theft & Fraud            | Inventory theft, Equipment theft, Procurement fraud, Intellectual property theft                 |
| Workplace Harassment     | Sexual harassment, Bullying, Discrimination, Retaliation                                         |

### Construction & Engineering

| Category                 | Subcategories                                                                                         |
| ------------------------ | ----------------------------------------------------------------------------------------------------- |
| Safety Violations        | Fall hazards, Scaffolding failures, Excavation dangers, Electrical hazards, Heavy equipment accidents |
| Code Violations          | Building code violations, Permit violations, Substandard materials, Structural deficiencies           |
| Contract Fraud           | Bid rigging, Overbilling, False certifications, Kickbacks                                             |
| Environmental Violations | Illegal dumping, Asbestos handling violations, Erosion control failures, Wetland destruction          |
| Labor Violations         | Undocumented workers, Wage theft, Unsafe conditions, Workers compensation fraud                       |
| Professional Misconduct  | Engineering malpractice, Falsified inspections, Unlicensed practice, Conflict of interest             |

### Financial & Insurance

| Category              | Subcategories                                                                                    |
| --------------------- | ------------------------------------------------------------------------------------------------ |
| Fraud                 | Insurance fraud, Claims manipulation, Identity theft, Money laundering, Insider trading          |
| Regulatory Violations | AML violations, KYC non-compliance, Licensing breaches, Reporting failures                       |
| Customer Harm         | Mis-selling of products, Unauthorized transactions, Unfair lending practices, Privacy violations |
| Conflict of Interest  | Self-dealing, Related party transactions, Undisclosed commissions                                |
| Workplace Misconduct  | Harassment, Discrimination, Bullying, Retaliation                                                |
| Cybersecurity         | Data breaches, Phishing incidents, System vulnerabilities, Unauthorized access                   |

### Security & Uniformed Services

| Category           | Subcategories                                                                                  |
| ------------------ | ---------------------------------------------------------------------------------------------- |
| Abuse of Authority | Excessive force, Unlawful detention, Harassment of civilians, Abuse of power                   |
| Corruption         | Bribery, Extortion, Theft of seized property, Falsifying reports                               |
| Misconduct         | Dereliction of duty, Insubordination, Unauthorized disclosure, Off-duty misconduct             |
| Discrimination     | Racial profiling, Gender discrimination, Religious discrimination, LGBTQ+ discrimination       |
| Safety Violations  | Improper weapon handling, Vehicle safety violations, Training deficiencies, Equipment failures |
| Workplace Issues   | Sexual harassment, Bullying, Hazing, Retaliation                                               |

### Hospitality, Travel & Tourism

| Category             | Subcategories                                                                       |
| -------------------- | ----------------------------------------------------------------------------------- |
| Guest Safety         | Food safety violations, Hygiene issues, Security breaches, Unsafe premises          |
| Employee Misconduct  | Theft from guests, Privacy violations, Harassment of guests, Unauthorized access    |
| Labor Violations     | Wage theft, Tip theft, Forced overtime, Discrimination                              |
| Fraud                | Booking fraud, Credit card fraud, Overbilling, False advertising                    |
| Health & Safety      | Pool safety issues, Fire code violations, Emergency preparedness, Pest infestations |
| Workplace Harassment | Sexual harassment, Bullying, Hostile work environment, Retaliation                  |

### NGO, CSO & Donor-Funded

| Category              | Subcategories                                                                                  |
| --------------------- | ---------------------------------------------------------------------------------------------- |
| Financial Misconduct  | Misuse of donor funds, Embezzlement, Fraudulent reporting, Kickbacks in procurement            |
| Program Fraud         | Falsified beneficiary numbers, Ghost projects, Diversion of aid, False impact reporting        |
| Safeguarding          | Sexual exploitation, Abuse of beneficiaries, Child protection failures, Trafficking concerns   |
| Governance Violations | Conflict of interest, Board misconduct, Policy violations, Lack of accountability              |
| Workplace Misconduct  | Harassment, Discrimination, Bullying, Retaliation                                              |
| Compliance Violations | Donor requirement breaches, Tax compliance issues, Registration violations, Reporting failures |

### Religious Institutions

| Category              | Subcategories                                                                             |
| --------------------- | ----------------------------------------------------------------------------------------- |
| Financial Misconduct  | Misuse of donations, Embezzlement, Fraudulent fundraising, Lack of financial transparency |
| Safeguarding          | Child abuse, Sexual misconduct, Vulnerable adult abuse, Domestic violence                 |
| Leadership Misconduct | Abuse of spiritual authority, Manipulation, Coercion, Cult-like practices                 |
| Discrimination        | Gender discrimination, Racial discrimination, LGBTQ+ discrimination, Caste discrimination |
| Governance Violations | Lack of accountability, Nepotism, Violation of bylaws, Cover-ups                          |
| Workplace Issues      | Harassment, Unfair treatment, Wage issues, Hostile environment                            |

### Transport & Logistics

| Category                 | Subcategories                                                                                                  |
| ------------------------ | -------------------------------------------------------------------------------------------------------------- |
| Safety Violations        | Driver fatigue violations, Vehicle maintenance failures, Overloading, Speed violations, Hazmat handling issues |
| Fraud                    | Cargo theft, Fuel theft, Invoice fraud, Insurance fraud                                                        |
| Regulatory Violations    | Licensing violations, Hours of service violations, Documentation fraud, Import/export violations               |
| Labor Violations         | Wage theft, Unpaid overtime, Discrimination, Unsafe working conditions                                         |
| Environmental Violations | Emissions violations, Illegal dumping, Fuel spills, Noise violations                                           |
| Workplace Misconduct     | Harassment, Bullying, Substance abuse, Retaliation                                                             |

## Error Responses

### Validation Error (422)

```json
{
    "success": false,
    "message": "Validation errors",
    "errors": {
        "sector": ["The selected sector is invalid."]
    }
}
```

### Company Not Found (404)

```json
{
    "success": false,
    "message": "Company not found"
}
```

### Company Inactive (404)

```json
{
    "success": false,
    "message": "Company not found or inactive."
}
```

### Server Error (500)

```json
{
    "success": false,
    "message": "Failed to retrieve incident categories.",
    "error": "Internal server error"
}
```

## Usage Examples

### Get available sectors:

```bash
GET /api/public/sectors
```

### Create a company with education sector:

```bash
POST /api/admin/companies
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "name": "ABC School",
    "email": "info@abcschool.com",
    "sector": "education",
    "plan": "plan_ulid_here"
}
```

### Update company sector to healthcare:

```bash
PUT /api/admin/companies/01abc123def456
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
    "sector": "healthcare"
}
```

### Get incident categories for admin dashboard:

```bash
GET /api/admin/incident-categories/company/01abc123def456
Authorization: Bearer YOUR_TOKEN
```

### Get incident categories for public reporting form:

```bash
GET /api/public/incident-categories/company/01abc123def456
```

## Database Schema

### sector_incident_templates

| Column           | Type               | Description                              |
| ---------------- | ------------------ | ---------------------------------------- |
| id               | ULID               | Primary key                              |
| sector           | ENUM               | Industry sector                          |
| category_key     | VARCHAR            | Unique key for the category              |
| category_name    | VARCHAR            | Display name of the category             |
| subcategory_name | VARCHAR (nullable) | Subcategory name (null for parent entry) |
| description      | TEXT (nullable)    | Category description                     |
| status           | BOOLEAN            | Active status (default: true)            |
| sort_order       | INTEGER            | Display order                            |
| created_at       | TIMESTAMP          | Creation timestamp                       |
| updated_at       | TIMESTAMP          | Last update timestamp                    |

### incident_categories

| Column       | Type                 | Description                                |
| ------------ | -------------------- | ------------------------------------------ |
| id           | ULID                 | Primary key                                |
| company_id   | ULID                 | Foreign key to companies                   |
| parent_id    | ULID (nullable)      | Self-referencing foreign key for hierarchy |
| name         | VARCHAR              | Category name                              |
| category_key | VARCHAR (nullable)   | Template origin key                        |
| description  | TEXT (nullable)      | Category description                       |
| status       | BOOLEAN              | Active status                              |
| sort_order   | INTEGER              | Display order                              |
| created_at   | TIMESTAMP            | Creation timestamp                         |
| updated_at   | TIMESTAMP            | Last update timestamp                      |
| deleted_at   | TIMESTAMP (nullable) | Soft delete timestamp                      |

## Notes

-   All endpoints returning company data respect the company's active status
-   Incident categories are soft-deleted, allowing for data recovery
-   Subcategories inherit the `company_id` from their parent category
-   The `category_key` field tracks which template was used to create the category
-   **Sector changes use intelligent sync**: adds new categories, removes obsolete ones, preserves custom categories
-   Custom categories (without `category_key`) are never automatically deleted
-   Public endpoints only return active (`status: true`) categories
-   Sort order values: Parent categories use multiples of 100, subcategories use incremental values

## CLI Commands

Manage incident categories via artisan commands:

```bash
# Sync categories for a specific company
php artisan company:categories info@company.com --sync

# Sync all companies with sectors
php artisan company:categories --all --sync

# Sync all companies in a specific sector (useful after template updates)
php artisan company:categories --sector=healthcare

# Force recreate (destructive - deletes all and creates fresh)
php artisan company:categories info@company.com --force

# Create categories for new company (no existing categories)
php artisan company:categories info@company.com
```
