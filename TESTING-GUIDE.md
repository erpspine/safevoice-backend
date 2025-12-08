# Case Submission API - Testing Guide

## 🎯 Quick Start

### 1. Open Test Page

Open this URL in your browser:

```
http://localhost:8000/test-case-api.html
```

### 2. Use This Company ID

```
01k9yhbtdq68km9gfaxq94zgjq
```

---

## 📋 Required Fields

The API now requires these fields (after removing `incident_category_id` and `priority`):

### ✅ Required:

-   `company_id` - Company identifier
-   `description` - Incident description
-   `date_time_type` - "specific" or "general"
-   `company_relationship` - employee, contractor, vendor, customer, etc.
-   `access_id` - Unique tracking ID (auto-generated in test page)
-   `access_password` - Password for case tracking (min 6 chars)

### ⚪ Optional:

-   `branch_id` - Branch identifier
-   `location_description` - Where it happened
-   `date_occurred` - Date when incident occurred
-   `time_occurred` - Time when incident occurred
-   `general_timeframe` - Used if date_time_type is "general"
-   `contact_info` - Reporter contact details
-   `involved_parties` - Array of involved employees
-   `additional_parties` - Array of external parties
-   `files` - Array of file uploads (max 10 files, 10MB each)

---

## 🧪 Testing Steps

### Step 1: Test Payload Structure

1. Click "Test Payload" button
2. Verify the payload is received correctly
3. Check for any errors

### Step 2: Submit Case

1. Enter Company ID: `01k9yhbtdq68km9gfaxq94zgjq`
2. Fill in description
3. Click "Submit Case"
4. Note the returned `access_id` and `case_number`

### Step 3: Track Case

1. Use the access credentials from Step 2
2. Click "Track Case"
3. Verify case details are returned

---

## 📡 API Endpoints

### Submit Case

```
POST http://localhost:8000/api/public/cases/submit
Content-Type: application/json
```

### Test Payload (Debug)

```
POST http://localhost:8000/api/public/cases/test-payload
Content-Type: application/json
```

### Track Case

```
POST http://localhost:8000/api/public/cases/track
Content-Type: application/json
```

---

## 💻 Axios Example (JSON - No Files)

```javascript
import axios from "axios";

const submitCase = async () => {
    try {
        const response = await axios.post(
            "http://localhost:8000/api/public/cases/submit",
            {
                company_id: "01k9yhbtdq68km9gfaxq94zgjq",
                description: "Test incident description",
                date_time_type: "specific",
                date_occurred: "2025-11-15",
                time_occurred: "14:30",
                company_relationship: "employee",
                location_description: "Office Building, 3rd Floor",
                contact_info: {
                    name: "John Doe",
                    email: "john.doe@example.com",
                    phone: "+255123456789",
                    is_anonymous: false,
                },
                access_id: "UNIQUE-ID-HERE",
                access_password: "SecurePass123",
            },
            {
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
            }
        );

        console.log("Success:", response.data);
    } catch (error) {
        console.error("Error:", error.response?.data);
    }
};
```

---

## 💻 Axios Example (FormData - With Files)

```javascript
const submitCaseWithFiles = async (files) => {
    const formData = new FormData();

    // Add fields
    formData.append("company_id", "01k9yhbtdq68km9gfaxq94zgjq");
    formData.append("description", "Test incident with files");
    formData.append("date_time_type", "specific");
    formData.append("company_relationship", "employee");
    formData.append("access_id", "UNIQUE-ID-HERE");
    formData.append("access_password", "SecurePass123");

    // Add nested contact info
    formData.append("contact_info[name]", "John Doe");
    formData.append("contact_info[email]", "john.doe@example.com");
    formData.append("contact_info[is_anonymous]", "false");

    // Add files
    files.forEach((file, index) => {
        formData.append(`files[${index}][file]`, file);
        formData.append(`files[${index}][type]`, "document");
        formData.append(`files[${index}][name]`, file.name);
    });

    try {
        const response = await axios.post(
            "http://localhost:8000/api/public/cases/submit",
            formData,
            {
                headers: {
                    "Content-Type": "multipart/form-data",
                },
            }
        );

        console.log("Success:", response.data);
    } catch (error) {
        console.error("Error:", error.response?.data);
    }
};
```

---

## ✅ Expected Success Response

```json
{
    "status": "success",
    "message": "Case submitted successfully",
    "data": {
        "case_id": "01k9yhbtdq68km9gfaxq94zgjq",
        "case_number": "UNIQUE-ID-HERE",
        "access_id": "UNIQUE-ID-HERE",
        "status": "open",
        "submitted_at": "2025-11-15T14:30:00.000000Z",
        "files_uploaded": 0,
        "tracking_info": {
            "message": "Save your access credentials to track case progress",
            "access_id": "UNIQUE-ID-HERE",
            "note": "You will need these credentials to check your case status"
        }
    }
}
```

---

## ❌ Common Errors

### 422 Validation Error

```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "company_id": ["The company id field is required."],
        "access_id": ["The access id has already been taken."]
    }
}
```

**Solutions:**

-   Check all required fields are provided
-   Ensure `access_id` is unique
-   Use a different `access_id` for each submission

### 404 Company Not Found

```json
{
    "status": "error",
    "message": "Validation failed",
    "errors": {
        "company_id": ["The selected company id is invalid."]
    }
}
```

**Solution:** Use a valid company ID: `01k9yhbtdq68km9gfaxq94zgjq`

---

## 🔧 Files Included

1. **test-case-api.html** - Interactive test page
2. **axios-test-examples.js** - Axios code examples
3. **test-case-submission.json** - Sample JSON payload

---

## 📝 Changes Made

✅ Removed `incident_category_id` from validation and database  
✅ Removed `priority` from validation and made it nullable in database  
✅ Added debug route: `/api/public/cases/test-payload`  
✅ Created interactive HTML test page  
✅ Created axios examples with file upload support

---

## 🚀 Next Steps

1. Open test page: http://localhost:8000/test-case-api.html
2. Test payload structure first
3. Submit a test case
4. Track the submitted case
5. Integrate into your frontend using the axios examples

---

## 📞 Support

If you encounter any issues:

1. Check browser console for errors
2. Check Laravel logs: `storage/logs/laravel.log`
3. Use the test-payload endpoint to debug
4. Verify company_id exists in database
