/**
 * Axios Test Script for Case Submission API
 * 
 * This file demonstrates how to submit a case using axios with the correct payload structure.
 * 
 * IMPORTANT NOTES FOR AXIOS:
 * 1. For JSON data (without files): Use regular POST with Content-Type: application/json
 * 2. For files: Use FormData with Content-Type: multipart/form-data
 */

import axios from 'axios';

const API_BASE_URL = 'http://localhost:8000/api/public';

// =============================================================================
// TEST 1: Submit case WITHOUT files (JSON payload)
// =============================================================================
async function testCaseSubmissionWithoutFiles() {
    try {
        const payload = {
            company_id: 'YOUR_COMPANY_ID_HERE', // Replace with actual company ID
            branch_id: null, // Optional
            description: 'Test incident description - This is a test case submission',
            location_description: 'Office Building, 3rd Floor',
            date_time_type: 'specific', // or 'general'
            date_occurred: '2025-11-15',
            time_occurred: '14:30',
            general_timeframe: null, // Use this if date_time_type is 'general'
            company_relationship: 'employee', // employee, contractor, vendor, customer, etc.
            contact_info: {
                name: 'John Doe',
                email: 'john.doe@example.com',
                phone: '+255123456789',
                is_anonymous: false
            },
            involved_parties: [
                {
                    employee_id: 'EMP001',
                    nature_of_involvement: 'witness'
                }
            ],
            additional_parties: [
                {
                    name: 'Jane Smith',
                    email: 'jane.smith@example.com',
                    phone: '+255987654321',
                    job_title: 'Manager',
                    role: 'supervisor'
                }
            ],
            access_id: 'TEST-CASE-2025-001', // Unique ID for case tracking
            access_password: 'SecurePass123' // Min 6 characters
        };

        // Test the payload first
        console.log('Testing payload structure...');
        const testResponse = await axios.post(`${API_BASE_URL}/cases/test-payload`, payload, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });
        console.log('Test Response:', testResponse.data);

        // Submit actual case
        console.log('\nSubmitting case...');
        const response = await axios.post(`${API_BASE_URL}/cases/submit`, payload, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        console.log('Success:', response.data);
        return response.data;
    } catch (error) {
        if (error.response) {
            console.error('Error Response:', error.response.data);
            console.error('Status:', error.response.status);
        } else {
            console.error('Error:', error.message);
        }
        throw error;
    }
}

// =============================================================================
// TEST 2: Submit case WITH files (FormData)
// =============================================================================
async function testCaseSubmissionWithFiles(files) {
    try {
        const formData = new FormData();

        // Add all regular fields
        formData.append('company_id', 'YOUR_COMPANY_ID_HERE');
        formData.append('description', 'Test incident with files');
        formData.append('location_description', 'Office Building, 3rd Floor');
        formData.append('date_time_type', 'specific');
        formData.append('date_occurred', '2025-11-15');
        formData.append('time_occurred', '14:30');
        formData.append('company_relationship', 'employee');
        formData.append('access_id', 'TEST-CASE-WITH-FILES-001');
        formData.append('access_password', 'SecurePass123');

        // Add contact info (nested object)
        formData.append('contact_info[name]', 'John Doe');
        formData.append('contact_info[email]', 'john.doe@example.com');
        formData.append('contact_info[phone]', '+255123456789');
        formData.append('contact_info[is_anonymous]', 'false');

        // Add involved parties (array of objects)
        formData.append('involved_parties[0][employee_id]', 'EMP001');
        formData.append('involved_parties[0][nature_of_involvement]', 'witness');

        // Add additional parties (array of objects)
        formData.append('additional_parties[0][name]', 'Jane Smith');
        formData.append('additional_parties[0][email]', 'jane.smith@example.com');
        formData.append('additional_parties[0][phone]', '+255987654321');
        formData.append('additional_parties[0][job_title]', 'Manager');
        formData.append('additional_parties[0][role]', 'supervisor');

        // Add files
        if (files && files.length > 0) {
            files.forEach((file, index) => {
                formData.append(`files[${index}][file]`, file);
                formData.append(`files[${index}][type]`, 'document'); // or 'image', 'video', 'audio'
                formData.append(`files[${index}][name]`, file.name);
                formData.append(`files[${index}][is_confidential]`, 'false');
            });
        }

        console.log('Submitting case with files...');
        const response = await axios.post(`${API_BASE_URL}/cases/submit`, formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
                'Accept': 'application/json'
            },
            onUploadProgress: (progressEvent) => {
                const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
                console.log(`Upload Progress: ${percentCompleted}%`);
            }
        });

        console.log('Success:', response.data);
        return response.data;
    } catch (error) {
        if (error.response) {
            console.error('Error Response:', error.response.data);
            console.error('Status:', error.response.status);
        } else {
            console.error('Error:', error.message);
        }
        throw error;
    }
}

// =============================================================================
// TEST 3: Track case using access credentials
// =============================================================================
async function testCaseTracking(accessId, accessPassword) {
    try {
        const payload = {
            access_id: accessId,
            access_password: accessPassword
        };

        console.log('Tracking case...');
        const response = await axios.post(`${API_BASE_URL}/cases/track`, payload, {
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        console.log('Case Details:', response.data);
        return response.data;
    } catch (error) {
        if (error.response) {
            console.error('Error Response:', error.response.data);
            console.error('Status:', error.response.status);
        } else {
            console.error('Error:', error.message);
        }
        throw error;
    }
}

// =============================================================================
// EXAMPLE USAGE IN REACT/VUE COMPONENT
// =============================================================================

/**
 * React Example:
 */
/*
const handleSubmit = async (formData) => {
  try {
    const response = await axios.post('http://localhost:8000/api/public/cases/submit', formData, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });
    
    // Handle success
    console.log('Case submitted:', response.data);
    alert(`Case submitted successfully! Access ID: ${response.data.data.access_id}`);
  } catch (error) {
    // Handle error
    if (error.response?.data?.errors) {
      // Validation errors
      console.error('Validation errors:', error.response.data.errors);
    } else {
      console.error('Error:', error.response?.data?.message || error.message);
    }
  }
};
*/

/**
 * Vue 3 Example:
 */
/*
const submitCase = async () => {
  try {
    const { data } = await axios.post('/api/public/cases/submit', formState, {
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      }
    });
    
    // Success
    console.log('Case submitted:', data);
    router.push({ name: 'case-confirmation', params: { accessId: data.data.access_id } });
  } catch (error) {
    // Error handling
    if (error.response?.status === 422) {
      errors.value = error.response.data.errors;
    } else {
      errorMessage.value = error.response?.data?.message || 'An error occurred';
    }
  }
};
*/

// =============================================================================
// EXPORT FUNCTIONS
// =============================================================================
export {
    testCaseSubmissionWithoutFiles,
    testCaseSubmissionWithFiles,
    testCaseTracking
};

// =============================================================================
// QUICK TEST (for Node.js environment)
// =============================================================================
// Uncomment to run quick test
// testCaseSubmissionWithoutFiles().catch(console.error);
