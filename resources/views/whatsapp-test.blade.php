<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Test - SafeVoice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #25D366;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }

        button {
            background-color: #25D366;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background-color: #128C7E;
        }

        .response {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
            display: none;
        }

        .success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .config-section {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }

        .config-section h3 {
            margin-top: 0;
            color: #856404;
        }

        .code {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>📱 WhatsApp API Test - SafeVoice</h1>

        <div class="config-section">
            <h3>⚙️ Configuration Required</h3>
            <p>Before testing, you need to provide the following in your <code>.env</code> file:</p>
            <div class="code">
                # WhatsApp Business API Configuration<br>
                WHATSAPP_ACCESS_TOKEN=your_access_token_here<br>
                WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id_here<br>
                WHATSAPP_BUSINESS_ACCOUNT_ID=your_business_account_id_here<br>
                WHATSAPP_VERIFY_TOKEN=your_webhook_verify_token_here
            </div>
            <p><strong>How to get these values:</strong></p>
            <ol>
                <li><strong>Facebook Developer Account:</strong> Create an app at <a
                        href="https://developers.facebook.com">developers.facebook.com</a></li>
                <li><strong>WhatsApp Business API:</strong> Add WhatsApp product to your app</li>
                <li><strong>Phone Number:</strong> Get a test phone number or use your business number</li>
                <li><strong>Access Token:</strong> Generate a temporary token (24h) or permanent token</li>
            </ol>
        </div>

        <form id="whatsappForm">
            <div class="form-group">
                <label for="message_type">Message Type:</label>
                <select id="message_type" name="message_type" required>
                    <option value="test">Test Message</option>
                    <option value="case_notification">Case Notification</option>
                    <option value="template">Template Message</option>
                    <option value="address_update">Address Update Template</option>
                </select>
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number (with country code):</label>
                <input type="tel" id="phone_number" name="phone_number" placeholder="e.g., +1234567890" required>
                <small>Format: +[country_code][phone_number] (e.g., +1234567890)</small>
            </div>

            <div class="form-group test-message">
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="4" placeholder="Type your test message here..."></textarea>
            </div>

            <div class="form-group case-notification" style="display: none;">
                <label for="case_id">Case ID:</label>
                <input type="text" id="case_id" name="case_id" placeholder="e.g., CASE-001">
            </div>

            <div class="form-group case-notification" style="display: none;">
                <label for="notification_type">Notification Type:</label>
                <select id="notification_type" name="notification_type">
                    <option value="new_case">New Case</option>
                    <option value="case_assigned">Case Assigned</option>
                    <option value="case_updated">Case Updated</option>
                    <option value="case_closed">Case Closed</option>
                </select>
            </div>

            <div class="form-group case-notification" style="display: none;">
                <label for="additional_message">Additional Message (optional):</label>
                <textarea id="additional_message" name="additional_message" rows="3" placeholder="Any additional information..."></textarea>
            </div>

            <div class="form-group template-message" style="display: none;">
                <label for="template_name">Template Name:</label>
                <input type="text" id="template_name" name="template_name" placeholder="e.g., address_update">
            </div>

            <div class="form-group template-message" style="display: none;">
                <label for="template_parameters">Parameters (comma separated):</label>
                <input type="text" id="template_parameters" name="template_parameters"
                    placeholder="e.g., John Doe, 123 Main St, support@company.com">
            </div>

            <div class="form-group address-update" style="display: none;">
                <label for="customer_name">Customer Name:</label>
                <input type="text" id="customer_name" name="customer_name" placeholder="e.g., John Doe">
            </div>

            <div class="form-group address-update" style="display: none;">
                <label for="new_address">New Address:</label>
                <input type="text" id="new_address" name="new_address" placeholder="e.g., 123 Main Street, City">
            </div>

            <div class="form-group address-update" style="display: none;">
                <label for="contact_info">Contact Information:</label>
                <input type="text" id="contact_info" name="contact_info"
                    placeholder="e.g., support@company.com or +1234567890">
            </div>

            <button type="submit">📤 Send WhatsApp Message</button>
        </form>

        <div id="response" class="response"></div>

        <div class="config-section" style="margin-top: 30px;">
            <h3>🔧 API Endpoints Created</h3>
            <div class="code">
                POST /api/whatsapp/test-message<br>
                POST /api/whatsapp/case-notification
            </div>
            <p><strong>Next Steps for Integration:</strong></p>
            <ul>
                <li>Configure webhook URL for receiving messages</li>
                <li>Set up message templates for faster delivery</li>
                <li>Integrate with case management system</li>
                <li>Add user phone number fields to database</li>
            </ul>
        </div>
    </div>

    <script>
        const messageType = document.getElementById('message_type');
        const testFields = document.querySelectorAll('.test-message');
        const caseFields = document.querySelectorAll('.case-notification');
        const templateFields = document.querySelectorAll('.template-message');
        const addressFields = document.querySelectorAll('.address-update');

        messageType.addEventListener('change', function() {
            // Hide all fields first and remove required attributes
            testFields.forEach(field => {
                field.style.display = 'none';
                const inputs = field.querySelectorAll('input, textarea, select');
                inputs.forEach(input => input.removeAttribute('required'));
            });
            caseFields.forEach(field => {
                field.style.display = 'none';
                const inputs = field.querySelectorAll('input, textarea, select');
                inputs.forEach(input => input.removeAttribute('required'));
            });
            templateFields.forEach(field => {
                field.style.display = 'none';
                const inputs = field.querySelectorAll('input, textarea, select');
                inputs.forEach(input => input.removeAttribute('required'));
            });
            addressFields.forEach(field => {
                field.style.display = 'none';
                const inputs = field.querySelectorAll('input, textarea, select');
                inputs.forEach(input => input.removeAttribute('required'));
            });

            // Show relevant fields and add required attributes
            if (this.value === 'test') {
                testFields.forEach(field => {
                    field.style.display = 'block';
                    const inputs = field.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => input.setAttribute('required', 'required'));
                });
            } else if (this.value === 'case_notification') {
                caseFields.forEach(field => {
                    field.style.display = 'block';
                    // Only make required fields required (case_id and notification_type)
                    if (field.querySelector('#case_id') || field.querySelector('#notification_type')) {
                        const inputs = field.querySelectorAll('input, select');
                        inputs.forEach(input => input.setAttribute('required', 'required'));
                    }
                });
            } else if (this.value === 'template') {
                templateFields.forEach(field => {
                    field.style.display = 'block';
                    const inputs = field.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => input.setAttribute('required', 'required'));
                });
            } else if (this.value === 'address_update') {
                addressFields.forEach(field => {
                    field.style.display = 'block';
                    const inputs = field.querySelectorAll('input, textarea, select');
                    inputs.forEach(input => input.setAttribute('required', 'required'));
                });
            }
        });

        // Trigger change event on page load to set up the form correctly
        messageType.dispatchEvent(new Event('change'));
        document.getElementById('whatsappForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const responseDiv = document.getElementById('response');

            const data = {
                phone_number: formData.get('phone_number'),
            };

            let endpoint = '';

            if (formData.get('message_type') === 'test') {
                endpoint = '/api/whatsapp/test-message';
                data.message = formData.get('message');
            } else if (formData.get('message_type') === 'case_notification') {
                endpoint = '/api/whatsapp/case-notification';
                data.case_id = formData.get('case_id');
                data.notification_type = formData.get('notification_type');
                data.additional_message = formData.get('additional_message');
            } else if (formData.get('message_type') === 'template') {
                endpoint = '/api/whatsapp/template-message';
                data.template_name = formData.get('template_name');
                const params = formData.get('template_parameters');
                if (params) {
                    data.parameters = params.split(',').map(p => p.trim());
                }
                data.language_code = 'en_US';
            } else if (formData.get('message_type') === 'address_update') {
                endpoint = '/api/whatsapp/address-update';
                data.customer_name = formData.get('customer_name');
                data.new_address = formData.get('new_address');
                data.contact_info = formData.get('contact_info');
            }

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                responseDiv.style.display = 'block';
                if (response.ok && result.success) {
                    responseDiv.className = 'response success';
                    responseDiv.innerHTML = `
                        <strong>✅ Success!</strong><br>
                        ${result.message}<br>
                        <small>Message ID: ${result.data?.message_id || 'N/A'}</small>
                    `;
                } else {
                    responseDiv.className = 'response error';
                    responseDiv.innerHTML = `
                        <strong>❌ Error!</strong><br>
                        ${result.message || 'Unknown error'}<br>
                        <small>${result.error || ''}</small>
                    `;
                }
            } catch (error) {
                responseDiv.style.display = 'block';
                responseDiv.className = 'response error';
                responseDiv.innerHTML = `
                    <strong>❌ Network Error!</strong><br>
                    ${error.message}
                `;
            }
        });
    </script>
</body>

</html>
