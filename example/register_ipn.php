<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Register IPN URL</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS to Prevent FOUC -->
    <style>
    /* Hide content initially */
    .content-wrapper {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease-in;
    }

    /* Show content once loaded */
    .content-visible {
        display: block;
        opacity: 1;
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
    <div class="container content-wrapper" id="contentWrapper">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="mt-5 text-center">Register IPN URL</h2>
                <p class="text-muted text-center">Enter the IPN URL for your Pesapal integration.</p>

                <!-- Success Message -->
                <div id="successMessage" class="alert alert-success mt-4" style="display: none;">
                    IPN URL registered successfully!
                </div>

                <!-- Error Message -->
                <div id="errorMessage" class="alert alert-danger mt-4" style="display: none;">
                    There was an error registering the IPN URL. Please try again.
                </div>
                <!-- IPN URL Registration Form -->
                <form id="ipnForm" action="/configure_ipn.php" method="POST">
                    <div class="form-group">
                        <label for="ipnUrl">IPN URL</label>
                        <input type="url" class="form-control" id="ipnUrl" name="ipn_url"
                            placeholder="https://www.example.com/ipn" required>
                        <small class="form-text text-muted">The IPN URL is where Pesapal will send payment
                            notifications.</small>
                    </div>

                    <!-- Display notification ID if it already exists -->
                    <div class="form-group" id="notificationIdContainer" style="display: none;">
                        <label for="notificationId">Existing Notification ID</label>
                        <input type="text" class="form-control" id="notificationId" name="notification_id" readonly>
                        <small class="form-text text-muted">This ID is already registered with Pesapal. If you want to
                            change it, enter a new IPN URL above.</small>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary btn-block" id="registerIpnButton">Register IPN
                        URL</button>

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

    <!-- JavaScript to Show Content After Load -->
    <script>
    // Display content after the page fully loads to prevent FOUC
    window.addEventListener('load', function() {
        document.getElementById('contentWrapper').classList.add('content-visible');
    });

    $(document).ready(function() {
        // AJAX form submission logic with validation
        $('#ipnForm').on('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            const ipnUrl = $('#ipnUrl').val();
            const urlPattern =
                /^(https?:\/\/)?([a-zA-Z0-9\-_]+\.)+[a-zA-Z]{2,}(:\d+)?(\/.*)?$/; // URL validation pattern

            // Validate IPN URL field
            if (!ipnUrl) {
                $('#errorMessage').text('Please enter an IPN URL.').show();
                $('#successMessage').hide();
                return;
            }
            if (!urlPattern.test(ipnUrl)) {
                $('#errorMessage').text('Please enter a valid URL.').show();
                $('#successMessage').hide();
                return;
            }

            // Hide any previous error messages
            $('#errorMessage').hide();

            // Update the submit button to show processing
            const $katorymnd_j9zndeu = $('#registerIpnButton');
            $katorymnd_j9zndeu.prop('disabled', true);
            $katorymnd_j9zndeu.html(
                '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...'
            );

            // Perform AJAX request if validation passes
            $.ajax({
                url: 'configure_ipn.php', // Backend endpoint to handle registration
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    ipn_url: ipnUrl
                }),
                success: function(response) {
                    if (response.notification_id) {
                        // Show notification ID if registration was successful
                        $('#notificationIdContainer').show();
                        $('#notificationId').val(response.notification_id);
                        $('#successMessage').text(response.message ||
                            'IPN URL registered successfully!').show();
                        $('#errorMessage').hide();
                    } else if (response.error) {
                        const errorMessage = response.response ?
                            `${response.error}: ${JSON.stringify(response.response)}` :
                            response.error;
                        $('#errorMessage').text(errorMessage).show();
                        $('#successMessage').hide();
                    }


                    // Restore the submit button
                    $katorymnd_j9zndeu.prop('disabled', false);
                    $katorymnd_j9zndeu.html('Register IPN URL');

                },
                error: function(jqXHR) {
                    // Parse and display any error response from the backend
                    const errorResponse = jqXHR.responseJSON || {
                        error: 'There was an unexpected error. Please try again.'
                    };
                    const errorMessage = errorResponse.response ?
                        `${errorResponse.error}: ${JSON.stringify(errorResponse.response)}` :
                        errorResponse.error;
                    $('#errorMessage').text(errorMessage).show();
                    $('#successMessage').hide();
                }
            });
        });
    });
    </script>

</body>

</html>