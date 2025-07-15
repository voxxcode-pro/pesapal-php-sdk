<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Submit Order Request</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.min.css">

    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">



    <!-- Custom CSS to Prevent FOUC -->
    <style>
    .content-wrapper {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease-in;
    }

    .content-visible {
        display: block;
        opacity: 1;
    }

    /* Ensure intl-tel-input fits Bootstrap's form control style */
    .iti {
        width: 100%;
    }

    .iti--allow-dropdown {
        width: 100%;
    }

    .iti input[type="tel"] {
        width: 100%;
        height: calc(1.5em + .75rem + 2px);
        padding: .375rem .75rem;
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: .25rem;
        transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
    }

    /* Powered By Container Styling */
    .powered-by-container {
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 50px;
        padding: 10px;
        background: linear-gradient(90deg, rgba(255, 255, 255, 0) 0%, rgba(230, 230, 230, 0.5) 50%, rgba(255, 255, 255, 0) 100%);
        border-radius: 5px;
        position: relative;
    }

    .powered-by-container::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 5%;
        width: 40%;
        height: 1px;
        background-color: #eaeaea;
        transform: translateY(-50%);
    }

    .powered-by-container::after {
        content: '';
        position: absolute;
        top: 50%;
        right: 5%;
        width: 40%;
        height: 1px;
        background-color: #eaeaea;
        transform: translateY(-50%);
    }

    .powered-by-text {
        margin-right: 10px;
        font-weight: bold;
        color: #555;
        z-index: 1;
        background-color: #fff;
        padding: 0 5px;
    }

    .pesapal-logo {
        max-height: 30px;
        z-index: 1;
    }
    </style>
</head>

<body>
    <!-- Main Content Wrapper -->
    <div class="container content-wrapper" id="contentWrapperContainer">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mt-5 text-center">Submit Order Request</h2>
                <p class="text-muted text-center">Enter the details for your order request.</p>
                <!-- Success Message -->
                <div id="orderSuccessMessage" class="alert alert-success mt-4" style="display: none;">
                    Order submitted successfully!
                </div>

                <!-- Error Message -->
                <div id="orderErrorMessage" class="alert alert-danger mt-4" style="display: none;">
                    There was an error submitting the order. Please try again.
                </div>
                <!-- Order Request Form -->
                <form id="orderRequestForm">
                    <div class="mb-3">
                        <label for="amountField" class="form-label">Amount:</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text" id="currencyLabel">USD</span>
                            <input type="text" id="amountField" name="amount" class="form-control" placeholder="Amount"
                                aria-label="Amount" aria-describedby="currencyLabel" required>
                            <div id="amountErrorFeedback" class="invalid-feedback" style="display: none;">
                                Please enter a valid amount.
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="descriptionField">Description</label>
                        <textarea class="form-control" id="descriptionField" name="description" maxlength="100"
                            placeholder="Order description" required></textarea>
                        <small id="descriptionCount" class="form-text text-muted">0 / 100 characters</small>
                    </div>
                    <div class="form-group">
                        <label for="merchantReferenceField">Merchant Reference</label>
                        <input type="text" class="form-control" id="merchantReferenceField" name="merchant_reference"
                            value="" readonly>
                        <small class="form-text text-muted">Auto-generated unique reference for this order.</small>
                    </div>
                    <div class="form-group">
                        <label for="emailField">Email</label>
                        <input type="email" class="form-control" id="emailField" name="email"
                            placeholder="customer@example.com">
                    </div>

                    <!-- Phone Number Field -->
                    <div class="mb-3">
                        <label for="phoneNumberField" class="form-label">Phone Number</label>
                        <input type="tel" id="phoneNumberField" name="phone_number" class="form-control"
                            placeholder="Phone number" aria-label="Phone number">
                        <div id="phoneErrorFeedback" class="invalid-feedback" style="display: none;">
                            Please enter a valid phone number.
                        </div>
                    </div>
                    <!-- Billing Details -->
                    <h4 class="mt-4">Billing Details</h4>
                    <div class="form-group">
                        <label for="firstNameField">First Name</label>
                        <input type="text" class="form-control" id="firstNameField" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="lastNameField">Last Name</label>
                        <input type="text" class="form-control" id="lastNameField" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="addressLine1Field">Address Line 1</label>
                        <input type="text" class="form-control" id="addressLine1Field" name="address_line1" required>
                    </div>
                    <div class="form-group">
                        <label for="addressLine2Field">Address Line 2 (Optional)</label>
                        <input type="text" class="form-control" id="addressLine2Field" name="address_line2">
                    </div>
                    <div class="form-group">
                        <label for="cityField">City</label>
                        <input type="text" class="form-control" id="cityField" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="stateField">State/Province</label>
                        <input type="text" class="form-control" id="stateField" name="state" required>
                    </div>
                    <div class="form-group">
                        <label for="postalCodeField">Postal Code</label>
                        <input type="text" class="form-control" id="postalCodeField" name="postal_code" required>
                    </div>
                    <div class="form-group">
                        <label for="countryField">Country</label>
                        <select class="form-control" id="countryField" name="country" required>
                            <!-- You can populate this with country options -->
                            <option value="">Select Country</option>
                            <option value="AF">Afghanistan</option>
                            <option value="AL">Albania</option>
                            <option value="DZ">Algeria</option>
                            <option value="AM">Armenia</option>
                            <option value="AU">Australia</option>
                            <option value="AT">Austria</option>
                            <option value="AZ">Azerbaijan</option>
                            <option value="BH">Bahrain</option>
                            <option value="BD">Bangladesh</option>
                            <option value="BY">Belarus</option>
                            <option value="BE">Belgium</option>
                            <option value="BA">Bosnia and Herzegovina</option>
                            <option value="BR">Brazil</option>
                            <option value="BG">Bulgaria</option>
                            <option value="BF">Burkina Faso</option>
                            <option value="BI">Burundi</option>
                            <option value="KH">Cambodia</option>
                            <option value="CM">Cameroon</option>
                            <option value="CA">Canada</option>
                            <option value="CV">Cape Verde</option>
                            <option value="KY">Cayman Islands</option>
                            <option value="CL">Chile</option>
                            <option value="CN">China</option>
                            <option value="CO">Colombia</option>
                            <option value="KM">Comoros</option>
                            <option value="CG">Congo</option>
                            <option value="CD">Congo, Democratic Republic of the</option>
                            <option value="CR">Costa Rica</option>
                            <option value="HR">Croatia</option>
                            <option value="CU">Cuba</option>
                            <option value="CY">Cyprus</option>
                            <option value="CZ">Czech Republic</option>
                            <option value="DK">Denmark</option>
                            <option value="DJ">Djibouti</option>
                            <option value="DM">Dominica</option>
                            <option value="DO">Dominican Republic</option>
                            <option value="EC">Ecuador</option>
                            <option value="EG">Egypt</option>
                            <option value="SV">El Salvador</option>
                            <option value="GQ">Equatorial Guinea</option>
                            <option value="ER">Eritrea</option>
                            <option value="EE">Estonia</option>
                            <option value="ET">Ethiopia</option>
                            <option value="FI">Finland</option>
                            <option value="FR">France</option>
                            <option value="GA">Gabon</option>
                            <option value="GM">Gambia</option>
                            <option value="GE">Georgia</option>
                            <option value="DE">Germany</option>
                            <option value="GH">Ghana</option>
                            <option value="GR">Greece</option>
                            <option value="GD">Grenada</option>
                            <option value="GT">Guatemala</option>
                            <option value="GN">Guinea</option>
                            <option value="GW">Guinea-Bissau</option>
                            <option value="GY">Guyana</option>
                            <option value="HT">Haiti</option>
                            <option value="HN">Honduras</option>
                            <option value="HK">Hong Kong</option>
                            <option value="HU">Hungary</option>
                            <option value="IS">Iceland</option>
                            <option value="IN">India</option>
                            <option value="ID">Indonesia</option>
                            <option value="IR">Iran</option>
                            <option value="IQ">Iraq</option>
                            <option value="IE">Ireland</option>
                            <option value="IL">Israel</option>
                            <option value="IT">Italy</option>
                            <option value="JM">Jamaica</option>
                            <option value="JP">Japan</option>
                            <option value="JO">Jordan</option>
                            <option value="KZ">Kazakhstan</option>
                            <option value="KE">Kenya</option>
                            <option value="KI">Kiribati</option>
                            <option value="KW">Kuwait</option>
                            <option value="KG">Kyrgyzstan</option>
                            <option value="LA">Laos</option>
                            <option value="LV">Latvia</option>
                            <option value="LB">Lebanon</option>
                            <option value="LS">Lesotho</option>
                            <option value="LR">Liberia</option>
                            <option value="LY">Libya</option>
                            <option value="LI">Liechtenstein</option>
                            <option value="LT">Lithuania</option>
                            <option value="LU">Luxembourg</option>
                            <option value="MO">Macau</option>
                            <option value="MK">Macedonia</option>
                            <option value="MG">Madagascar</option>
                            <option value="MW">Malawi</option>
                            <option value="MY">Malaysia</option>
                            <option value="MV">Maldives</option>
                            <option value="ML">Mali</option>
                            <option value="MT">Malta</option>
                            <option value="MH">Marshall Islands</option>
                            <option value="MQ">Martinique</option>
                            <option value="MR">Mauritania</option>
                            <option value="MU">Mauritius</option>
                            <option value="MX">Mexico</option>
                            <option value="FM">Micronesia</option>
                            <option value="MD">Moldova</option>
                            <option value="MC">Monaco</option>
                            <option value="MN">Mongolia</option>
                            <option value="ME">Montenegro</option>
                            <option value="MS">Montserrat</option>
                            <option value="MA">Morocco</option>
                            <option value="MZ">Mozambique</option>
                            <option value="MM">Myanmar</option>
                            <option value="NA">Namibia</option>
                            <option value="NR">Nauru</option>
                            <option value="NP">Nepal</option>
                            <option value="NL">Netherlands</option>
                            <option value="NC">New Caledonia</option>
                            <option value="NZ">New Zealand</option>
                            <option value="NI">Nicaragua</option>
                            <option value="NE">Niger</option>
                            <option value="NG">Nigeria</option>
                            <option value="KP">North Korea</option>
                            <option value="NO">Norway</option>
                            <option value="OM">Oman</option>
                            <option value="PK">Pakistan</option>
                            <option value="PW">Palau</option>
                            <option value="PA">Panama</option>
                            <option value="PG">Papua New Guinea</option>
                            <option value="PY">Paraguay</option>
                            <option value="PE">Peru</option>
                            <option value="PH">Philippines</option>
                            <option value="PL">Poland</option>
                            <option value="PT">Portugal</option>
                            <option value="PR">Puerto Rico</option>
                            <option value="QA">Qatar</option>
                            <option value="RO">Romania</option>
                            <option value="RU">Russia</option>
                            <option value="RW">Rwanda</option>
                            <option value="RE">Réunion</option>
                            <option value="ST">São Tomé and Príncipe</option>
                            <option value="SA">Saudi Arabia</option>
                            <option value="SN">Senegal</option>
                            <option value="RS">Serbia</option>
                            <option value="SC">Seychelles</option>
                            <option value="SL">Sierra Leone</option>
                            <option value="SG">Singapore</option>
                            <option value="SK">Slovakia</option>
                            <option value="SI">Slovenia</option>
                            <option value="SB">Solomon Islands</option>
                            <option value="SO">Somalia</option>
                            <option value="ZA">South Africa</option>
                            <option value="KR">South Korea</option>
                            <option value="SS">South Sudan</option>
                            <option value="ES">Spain</option>
                            <option value="LK">Sri Lanka</option>
                            <option value="SD">Sudan</option>
                            <option value="SR">Suriname</option>
                            <option value="SJ">Svalbard and Jan Mayen</option>
                            <option value="SZ">Swaziland</option>
                            <option value="SE">Sweden</option>
                            <option value="CH">Switzerland</option>
                            <option value="SY">Syria</option>
                            <option value="TW">Taiwan</option>
                            <option value="TJ">Tajikistan</option>
                            <option value="TZ">Tanzania</option>
                            <option value="TH">Thailand</option>
                            <option value="TL">Timor-Leste</option>
                            <option value="TG">Togo</option>
                            <option value="TK">Tokelau</option>
                            <option value="TO">Tonga</option>
                            <option value="TT">Trinidad and Tobago</option>
                            <option value="TN">Tunisia</option>
                            <option value="TR">Turkey</option>
                            <option value="TM">Turkmenistan</option>
                            <option value="TC">Turks and Caicos Islands</option>
                            <option value="TV">Tuvalu</option>
                            <option value="UG">Uganda</option>
                            <option value="UA">Ukraine</option>
                            <option value="AE">United Arab Emirates</option>
                            <option value="GB">United Kingdom</option>
                            <option value="US">United States</option>
                            <option value="UY">Uruguay</option>
                            <option value="UZ">Uzbekistan</option>
                            <option value="VU">Vanuatu</option>
                            <option value="VE">Venezuela</option>
                            <option value="VN">Vietnam</option>
                            <option value="WF">Wallis and Futuna</option>
                            <option value="YE">Yemen</option>
                            <option value="ZM">Zambia</option>
                            <option value="ZW">Zimbabwe</option>

                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block mb-4" id="submitOrderButton">Submit
                        Order</button>
                </form>
                <!-- "Powered by Pesapal" Section -->
                <div class="powered-by-container">
                    <span class="powered-by-text">Powered by</span>
                    <img src="images/pesapal.png" alt="Pesapal Logo" class="pesapal-logo">
                </div>

            </div>
        </div>
    </div>

    <!-- jQuery and Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <!-- Include the intl-tel-input script and CSS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/intlTelInput.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"></script>

    <!-- JavaScript to Show Content After Load, Clear Form, and Handle Form Submission -->
    <script>
    window.addEventListener('load', function() {
        // Clear all form fields on page load
        document.getElementById('orderRequestForm').reset();

        document.getElementById('contentWrapperContainer').classList.add('content-visible');

        // Generate a unique merchant reference on page load
        $('#merchantReferenceField').val(generateMerchantReference());
    });

    // Function to generate a unique merchant reference
    function generateMerchantReference() {
        return (Math.random().toString(36).substr(2, 4) + '-' + Math.random().toString(36).substr(2, 4)).toUpperCase();
    }

    $(document).ready(function() {



        var phoneInput = document.querySelector("#phoneNumberField");

        var countryField = document.querySelector("#countryField");

        var iti = window.intlTelInput(phoneInput, {
            initialCountry: "UG",
            separateDialCode: true,
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/js/utils.js"
        });

        // Update country code when the selected country changes in the dropdown
        countryField.addEventListener("change", function() {
            var selectedCountryCode = countryField.value;
            if (selectedCountryCode) {
                iti.setCountry(selectedCountryCode); // Set the selected country in intl-tel-input
                var countryData = iti.getSelectedCountryData();

            }
        });



        // Description character count
        $('#descriptionField').on('input', function() {
            const currentLength = $(this).val().length;
            $('#descriptionCount').text(`${currentLength} / 100 characters`);
        });


        // Validate phone number using intl-tel-input
        function validatePhoneNumber() {
            return iti.isValidNumber();
        }

        // Function to validate billing details
        function validateBillingDetails() {
            let isValid = true;
            const requiredFields = [{
                    id: '#firstNameField',
                    name: 'First Name'
                },
                {
                    id: '#lastNameField',
                    name: 'Last Name'
                },
                {
                    id: '#addressLine1Field',
                    name: 'Address Line 1'
                },
                {
                    id: '#cityField',
                    name: 'City'
                },
                {
                    id: '#stateField',
                    name: 'State/Province'
                },
                {
                    id: '#postalCodeField',
                    name: 'Postal Code'
                },
                {
                    id: '#countryField',
                    name: 'Country'
                }
            ];

            requiredFields.forEach(field => {
                const value = $(field.id).val().trim();
                if (!value) {
                    isValid = false;
                    $(field.id).addClass('is-invalid');
                    $(field.id).next('.invalid-feedback').remove(); // Remove any existing feedback
                    $(field.id).after(
                        `<div class="invalid-feedback">Please enter ${field.name}.</div>`);
                } else {
                    $(field.id).removeClass('is-invalid');
                    $(field.id).next('.invalid-feedback').remove();
                }
            });

            return isValid;
        }

        // AJAX form submission logic with validation
        $('#orderRequestForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const amount = $('#amountField').val();
            const description = $('#descriptionField').val();
            const merchantReference = $('#merchantReferenceField').val();
            const email = $('#emailField').val();
            const phoneNumber = $('#phoneNumberField').val();

            // Get billing details
            const firstName = $('#firstNameField').val();
            const lastName = $('#lastNameField').val();
            const addressLine1 = $('#addressLine1Field').val();
            const addressLine2 = $('#addressLine2Field').val();
            const city = $('#cityField').val();
            const state = $('#stateField').val();
            const postalCode = $('#postalCodeField').val();
            const country = $('#countryField').val();

            // Validate billing details
            if (!firstName || !lastName || !addressLine1 || !city || !state || !postalCode || !
                country || !email || !phoneNumber) {
                $('#orderErrorMessage').text('Please fill in all required billing details.').show();
                $('#orderSuccessMessage').hide();
                return;
            }


            // Validate phone number if provided
            if (phoneNumber && !validatePhoneNumber()) {
                $('#phoneNumberField').addClass('is-invalid');
                $('#phoneErrorFeedback').show();
                $('#orderErrorMessage').text('Please enter a valid phone number.').show();
                $('#orderSuccessMessage').hide();
                return;
            } else {
                $('#phoneNumberField').removeClass('is-invalid');
                $('#phoneErrorFeedback').hide();
            }

            // Validate billing details
            if (!validateBillingDetails()) {
                $('#orderErrorMessage').text('Please fill in all required billing details.').show();
                $('#orderSuccessMessage').hide();
                return;
            }




            // Prepare data payload for AJAX
            const requestData = {
                amount: amount,
                currency: 'USD', // Only USD is supported
                description: description,
                merchant_reference: merchantReference,
                billing_details: {
                    first_name: firstName,
                    last_name: lastName,
                    address_line1: addressLine1,
                    address_line2: addressLine2,
                    city: city,
                    state: state,
                    postal_code: postalCode,
                    country: country
                }
            };

            // Add email or phone number to requestData based on what's filled
            if (email) {
                requestData.email_address = email;
            }
            if (phoneNumber) {
                const fullPhoneNumber = iti.getNumber();
                requestData.phone_number = fullPhoneNumber;
            }


            // Hide any previous error messages
            $('#orderErrorMessage').hide();

            // Update the submit button to show processing
            var $submitButton = $('#submitOrderButton');
            $submitButton.prop('disabled', true);
            $submitButton.html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
            );

            // Perform AJAX request if validation passes
            $.ajax({
                url: 'payment_order.php', // Backend endpoint to handle order request
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify(requestData),
                success: function(response) {
                    if (response.success) {
                        $('#orderSuccessMessage').text(response.message ||
                            'Order submitted successfully!').show();
                        $('#orderErrorMessage').hide();

                        if (response.redirect_url) {
                            // Notify user
                            $('#orderSuccessMessage').append(
                                '<br>You will be redirected to the payment page shortly.'
                            );

                             // Redirect after a delay
                            setTimeout(function() {
                                 window.location.href = response.redirect_url;
                             }, 3000);
                        }
                    } else if (response.error) {
                        const errorMessage = response.response ?
                            `${response.error}: ${JSON.stringify(response.response)}` :
                            response.error;
                        $('#orderErrorMessage').text(errorMessage).show();
                        $('#orderSuccessMessage').hide();
                    }


                    // Restore the submit button
                    $submitButton.prop('disabled', false);
                    $submitButton.html('Submit Order');

                },
                error: function(jqXHR) {
                    const errorResponse = jqXHR.responseJSON || {
                        error: 'There was an unexpected error. Please try again.'
                    };
                    const errorMessage = errorResponse.response ?
                        `${errorResponse.error}: ${JSON.stringify(errorResponse.response)}` :
                        errorResponse.error;
                    $('#orderErrorMessage').text(errorMessage).show();
                    $('#orderSuccessMessage').hide();
                }
            });
        });
    });
    </script>

</body>

</html>
